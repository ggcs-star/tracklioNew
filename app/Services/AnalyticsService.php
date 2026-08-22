<?php

namespace App\Services;

use App\Models\Post;
use App\Models\ShortLink;
use App\Models\SocialAccount;
use App\Models\SocialHourlyStat;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnalyticsService
{
    protected string $graphVersion = 'v24.0';

    /**
     * Dashboard rule:
     * 1/7 days  = DB snapshots (Facebook + Instagram + YouTube, all merged)
     * 28/30/90  = LIVE APIs (Facebook + Instagram + YouTube, all merged)
     */
    public function getAllAnalytics(
        string $userId,
        string $platformFilter = 'all',
        string $pageFilter = 'all',
        int $days = 7,
        string $instagramFilter = 'all',
        string $youtubeFilter = 'all'
    ): array {
        $platformFilter  = $this->normalizePlatformFilter($platformFilter);
        $pageFilter      = $this->normalizeSelectionFilter($pageFilter);
        $instagramFilter = $this->normalizeSelectionFilter($instagramFilter);
        $youtubeFilter   = $this->normalizeSelectionFilter($youtubeFilter);

        $days = in_array($days, [1, 7, 28, 30, 90], true) ? $days : 7;

        $startDate = Carbon::today()->subDays($days - 1);
        $endDate   = Carbon::today();

        $response = [
            'success' => true,
            'totalReach' => 0,
            'totalEngagement' => 0,
            'totalClicks' => 0,
            'followerGrowth' => 0,
            'labels' => [],
            'engagementData' => array_fill(0, $days, 0),
            'likesData' => array_fill(0, $days, 0),
            'sharesData' => array_fill(0, $days, 0),
            'reachData' => array_fill(0, $days, 0),
            'platformReach' => [0, 0, 0],
            'platformEngagement' => [0, 0, 0],
            'pages' => [],
            'recentActivity' => [],
        ];

        for ($i = 0; $i < $days; $i++) {
            $response['labels'][] = $startDate->copy()->addDays($i)->format('d M');
        }

        $response['totalClicks'] = (int) ShortLink::where('user_id', $userId)->sum('click_count');

        if ($days <= 7) {
            // 1 / 7 days -> DB snapshots (Facebook + Instagram + YouTube)
            $response = $this->fromDatabase(
                $userId,
                $platformFilter,
                $pageFilter,
                $instagramFilter,
                $youtubeFilter,
                $startDate,
                $endDate,
                $days,
                $response
            );
        } else {
            // 28 / 30 / 90 days -> Live APIs (Facebook + Instagram + YouTube)
            $response = $this->fromLiveApis(
                $userId,
                $platformFilter,
                $pageFilter,
                $instagramFilter,
                $youtubeFilter,
                $startDate,
                $endDate,
                $days,
                $response
            );
        }

        $response['recentActivity'] = $this->recentActivity($userId, $platformFilter);

        return $this->normalize($response, $days);
    }

    /* ============================================================
     * DATABASE: TODAY / 7 DAYS
     * All platforms (Facebook, Instagram, YouTube) come from
     * SocialHourlyStat snapshots when platformFilter === 'all'.
     * ============================================================ */
    private function fromDatabase(
        string $userId,
        string $platformFilter,
        string $pageFilter,
        string $instagramFilter,
        string $youtubeFilter,
        Carbon $startDate,
        Carbon $endDate,
        int $days,
        array $response
    ): array {
        $query = SocialHourlyStat::where('user_id', $userId)
            ->whereBetween('stat_date', [$startDate->toDateString(), $endDate->toDateString()]);

        if ($platformFilter !== 'all') {
            $query->where('platform', $platformFilter);
        }

        if ($platformFilter === 'facebook' && $pageFilter !== 'all') {
            $query->where('page_id', $pageFilter);
        }

        if ($platformFilter === 'instagram' && $instagramFilter !== 'all') {

            $instagramAccount = SocialAccount::where('user_id', $userId)
                ->where('status', 'connected')
                ->get()
                ->first(function ($account) use ($instagramFilter) {

                    if (!$this->isPlatformAccount($account, 'instagram')) {
                        return false;
                    }

                    $credentials = $this->accountCredentials($account);

                    $values = [
                        (string) ($account->_id ?? ''),
                        (string) ($account->id ?? ''),
                        (string) ($account->username ?? ''),
                        (string) ($account->name ?? ''),
                        (string) ($account->handle ?? ''),
                        (string) ($account->page_id ?? ''),
                        (string) ($account->instagram_business_id ?? ''),
                        (string) ($account->instagram_business_account_id ?? ''),
                        (string) ($credentials['username'] ?? ''),
                        (string) ($credentials['name'] ?? ''),
                        (string) ($credentials['instagram_business_id'] ?? ''),
                        (string) ($credentials['instagram_business_account_id'] ?? ''),
                        (string) ($credentials['page_id'] ?? ''),
                        (string) ($credentials['pageId'] ?? ''),
                    ];

                    $filter = strtolower(trim((string) $instagramFilter));

                    foreach ($values as $value) {
                        if (
                            $value !== '' &&
                            strtolower(trim($value)) === $filter
                        ) {
                            return true;
                        }
                    }

                    return false;
                });

            if ($instagramAccount) {

                $credentials = $this->accountCredentials($instagramAccount);

                $businessId = $this->firstValue(
                    $instagramAccount,
                    [
                        'instagram_business_id',
                        'instagram_business_account_id',
                        'page_id',
                    ],
                    $credentials,
                    [
                        'instagram_business_id',
                        'instagram_business_account_id',
                        'page_id',
                        'pageId',
                    ]
                );

                if ($businessId) {
                    $query->where('page_id', $businessId);
                } else {
                    $query->whereRaw('1 = 0');
                }

            } else {
                $query->whereRaw('1 = 0');
            }


        } elseif ($platformFilter === 'youtube' && $youtubeFilter !== 'all') {

            $youtubeAccount = SocialAccount::where('user_id', $userId)
                ->where('status', 'connected')
                ->get()
                ->first(function ($account) use ($youtubeFilter) {

                    if (!$this->isPlatformAccount($account, 'youtube')) {
                        return false;
                    }

                    $credentials = $this->accountCredentials($account);

                    $values = [
                        (string) ($account->_id ?? ''),
                        (string) ($account->id ?? ''),
                        (string) ($account->channel_id ?? ''),
                        (string) ($account->username ?? ''),
                        (string) ($account->name ?? ''),
                        (string) ($account->handle ?? ''),
                        (string) ($account->title ?? ''),
                        (string) ($credentials['channel_id'] ?? ''),
                        (string) ($credentials['channelId'] ?? ''),
                        (string) ($credentials['channel_name'] ?? ''),
                        (string) ($credentials['channel_title'] ?? ''),
                        (string) ($credentials['username'] ?? ''),
                        (string) ($credentials['name'] ?? ''),
                        (string) ($credentials['title'] ?? ''),
                        (string) ($credentials['handle'] ?? ''),
                    ];

                    $filter = strtolower(trim((string) $youtubeFilter));

                    foreach ($values as $value) {
                        if (
                            $value !== '' &&
                            strtolower(trim($value)) === $filter
                        ) {
                            return true;
                        }
                    }

                    return false;
                });

            if ($youtubeAccount) {

                $credentials = $this->accountCredentials($youtubeAccount);

                $channelId = $this->firstValue(
                    $youtubeAccount,
                    [
                        'channel_id',
                    ],
                    $credentials,
                    [
                        'channel_id',
                        'channelId',
                    ]
                );

                if ($channelId) {
                    $query->where('page_id', $channelId);
                } else {
                    $query->whereRaw('1 = 0');
                }

            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $stats = $query->orderBy('stat_date')->orderBy('stat_hour')->get();

        /*
         * Cron stores cumulative/day-to-date values every hour.
         * Take only the last row of each platform/page/date.
         */
        $daily = $stats->groupBy(function ($stat) {
            return implode('|', [
                (string)($stat->platform ?? ''),
                (string)($stat->page_id ?? ''),
                (string)($stat->stat_date ?? ''),
            ]);
        })->map(function ($rows) {
            return $rows->sortByDesc(fn($row) => (int)($row->stat_hour ?? 0))->first();
        });

        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i)->toDateString();

            foreach ($daily as $stat) {
                if ((string)$stat->stat_date !== $date) {
                    continue;
                }

                $platform = strtolower((string)($stat->platform ?? ''));
                $reach = (int)($stat->reach ?? $stat->views ?? 0);
                $engagement = (int)($stat->engagement ?? $stat->total_interactions ?? 0);
                $likes = (int)($stat->likes ?? 0);
                $shares = (int)($stat->shares ?? 0);

                $response['reachData'][$i] += $reach;
                $response['engagementData'][$i] += $engagement;
                $response['likesData'][$i] += $likes;
                $response['sharesData'][$i] += $shares;

                if ($platform === 'facebook') {
                    $response['platformReach'][0] += $reach;
                    $response['platformEngagement'][0] += $engagement;
                } elseif ($platform === 'instagram') {
                    $response['platformReach'][1] += $reach;
                    $response['platformEngagement'][1] += $engagement;
                } elseif ($platform === 'youtube') {
                    $response['platformReach'][2] += (int)($stat->views ?? $reach);
                    $response['platformEngagement'][2] += $engagement;
                }
            }
        }

        $response['totalReach'] = array_sum($response['reachData']);
        $response['totalEngagement'] = array_sum($response['engagementData']);

        // Follower growth across ALL platforms present in $stats
        // (already scoped to platformFilter/pageFilter by the query above).
        $response['followerGrowth'] = $this->dbFollowerGrowth($stats);

        $fb = SocialAccount::where('user_id', $userId)
            ->where('status', 'connected')
            ->get()
            ->first(fn($account) => strtolower((string)($account->platform ?? '')) === 'facebook');

        $response['pages'] = is_array($fb?->pages) ? $fb->pages : [];

        return $response;
    }

    /* ============================================================
     * LIVE: 28 / 30 / 90 DAYS
     * All platforms (Facebook, Instagram, YouTube) are fetched
     * live and merged independently when platformFilter === 'all'.
     * ============================================================ */
    private function fromLiveApis(
        string $userId,
        string $platformFilter,
        string $pageFilter,
        string $instagramFilter,
        string $youtubeFilter,
        Carbon $startDate,
        Carbon $endDate,
        int $days,
        array $response
    ): array {
        /*
         * Calls are independent. Each platform is isolated so one
         * API failure cannot blank the other platforms.
         */

        if ($platformFilter === 'all' || $platformFilter === 'facebook') {
            try {
                $fb = $this->facebookLive(
                    $userId,
                    $pageFilter,
                    $startDate,
                    $endDate,
                    $days
                );
                $this->mergeFacebook($response, $fb, $days);
                Log::info('Dashboard live Facebook merged', [
                    'user_id' => $userId,
                    'reach' => $fb['reach'] ?? 0,
                    'engagement' => $fb['engagement'] ?? 0,
                ]);
            } catch (\Throwable $e) {
                Log::error('Facebook analytics failed', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($platformFilter === 'all' || $platformFilter === 'instagram') {
            try {
                $ig = $this->instagramLive(
                    $userId,
                    $instagramFilter,
                    $startDate,
                    $endDate,
                    $days
                );
                $this->mergeInstagram($response, $ig, $days);
                Log::info('Dashboard live Instagram merged', [
                    'user_id' => $userId,
                    'reach' => $ig['reach'] ?? 0,
                    'engagement' => $ig['engagement'] ?? 0,
                ]);
            } catch (\Throwable $e) {
                Log::error('Instagram analytics failed', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($platformFilter === 'all' || $platformFilter === 'youtube') {
            try {
                $yt = $this->youtubeLive(
                    $userId,
                    $youtubeFilter,
                    $startDate,
                    $endDate,
                    $days
                );
                $this->mergeYoutube($response, $yt, $days);
                Log::info('Dashboard live YouTube merged', [
                    'user_id' => $userId,
                    'views' => $yt['views'] ?? 0,
                    'engagement' => $yt['engagement'] ?? 0,
                ]);
            } catch (\Throwable $e) {
                Log::error('YouTube analytics failed', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $fb = SocialAccount::where('user_id', $userId)
            ->where('status', 'connected')
            ->get()
            ->first(fn($account) => strtolower((string)($account->platform ?? '')) === 'facebook');

        $response['pages'] = is_array($fb?->pages) ? $fb->pages : [];

        return $response;
    }

    /* ============================================================
     * FACEBOOK LIVE
     * ============================================================ */
    private function facebookLive(
        string $userId,
        string $pageFilter,
        Carbon $startDate,
        Carbon $endDate,
        int $days
    ): array {
        $out = [
            'reach' => 0,
            'engagement' => 0,
            'followers' => 0,
            'reachData' => array_fill(0, $days, 0),
            'engagementData' => array_fill(0, $days, 0),
            'likesData' => array_fill(0, $days, 0),
            'sharesData' => array_fill(0, $days, 0),
        ];

        $account = SocialAccount::where('user_id', $userId)
            ->where('status', 'connected')
            ->get()
            ->first(fn($account) => $this->isPlatformAccount($account, 'facebook'));

        if (!$account) {
            return $out;
        }

        $pages = is_array($account->pages ?? null) ? $account->pages : [];

        foreach ($pages as $page) {
            $pageId = (string)($page['page_id'] ?? '');
            $token = $page['page_access_token']
                ?? data_get($account->credentials, 'page_access_token')
                ?? data_get($account->credentials, 'access_token');

            if (!$pageId || !$token) {
                continue;
            }

            if ($pageFilter !== 'all') {

    $filter = strtolower(trim((string) $pageFilter));

    $pageValues = [
        strtolower(trim((string) ($page['page_id'] ?? ''))),
        strtolower(trim((string) ($page['id'] ?? ''))),
        strtolower(trim((string) ($page['name'] ?? ''))),
        strtolower(trim((string) ($page['page_name'] ?? ''))),
        strtolower(trim((string) ($page['username'] ?? ''))),
    ];

    $matched = false;

    foreach ($pageValues as $value) {
        if ($value !== '' && $value === $filter) {
            $matched = true;
            break;
        }
    }

    if (!$matched) {
        continue;
    }
}
            /*
             * One profile request + one insights request per page.
             * Never fetch every post from Meta.
             */
            $profile = Http::timeout(10)
                ->withToken($token)
                ->get("https://graph.facebook.com/{$this->graphVersion}/{$pageId}", [
                    'fields' => 'followers_count,name',
                ])->json();

            $out['followers'] += (int)($profile['followers_count'] ?? 0);

            $insights = Http::timeout(15)
                ->withToken($token)
                ->get("https://graph.facebook.com/{$this->graphVersion}/{$pageId}/insights", [
                    'metric' => 'page_total_media_view_unique,page_post_engagements',
                    'period' => 'day',
                    'since' => $startDate->toDateString(),
                    'until' => $endDate->toDateString(),
                ])->json();

            /*
             * Fallback for accounts where the new unique-media metric
             * is unavailable.
             */
            if (isset($insights['error'])) {
                $insights = Http::timeout(15)
                    ->withToken($token)
                    ->get("https://graph.facebook.com/{$this->graphVersion}/{$pageId}/insights", [
                        'metric' => 'page_media_view,page_post_engagements',
                        'period' => 'day',
                        'since' => $startDate->toDateString(),
                        'until' => $endDate->toDateString(),
                    ])->json();
            }

            foreach (($insights['data'] ?? []) as $metric) {
                $name = (string)($metric['name'] ?? '');

                foreach (($metric['values'] ?? []) as $row) {
                    $date = $this->metaDate($row);
                    if (!$date) {
                        continue;
                    }

                    $index = $this->indexForDate($startDate, $date, $days);
                    if ($index === null) {
                        continue;
                    }

                    $value = $this->rowValue($row);

                    if (in_array($name, ['page_total_media_view_unique', 'page_media_view'], true)) {
                        $out['reachData'][$index] += $value;
                    }

                    if ($name === 'page_post_engagements') {
                        $out['engagementData'][$index] += $value;
                    }
                }
            }
        }

        $out['reach'] = array_sum($out['reachData']);
        $out['engagement'] = array_sum($out['engagementData']);

        return $out;
    }

    /* ============================================================
     * INSTAGRAM LIVE
     * ============================================================ */
    private function instagramLive(
        string $userId,
        string $filter,
        Carbon $startDate,
        Carbon $endDate,
        int $days
    ): array {
        $out = [
            'reach' => 0,
            'engagement' => 0,
            'followers' => 0,
            'reachData' => array_fill(0, $days, 0),
            'engagementData' => array_fill(0, $days, 0),
            'likesData' => array_fill(0, $days, 0),
            'sharesData' => array_fill(0, $days, 0),
        ];

        $accounts = SocialAccount::where('user_id', $userId)
            ->where('status', 'connected')
            ->get()
            ->filter(fn($account) => $this->isPlatformAccount($account, 'instagram'));

        foreach ($accounts as $account) {
            /*
             * IMPORTANT FIX:
             * The UI may send username (e.g. dillbyheart), Mongo _id,
             * instagram_business_id, or page_id. The old code compared
             * only _id, which caused a valid Instagram account to be
             * skipped and produced 0 reach / 0 engagement.
             */
            if (!$this->accountMatchesFilter($account, $filter, 'instagram')) {
                continue;
            }

            $credentials = $this->accountCredentials($account);

            $businessId = $this->firstValue($account, [
                'instagram_business_id',
                'instagram_business_account_id',
                'page_id',
            ], $credentials, [
                'instagram_business_id',
                'instagram_business_account_id',
                'page_id',
                'pageId',
            ]);

            $token = $this->firstValue($account, [
                'access_token',
                'page_access_token',
            ], $credentials, [
                'page_access_token',
                'access_token',
                'token',
            ]);

            if (!$businessId || !$token) {
                Log::warning('Instagram credentials missing', [
                    'account_id' => (string)$account->_id,
                ]);
                continue;
            }

            /*
             * PROFILE
             */
            try {
                $profile = Http::timeout(10)
                    ->withToken($token)
                    ->get("https://graph.facebook.com/{$this->graphVersion}/{$businessId}", [
                        'fields' => 'id,username,followers_count',
                    ])->json();

                if (!isset($profile['error'])) {
                    $out['followers'] += (int)($profile['followers_count'] ?? 0);
                }
            } catch (\Throwable $e) {
                Log::warning('Instagram profile failed', [
                    'account_id' => (string)$account->_id,
                    'error' => $e->getMessage(),
                ]);
            }

            /*
             * REACH:
             * Keep this separate because reach is a time-series metric.
             */
            try {
                /*
                 * Instagram reach is a TIME-SERIES metric.
                 * Explicitly pass metric_type=time_series; relying on the
                 * API default can return an empty/invalid result on newer
                 * Graph API versions, especially for long ranges.
                 *
                 * Meta stores Instagram user insights for up to 90 days,
                 * so the selected 90-day dashboard range can be requested
                 * directly.
                 */
                $reach = Http::timeout(20)
                    ->withToken($token)
                    ->get("https://graph.facebook.com/{$this->graphVersion}/{$businessId}/insights", [
                        'metric' => 'reach',
                        'period' => 'day',
                        'metric_type' => 'time_series',
                        'since' => $startDate->toDateString(),
                        'until' => $endDate->toDateString(),
                    ])->json();

                if (isset($reach['error'])) {
                    Log::warning('Instagram reach API error', [
                        'business_id' => $businessId,
                        'code' => $reach['error']['code'] ?? null,
                        'error' => $reach['error']['message'] ?? 'Unknown',
                    ]);
                }

                foreach (($reach['data'] ?? []) as $metric) {
                    if ((string)($metric['name'] ?? '') !== 'reach') {
                        continue;
                    }

                    foreach (($metric['values'] ?? []) as $row) {
                        $date = $this->metaDate($row);
                        if (!$date) {
                            continue;
                        }

                        $index = $this->indexForDate($startDate, $date, $days);
                        if ($index === null) {
                            continue;
                        }

                        $out['reachData'][$index] += $this->rowValue($row);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Instagram reach request failed', [
                    'business_id' => $businessId,
                    'error' => $e->getMessage(),
                ]);
            }

            /*
             * ENGAGEMENT:
             * total_interactions is requested as total_value.
             *
             * We also request likes/shares separately so the dashboard
             * can show them without per-post API calls.
             */
            try {
                $engagement = Http::timeout(20)
                    ->withToken($token)
                    ->get("https://graph.facebook.com/{$this->graphVersion}/{$businessId}/insights", [
                        'metric' => 'total_interactions,likes,comments,shares,saved',
                        'period' => 'day',
                        'metric_type' => 'total_value',
                        'since' => $startDate->toDateString(),
                        'until' => $endDate->toDateString(),
                    ])->json();

                if (isset($engagement['error'])) {
                    Log::warning('Instagram engagement API error', [
                        'business_id' => $businessId,
                        'code' => $engagement['error']['code'] ?? null,
                        'error' => $engagement['error']['message'] ?? 'Unknown',
                    ]);
                }

                $totalInteractions = 0;
                $likes = 0;
                $comments = 0;
                $shares = 0;
                $saved = 0;

                foreach (($engagement['data'] ?? []) as $metric) {
                    $name = (string)($metric['name'] ?? '');
                    $value = $this->totalMetricValue($metric);

                    switch ($name) {
                        case 'total_interactions':
                            $totalInteractions = $value;
                            break;
                        case 'likes':
                            $likes = $value;
                            break;
                        case 'comments':
                            $comments = $value;
                            break;
                        case 'shares':
                            $shares = $value;
                            break;
                        case 'saved':
                            $saved = $value;
                            break;
                    }
                }

                $out['engagement'] += $totalInteractions > 0
                    ? $totalInteractions
                    : ($likes + $comments + $shares + $saved);

                /*
                 * The API returns these as range totals. Keep them on the
                 * last point because we do not have a daily series here.
                 */
                $out['likesData'][$days - 1] += $likes;
                $out['sharesData'][$days - 1] += $shares;
            } catch (\Throwable $e) {
                Log::warning('Instagram engagement request failed', [
                    'business_id' => $businessId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $out['reach'] = array_sum($out['reachData']);

        /*
         * total_interactions is a total_value, not a daily series.
         * Put the range total on the last point instead of inventing
         * daily numbers.
         */
        if ($out['engagement'] > 0) {
            $out['engagementData'][$days - 1] = $out['engagement'];
        }

        return $out;
    }

    /* ============================================================
     * YOUTUBE LIVE
     * ============================================================ */
    private function youtubeLive(
        string $userId,
        string $filter,
        Carbon $startDate,
        Carbon $endDate,
        int $days
    ): array {
        $out = [
            'views' => 0,
            'engagement' => 0,
            'subscribers' => 0,
            'viewsData' => array_fill(0, $days, 0),
            'engagementData' => array_fill(0, $days, 0),
            'likesData' => array_fill(0, $days, 0),
            'sharesData' => array_fill(0, $days, 0),
        ];

        $accounts = SocialAccount::where('user_id', $userId)
            ->where('status', 'connected')
            ->get()
            ->filter(fn($account) => $this->isPlatformAccount($account, 'youtube'));

        foreach ($accounts as $account) {
            /*
             * Same filter problem can happen on YouTube.
             */
            if (!$this->accountMatchesFilter($account, $filter, 'youtube')) {
                continue;
            }

            $credentials = $this->accountCredentials($account);

            $refreshToken = $this->firstValue(
                $account,
                ['refresh_token'],
                $credentials,
                ['refresh_token', 'youtube_refresh_token']
            );

            if (!$refreshToken) {
                Log::warning('YouTube refresh token missing', [
                    'account_id' => (string)$account->_id,
                ]);
                continue;
            }

            /*
             * Refresh token -> access token
             */
            try {
                $tokenResponse = Http::timeout(10)
                    ->asForm()
                    ->post('https://oauth2.googleapis.com/token', [
                        'client_id' => env('YOUTUBE_CLIENT_ID'),
                        'client_secret' => env('YOUTUBE_CLIENT_SECRET'),
                        'refresh_token' => $refreshToken,
                        'grant_type' => 'refresh_token',
                    ]);

                if (!$tokenResponse->successful()) {
                    Log::warning('YouTube token refresh failed', [
                        'account_id' => (string)$account->_id,
                        'response' => $tokenResponse->json(),
                    ]);
                    continue;
                }

                $accessToken = $tokenResponse->json('access_token');

                if (!$accessToken) {
                    continue;
                }
            } catch (\Throwable $e) {
                Log::warning('YouTube token exception', [
                    'account_id' => (string)$account->_id,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            /*
             * Get channel id.
             * We do not depend on a stored channel_id.
             */
            try {
                $channel = Http::timeout(10)
                    ->withToken($accessToken)
                    ->get('https://www.googleapis.com/youtube/v3/channels', [
                        'part' => 'id,snippet,statistics',
                        'mine' => 'true',
                    ])->json();

                $channelItem = $channel['items'][0] ?? null;
                $channelId = $channelItem['id'] ?? null;

                if (!$channelId) {
                    Log::warning('YouTube channel not found', [
                        'account_id' => (string)$account->_id,
                        'response' => $channel,
                    ]);
                    continue;
                }

                /*
                 * Current subscriber snapshot.
                 */
                $out['subscribers'] += (int)(
                    $channelItem['statistics']['subscriberCount'] ?? 0
                );
            } catch (\Throwable $e) {
                Log::warning('YouTube channel lookup failed', [
                    'account_id' => (string)$account->_id,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            /*
             * YouTube Analytics API:
             * one request for the complete daily range.
             */
            try {
                $report = Http::timeout(20)
                    ->withToken($accessToken)
                    ->get('https://youtubeanalytics.googleapis.com/v2/reports', [
                        'ids' => 'channel==' . $channelId,
                        'startDate' => $startDate->toDateString(),
                        'endDate' => $endDate->toDateString(),
                        'metrics' => 'views,likes,comments,subscribersGained,subscribersLost',
                        'dimensions' => 'day',
                        'sort' => 'day',
                    ]);

                $json = $report->json();

                if (!$report->successful()) {
                    Log::warning('YouTube Analytics API failed', [
                        'account_id' => (string)$account->_id,
                        'status' => $report->status(),
                        'response' => $json,
                    ]);
                    continue;
                }

                /*
                 * Never assume column positions.
                 */
                $headers = array_map(
                    fn($h) => $h['name'] ?? '',
                    $json['columnHeaders'] ?? []
                );

                foreach (($json['rows'] ?? []) as $row) {
                    $mapped = [];

                    foreach ($headers as $index => $name) {
                        $mapped[$name] = $row[$index] ?? 0;
                    }

                    $date = $mapped['day'] ?? null;
                    if (!$date) {
                        continue;
                    }

                    $index = $this->indexForDate(
                        $startDate,
                        $date,
                        $days
                    );

                    if ($index === null) {
                        continue;
                    }

                    $views = (int)($mapped['views'] ?? 0);
                    $likes = (int)($mapped['likes'] ?? 0);
                    $comments = (int)($mapped['comments'] ?? 0);

                    $engagement = $likes + $comments;

                    $out['viewsData'][$index] += $views;
                    $out['likesData'][$index] += $likes;
                    $out['engagementData'][$index] += $engagement;

                    $out['subscribers'] += max(
                        0,
                        (int)($mapped['subscribersGained'] ?? 0)
                        - (int)($mapped['subscribersLost'] ?? 0)
                    );
                }
            } catch (\Throwable $e) {
                Log::error('YouTube Analytics exception', [
                    'account_id' => (string)$account->_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $out['views'] = array_sum($out['viewsData']);
        $out['engagement'] = array_sum($out['engagementData']);

        return $out;
    }

    /* ============================================================
     * FILTER MATCH
     * ============================================================ */
    private function isPlatformAccount($account, string $platform): bool
    {
        $platform = strtolower($platform);
        $values = [
            strtolower((string)($account->platform ?? '')),
            strtolower((string)($account->provider ?? '')),
            strtolower((string)($account->type ?? '')),
        ];

        $credentials = $this->accountCredentials($account);

        $values[] = strtolower((string)($credentials['platform'] ?? ''));
        $values[] = strtolower((string)($credentials['provider'] ?? ''));

        foreach ($values as $value) {
            if (
                $value === $platform ||
                str_contains($value, $platform)
            ) {
                return true;
            }
        }

        return false;
    }

    private function accountCredentials($account): array
    {
        $credentials = $account->credentials ?? [];

        if (is_object($credentials)) {
            $credentials = (array)$credentials;
        }

        return is_array($credentials) ? $credentials : [];
    }

    private function firstValue(
        $account,
        array $accountKeys,
        array $credentials,
        array $credentialKeys
    ): ?string {
        foreach ($accountKeys as $key) {
            $value = data_get($account, $key);

            if (is_string($value) || is_numeric($value)) {
                $value = trim((string)$value);

                if ($value !== '') {
                    return $value;
                }
            }
        }

        foreach ($credentialKeys as $key) {
            $value = data_get($credentials, $key);

            if (is_string($value) || is_numeric($value)) {
                $value = trim((string)$value);

                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function accountMatchesFilter($account, string $filter, string $platform): bool
    {
        if ($filter === 'all' || trim($filter) === '') {
            return true;
        }

        $filter = strtolower(trim($filter));
        $credentials = $this->accountCredentials($account);

        $values = [
            strtolower((string)($account->_id ?? '')),
            strtolower((string)($account->id ?? '')),
            strtolower((string)($account->page_id ?? '')),
            strtolower((string)($account->channel_id ?? '')),
            strtolower((string)($account->username ?? '')),
            strtolower((string)($account->name ?? '')),
            strtolower((string)($account->handle ?? '')),
            strtolower((string)($account->title ?? '')),
        ];

        foreach ([
            'instagram_business_id',
            'instagram_business_account_id',
            'username',
            'name',
            'channel_id',
            'channelId',
            'page_id',
            'pageId',
            'channel_title',
            'channel_name',
            'title',
            'handle',
        ] as $key) {
            $value = data_get($credentials, $key);

            if (is_scalar($value)) {
                $values[] = strtolower(trim((string)$value));
            }
        }

        foreach ($values as $value) {
            if ($value !== '' && $value === $filter) {
                return true;
            }
        }

        return false;
    }

    /* ============================================================
     * RECENT ACTIVITY - DB ONLY
     * ============================================================ */
    private function recentActivity(string $userId, string $platformFilter): array
    {
        $items = [];

        $map = [
            'facebook' => [
                'id' => 'facebook_post_id',
                'name' => 'Facebook',
                'description' => '📝 Posted on Facebook',
                'icon' => 'fab fa-facebook-f',
                'iconColor' => 'blue',
                'bg' => 'bg-blue-100',
                'text' => 'text-blue-600',
            ],
            'instagram' => [
                'id' => 'instagram_post_id',
                'name' => 'Instagram',
                'description' => '📷 Posted on Instagram',
                'icon' => 'fab fa-instagram',
                'iconColor' => 'pink',
                'bg' => 'bg-pink-100',
                'text' => 'text-pink-600',
            ],
            'youtube' => [
                'id' => 'youtube_video_id',
                'name' => 'YouTube',
                'description' => '🎬 Posted on YouTube',
                'icon' => 'fab fa-youtube',
                'iconColor' => 'red',
                'bg' => 'bg-red-100',
                'text' => 'text-red-600',
            ],
        ];

        $platforms = $platformFilter === 'all'
            ? array_keys($map)
            : [$platformFilter];

        foreach ($platforms as $platform) {
            if (!isset($map[$platform])) {
                continue;
            }

            $cfg = $map[$platform];

            $posts = Post::where('user_id', $userId)
                ->where('status', 'published')
                ->whereNotNull($cfg['id'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            foreach ($posts as $post) {
                if (!$this->postHasPlatform($post, $platform)) {
                    continue;
                }

                $created = $post->created_at;

                $items[] = [
                    'platform' => $platform,
                    'platform_name' => $cfg['name'],
                    'id' => (string)$post->_id,
                    'title' => mb_substr(
                        strip_tags((string)($post->content ?? $cfg['name'] . ' Post')),
                        0,
                        120
                    ),
                    'description' => $cfg['description'],
                    'timestamp' => $created
                        ? $created->toIso8601String()
                        : now()->toIso8601String(),
                    'created_at' => $created
                        ? $created->diffForHumans()
                        : 'Just now',
                    'media_url' => $post->media_path
                        ? asset('storage/' . $post->media_path)
                        : null,
                    'icon' => $cfg['icon'],
                    'icon_color' => $cfg['iconColor'],
                    'bg_color' => $cfg['bg'],
                    'text_color' => $cfg['text'],
                    'insights' => [],
                ];
            }
        }

        usort($items, fn($a, $b) =>
            strtotime($b['timestamp']) <=> strtotime($a['timestamp'])
        );

        return array_slice($items, 0, 15);
    }

    private function postHasPlatform($post, string $platform): bool
    {
        $platforms = $post->platforms ?? [];

        if (is_string($platforms)) {
            $decoded = json_decode($platforms, true);

            if (is_array($decoded)) {
                $platforms = $decoded;
            } else {
                $platforms = array_filter(
                    array_map('trim', explode(',', $platforms))
                );
            }
        }

        return is_array($platforms) && in_array($platform, $platforms, true);
    }

    /* ============================================================
     * MERGE
     * ============================================================ */
    private function mergeFacebook(array &$r, array $d, int $days): void
    {
        $r['totalReach'] += (int)($d['reach'] ?? 0);
        $r['totalEngagement'] += (int)($d['engagement'] ?? 0);
        $r['followerGrowth'] += (int)($d['followers'] ?? 0);
        $r['platformReach'][0] += (int)($d['reach'] ?? 0);
        $r['platformEngagement'][0] += (int)($d['engagement'] ?? 0);

        for ($i = 0; $i < $days; $i++) {
            $r['reachData'][$i] += (int)($d['reachData'][$i] ?? 0);
            $r['engagementData'][$i] += (int)($d['engagementData'][$i] ?? 0);
            $r['likesData'][$i] += (int)($d['likesData'][$i] ?? 0);
            $r['sharesData'][$i] += (int)($d['sharesData'][$i] ?? 0);
        }
    }

    private function mergeInstagram(array &$r, array $d, int $days): void
    {
        $r['totalReach'] += (int)($d['reach'] ?? 0);
        $r['totalEngagement'] += (int)($d['engagement'] ?? 0);
        $r['followerGrowth'] += (int)($d['followers'] ?? 0);
        $r['platformReach'][1] += (int)($d['reach'] ?? 0);
        $r['platformEngagement'][1] += (int)($d['engagement'] ?? 0);

        for ($i = 0; $i < $days; $i++) {
            $r['reachData'][$i] += (int)($d['reachData'][$i] ?? 0);
            $r['engagementData'][$i] += (int)($d['engagementData'][$i] ?? 0);
            $r['likesData'][$i] += (int)($d['likesData'][$i] ?? 0);
            $r['sharesData'][$i] += (int)($d['sharesData'][$i] ?? 0);
        }
    }

    private function mergeYoutube(array &$r, array $d, int $days): void
    {
        $views = (int)($d['views'] ?? 0);
        $engagement = (int)($d['engagement'] ?? 0);

        $r['totalReach'] += $views;
        $r['totalEngagement'] += $engagement;
        $r['followerGrowth'] += (int)($d['subscribers'] ?? 0);
        $r['platformReach'][2] += $views;
        $r['platformEngagement'][2] += $engagement;

        for ($i = 0; $i < $days; $i++) {
            $r['reachData'][$i] += (int)($d['viewsData'][$i] ?? 0);
            $r['engagementData'][$i] += (int)($d['engagementData'][$i] ?? 0);
            $r['likesData'][$i] += (int)($d['likesData'][$i] ?? 0);
            $r['sharesData'][$i] += (int)($d['sharesData'][$i] ?? 0);
        }
    }

    /* ============================================================
     * HELPERS
     * ============================================================ */
    private function dbFollowerGrowth($stats): int
{
    $latest = [];

    foreach ($stats as $stat) {

        $key = implode('|', [
            (string)($stat->platform ?? ''),
            (string)($stat->page_id ?? ''),
        ]);

        if (
            !isset($latest[$key]) ||
            (
                (string)($stat->stat_date ?? '') .
                sprintf('%02d', (int)($stat->stat_hour ?? 0))
            ) >
            (
                (string)($latest[$key]->stat_date ?? '') .
                sprintf('%02d', (int)($latest[$key]->stat_hour ?? 0))
            )
        ) {
            $latest[$key] = $stat;
        }
    }

    $followers = 0;

    foreach ($latest as $stat) {
        $followers += (int)($stat->followers ?? 0);
    }

    return $followers;
}

    private function metaDate(array $row): ?string
    {
        try {
            /*
             * Prefer start_time when available.
             * This avoids the old one-day shift bug.
             */
            if (!empty($row['start_time'])) {
                return Carbon::parse($row['start_time'])->toDateString();
            }

            if (!empty($row['end_time'])) {
                return Carbon::parse($row['end_time'])
                    ->subDay()
                    ->toDateString();
            }

            if (!empty($row['date'])) {
                return Carbon::parse($row['date'])->toDateString();
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    private function rowValue(array $row): int
    {
        $value = $row['value'] ?? 0;
        return is_array($value) ? 0 : (int)$value;
    }

    private function totalMetricValue(array $metric): int
    {
        if (isset($metric['total_value'])) {
            $value = $metric['total_value'];

            if (is_array($value)) {
                return (int)($value['value'] ?? $value['total'] ?? 0);
            }

            return (int)$value;
        }

        foreach (($metric['values'] ?? []) as $row) {
            if (isset($row['value']) && !is_array($row['value'])) {
                return (int)$row['value'];
            }
        }

        return isset($metric['value']) && !is_array($metric['value'])
            ? (int)$metric['value']
            : 0;
    }

    private function indexForDate(
        Carbon $startDate,
        string $date,
        int $days
    ): ?int {
        try {
            $target = Carbon::parse($date)->startOfDay();
            $index = $startDate->copy()->startOfDay()->diffInDays($target, false);

            return ($index >= 0 && $index < $days) ? (int)$index : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizePlatformFilter(mixed $value): string
    {
        $value = $this->toStringFilter($value);

        $key = strtolower(trim($value));
        $key = str_replace(['_', '-'], ' ', $key);

        if (
            $key === '' ||
            $key === 'all' ||
            str_contains($key, 'all platform')
        ) {
            return 'all';
        }

        return match ($key) {
            'facebook', 'fb' => 'facebook',
            'instagram', 'ig' => 'instagram',
            'youtube', 'yt' => 'youtube',
            default => $key,
        };
    }

    private function normalizeSelectionFilter(mixed $value): string
    {
        $value = $this->toStringFilter($value);
        $key = strtolower(trim($value));

        if (
            $key === '' ||
            $key === 'all' ||
            str_starts_with($key, 'all ')
        ) {
            return 'all';
        }

        return $value;
    }

    private function toStringFilter(mixed $value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return (string)$value;
        }

        return 'all';
    }

    private function normalize(array $r, int $days): array
    {
        $r['totalReach'] = (int)($r['totalReach'] ?? 0);
        $r['totalEngagement'] = (int)($r['totalEngagement'] ?? 0);
        $r['totalClicks'] = (int)($r['totalClicks'] ?? 0);
        $r['followerGrowth'] = (int)($r['followerGrowth'] ?? 0);

        foreach ([
            'engagementData',
            'likesData',
            'sharesData',
            'reachData',
        ] as $key) {
            $r[$key] = array_values(array_map(
                'intval',
                array_pad($r[$key] ?? [], $days, 0)
            ));
            $r[$key] = array_slice($r[$key], 0, $days);
        }

        $r['platformReach'] = array_slice(
            array_values(array_map(
                'intval',
                array_pad($r['platformReach'] ?? [], 3, 0)
            )),
            0,
            3
        );

        $r['platformEngagement'] = array_slice(
            array_values(array_map(
                'intval',
                array_pad($r['platformEngagement'] ?? [], 3, 0)
            )),
            0,
            3
        );

        return $r;
    }
}