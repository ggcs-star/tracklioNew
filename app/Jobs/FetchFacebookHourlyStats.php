<?php

namespace App\Jobs;

use App\Models\SocialAccount;
use App\Models\SocialHourlyStat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FetchFacebookHourlyStats implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 2;

    protected string $fbVersion = 'v19.0';

    public function handle()
    {
        $today = now()->toDateString();
        
        Log::info('FetchFacebookHourlyStats: STARTED');

        // ========== 1. FACEBOOK ==========
        $this->fetchFacebook($today);

        // ========== 2. INSTAGRAM ==========
        $this->fetchInstagram($today);

        // ========== 3. YOUTUBE ==========
        $this->fetchYouTube($today);

        Log::info('FetchFacebookHourlyStats: COMPLETED');
    }

    // ==================== FACEBOOK ====================
    
    private function fetchFacebook(string $today)
    {
        $fbAccounts = SocialAccount::where('platform', 'facebook')
            ->where('status', 'connected')
            ->get();

        if ($fbAccounts->isEmpty()) {
            Log::info('No Facebook accounts');
            return;
        }

        foreach ($fbAccounts as $account) {
            if (empty($account->pages)) continue;

            foreach ($account->pages as $page) {
                $pageId = $page['page_id'] ?? null;
                $token = $this->getToken($page, $account);

                if (!$pageId || !$token) continue;

                try {
                    Log::info("Processing Facebook: " . ($page['page_name'] ?? $pageId));

                    // 1. Get followers
                    $followers = $this->getFacebookFollowers($pageId, $token);

                    // 2. Get today's reach (NO LOOP)
                    $reach = $this->getFacebookReach($pageId, $token, $today);

                    // 3. Get today's engagement (NO LOOP)
                    $engagement = $this->getFacebookEngagement($pageId, $token, $today);

                    // 4. Save - WITHOUT stat_hour
                    SocialHourlyStat::updateOrCreate(
                        [
                            'platform' => 'facebook',
                            'page_id' => $pageId,
                            'stat_date' => $today,
                            // stat_hour MAT DAALO
                        ],
                        [
                            'user_id' => (string) $account->user_id,
                            'reach' => $reach > 0 ? $reach : 0,
                            'engagement' => $engagement > 0 ? $engagement : 0,
                            'followers' => $followers > 0 ? $followers : 0,
                            'likes' => 0,
                            'shares' => 0,
                            'comments' => 0,
                            // stat_hour MAT DAALO
                        ]
                    );

                    Log::info("✅ Facebook saved: " . ($page['page_name'] ?? $pageId));

                } catch (\Throwable $e) {
                    Log::error('Facebook failed: ' . $e->getMessage(), ['page_id' => $pageId]);
                }
            }
        }
    }

    private function getToken($page, $account): ?string
    {
        return $page['page_access_token'] ?? 
               $account->credentials['page_access_token'] ?? 
               $account->credentials['user_access_token'] ?? 
               $account->credentials['access_token'] ?? 
               null;
    }

    private function getFacebookFollowers(string $pageId, string $token): int
    {
        try {
            $response = Http::timeout(15)->get("https://graph.facebook.com/{$this->fbVersion}/{$pageId}", [
                'fields' => 'followers_count,fan_count',
                'access_token' => $token
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return (int) ($data['followers_count'] ?? $data['fan_count'] ?? 0);
            }
        } catch (\Throwable $e) {
            Log::error('Followers error: ' . $e->getMessage());
        }
        return 0;
    }

    private function getFacebookReach(string $pageId, string $token, string $date): int
    {
        try {
            $response = Http::timeout(15)->get("https://graph.facebook.com/{$this->fbVersion}/{$pageId}/insights", [
                'metric' => 'page_impressions',
                'period' => 'day',
                'since' => $date,
                'until' => $date,
                'access_token' => $token
            ]);

            if ($response->successful() && !isset($response->json()['error'])) {
                $data = $response->json();
                if (!empty($data['data'])) {
                    return (int) ($data['data'][0]['values'][0]['value'] ?? 0);
                }
            }
        } catch (\Throwable $e) {
            Log::debug('Reach not available');
        }
        return 0;
    }

    private function getFacebookEngagement(string $pageId, string $token, string $date): int
    {
        try {
            $response = Http::timeout(15)->get("https://graph.facebook.com/{$this->fbVersion}/{$pageId}/insights", [
                'metric' => 'page_engaged_users',
                'period' => 'day',
                'since' => $date,
                'until' => $date,
                'access_token' => $token
            ]);

            if ($response->successful() && !isset($response->json()['error'])) {
                $data = $response->json();
                if (!empty($data['data'])) {
                    return (int) ($data['data'][0]['values'][0]['value'] ?? 0);
                }
            }
        } catch (\Throwable $e) {
            Log::debug('Engagement not available');
        }
        return 0;
    }

    // ==================== INSTAGRAM ====================

    private function fetchInstagram(string $today)
    {
        $igAccounts = SocialAccount::where('platform', 'instagram')
            ->where('status', 'connected')
            ->get();

        if ($igAccounts->isEmpty()) {
            Log::info('No Instagram accounts');
            return;
        }

        foreach ($igAccounts as $account) {
            $businessId = $account->credentials['instagram_business_id'] ?? null;
            $token = $account->credentials['page_access_token'] ?? $account->credentials['access_token'] ?? null;

            if (!$businessId || !$token) continue;

            try {
                Log::info("Processing Instagram: " . $businessId);

                // Followers
                $response = Http::timeout(15)->get("https://graph.facebook.com/{$this->fbVersion}/{$businessId}", [
                    'fields' => 'followers_count',
                    'access_token' => $token
                ]);

                $followers = 0;
                if ($response->successful()) {
                    $followers = (int) ($response->json('followers_count') ?? 0);
                }

                // Reach
                $reach = $this->getInstagramReach($businessId, $token, $today);

                // Engagement
                $engagement = $this->getInstagramEngagement($businessId, $token, $today);

                SocialHourlyStat::updateOrCreate(
                    [
                        'platform' => 'instagram',
                        'page_id' => $businessId,
                        'stat_date' => $today,
                    ],
                    [
                        'user_id' => (string) $account->user_id,
                        'reach' => $reach > 0 ? $reach : 0,
                        'engagement' => $engagement > 0 ? $engagement : 0,
                        'followers' => $followers > 0 ? $followers : 0,
                        'likes' => 0,
                        'shares' => 0,
                        'comments' => 0,
                    ]
                );

                Log::info("✅ Instagram saved: $businessId");

            } catch (\Throwable $e) {
                Log::error('Instagram failed: ' . $e->getMessage());
            }
        }
    }

    private function getInstagramReach(string $businessId, string $token, string $date): int
    {
        try {
            $response = Http::timeout(15)->get("https://graph.facebook.com/{$this->fbVersion}/{$businessId}/insights", [
                'metric' => 'reach',
                'period' => 'day',
                'since' => $date,
                'until' => $date,
                'access_token' => $token
            ]);

            if ($response->successful() && !isset($response->json()['error'])) {
                $data = $response->json();
                if (!empty($data['data'])) {
                    $values = $data['data'][0]['values'] ?? [];
                    $total = 0;
                    foreach ($values as $v) {
                        $total += (int) ($v['value'] ?? 0);
                    }
                    return $total;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('Instagram reach not available');
        }
        return 0;
    }

    private function getInstagramEngagement(string $businessId, string $token, string $date): int
    {
        try {
            $response = Http::timeout(15)->get("https://graph.facebook.com/{$this->fbVersion}/{$businessId}/insights", [
                'metric' => 'engagement',
                'period' => 'day',
                'since' => $date,
                'until' => $date,
                'access_token' => $token
            ]);

            if ($response->successful() && !isset($response->json()['error'])) {
                $data = $response->json();
                if (!empty($data['data'])) {
                    $values = $data['data'][0]['values'] ?? [];
                    $total = 0;
                    foreach ($values as $v) {
                        $total += (int) ($v['value'] ?? 0);
                    }
                    return $total;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('Instagram engagement not available');
        }
        return 0;
    }

    // ==================== YOUTUBE ====================

    private function fetchYouTube(string $today)
    {
        $ytAccounts = SocialAccount::where('platform', 'youtube')
            ->where('status', 'connected')
            ->get();

        if ($ytAccounts->isEmpty()) {
            Log::info('No YouTube accounts');
            return;
        }

        foreach ($ytAccounts as $account) {
            $creds = $account->credentials;

            if (empty($creds['refresh_token']) || empty($creds['channel_id'])) continue;

            try {
                Log::info("Processing YouTube: " . $creds['channel_id']);

                // Refresh token
                $tokenRes = Http::timeout(15)->asForm()->post('https://oauth2.googleapis.com/token', [
                    'client_id' => env('YOUTUBE_CLIENT_ID'),
                    'client_secret' => env('YOUTUBE_CLIENT_SECRET'),
                    'refresh_token' => $creds['refresh_token'],
                    'grant_type' => 'refresh_token',
                ]);

                if (!$tokenRes->successful()) continue;

                $accessToken = $tokenRes->json('access_token');
                $channelId = $creds['channel_id'];

                // Channel stats
                $response = Http::timeout(15)->withToken($accessToken)->get('https://www.googleapis.com/youtube/v3/channels', [
                    'part' => 'statistics',
                    'id' => $channelId
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $stats = $data['items'][0]['statistics'] ?? [];
                    
                    $subscribers = (int) ($stats['subscriberCount'] ?? 0);
                    $views = (int) ($stats['viewCount'] ?? 0);
                    $likes = (int) ($stats['likeCount'] ?? 0);
                    $comments = (int) ($stats['commentCount'] ?? 0);
                    $engagement = $likes + $comments;

                    SocialHourlyStat::updateOrCreate(
                        [
                            'platform' => 'youtube',
                            'page_id' => $channelId,
                            'stat_date' => $today,
                        ],
                        [
                            'user_id' => (string) $account->user_id,
                            'reach' => $views > 0 ? $views : 0,
                            'engagement' => $engagement > 0 ? $engagement : 0,
                            'followers' => $subscribers > 0 ? $subscribers : 0,
                            'likes' => 0,
                            'shares' => 0,
                            'comments' => 0,
                        ]
                    );

                    Log::info("✅ YouTube saved: $channelId");
                }

            } catch (\Throwable $e) {
                Log::error('YouTube failed: ' . $e->getMessage());
            }
        }
    }
}