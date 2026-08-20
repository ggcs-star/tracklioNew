<?php
// app/Jobs/FetchFacebookHourlyStats.php

namespace App\Jobs;

use App\Models\SocialAccount;
use App\Models\SocialHourlyStat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class FetchFacebookHourlyStats implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $fbVersion = 'v26.0';

    public function handle()
    {
        $now = now();
        $date = $now->toDateString();
        $hour = $now->hour;

        // ========== 1. FACEBOOK ==========
                $fbAccounts = SocialAccount::where('platform', 'facebook')
            ->where('status', 'connected')
            ->get();

        foreach ($fbAccounts as $account) {
            if (empty($account->pages)) continue;

            foreach ($account->pages as $page) {
                $pageId = $page['page_id'] ?? null;
                
                $token = $page['page_access_token'] ?? $account->credentials['user_access_token'] ?? null;
                
                if (empty($token)) {
                    $token = $page['access_token'] ?? null;
                }
                
                if (empty($token)) {
                    $token = $account->credentials['access_token'] ?? null;
                }

                if (!$pageId || !$token) continue;

                try {
                    $info = Http::timeout(30)->get("https://graph.facebook.com/v19.0/{$pageId}", [
                        'fields' => 'followers_count,fan_count',
                        'access_token' => $token
                    ])->json();

                    $followers = (int) ($info['followers_count'] ?? $info['fan_count'] ?? 0);

                    $totalReach = 0;
                    $totalEngagement = 0;

                    for ($i = 29; $i >= 0; $i--) {
                        $targetDate = Carbon::now()->subDays($i)->toDateString();

                        // REACH: Using page_impressions for better compatibility
                        $reachRes = Http::timeout(30)->get("https://graph.facebook.com/v19.0/{$pageId}/insights", [
                            'metric' => 'page_impressions',
                            'period' => 'day',
                            'since' => $targetDate,
                            'until' => $targetDate,
                            'access_token' => $token
                        ])->json();

                        if (!isset($reachRes['error']) && !empty($reachRes['data'])) {
                            $val = $reachRes['data'][0]['values'][0]['value'] ?? 0;
                            $totalReach += (int) $val;
                        }

                        // ENGAGEMENT
                        $engRes = Http::timeout(30)->get("https://graph.facebook.com/v19.0/{$pageId}/insights", [
                            'metric' => 'page_engaged_users',
                            'period' => 'day',
                            'since' => $targetDate,
                            'until' => $targetDate,
                            'access_token' => $token
                        ])->json();

                        if (!isset($engRes['error']) && !empty($engRes['data'])) {
                            $val = $engRes['data'][0]['values'][0]['value'] ?? 0;
                            $totalEngagement += (int) $val;
                        }
                    }

                    SocialHourlyStat::updateOrCreate(
                        [
                            'platform' => 'facebook',
                            'page_id' => $pageId,
                            'stat_date' => $date,
                            'stat_hour' => $hour,
                        ],
                        [
                            'user_id' => (string) $account->user_id,
                            'reach' => (int) $totalReach,
                            'engagement' => (int) $totalEngagement,
                            'followers' => (int) $followers,
                        ]
                    );

                } catch (\Throwable $e) {
                    \Log::error('Facebook fetch failed: ' . $e->getMessage());
                }
            }
        }
        // ========== 2. INSTAGRAM ==========
        $igAccounts = SocialAccount::where('platform', 'instagram')
            ->where('status', 'connected')
            ->get();

        foreach ($igAccounts as $account) {
            $businessId = $account->credentials['instagram_business_id'] ?? null;
            $token = $account->credentials['page_access_token'] ?? $account->credentials['access_token'] ?? null;

            if (!$businessId || !$token) continue;

            try {
                $profile = Http::timeout(30)->get("https://graph.facebook.com/{$this->fbVersion}/{$businessId}", [
                    'fields' => 'followers_count,username',
                    'access_token' => $token
                ])->json();

                if (isset($profile['error'])) continue;

                $followers = (int) ($profile['followers_count'] ?? 0);

                $endDate = now()->toDateString();
                $startDate = now()->subDays(7)->toDateString();

                $reachRes = Http::timeout(30)->get(
                    "https://graph.facebook.com/{$this->fbVersion}/{$businessId}/insights",
                    [
                        'metric' => 'reach',
                        'period' => 'day',
                        'since' => $startDate,
                        'until' => $endDate,
                        'access_token' => $token
                    ]
                )->json();

                $reach = 0;
                if (!isset($reachRes['error']) && !empty($reachRes['data'])) {
                    $values = $reachRes['data'][0]['values'] ?? [];
                    foreach ($values as $v) {
                        $reach += (int) ($v['value'] ?? 0);
                    }
                }

                $engRes = Http::timeout(30)->get(
                    "https://graph.facebook.com/{$this->fbVersion}/{$businessId}/insights",
                    [
                        'metric' => 'likes,comments,shares,saves',
                        'period' => 'day',
                        'since' => $startDate,
                        'until' => $endDate,
                        'access_token' => $token
                    ]
                )->json();

                $engagement = 0;
                if (!isset($engRes['error']) && !empty($engRes['data'])) {
                    foreach ($engRes['data'] as $metric) {
                        $values = $metric['values'] ?? [];
                        foreach ($values as $v) {
                            $engagement += (int) ($v['value'] ?? 0);
                        }
                    }
                }

                SocialHourlyStat::updateOrCreate(
                    [
                        'platform' => 'instagram',
                        'page_id' => $businessId,
                        'stat_date' => $date,
                        'stat_hour' => $hour,
                    ],
                    [
                        'user_id' => (string) $account->user_id,
                        'reach' => (int) $reach,
                        'engagement' => (int) $engagement,
                        'followers' => (int) $followers,
                    ]
                );

            } catch (\Throwable $e) {
                \Log::error('Instagram fetch failed: ' . $e->getMessage());
            }
        }

        // ========== 3. YOUTUBE ==========
        $ytAccounts = SocialAccount::where('platform', 'youtube')
            ->where('status', 'connected')
            ->get();

        foreach ($ytAccounts as $account) {
            $creds = $account->credentials;

            if (empty($creds['refresh_token']) || empty($creds['channel_id'])) continue;

            try {
                $tokenRes = Http::timeout(30)->asForm()->post('https://oauth2.googleapis.com/token', [
                    'client_id' => env('YOUTUBE_CLIENT_ID'),
                    'client_secret' => env('YOUTUBE_CLIENT_SECRET'),
                    'refresh_token' => $creds['refresh_token'],
                    'grant_type' => 'refresh_token',
                ]);

                if (!$tokenRes->successful()) continue;

                $accessToken = $tokenRes->json('access_token');
                $channelId = $creds['channel_id'];

                $channel = Http::timeout(30)->withToken($accessToken)->get('https://www.googleapis.com/youtube/v3/channels', [
                    'part' => 'statistics',
                    'id' => $channelId
                ])->json();

                $stats = $channel['items'][0]['statistics'] ?? [];
                $subscribers = (int) ($stats['subscriberCount'] ?? 0);
                $views = (int) ($stats['viewCount'] ?? 0);
                $likes = (int) ($stats['likeCount'] ?? 0);
                $comments = (int) ($stats['commentCount'] ?? 0);
                $engagement = $likes + $comments;

                SocialHourlyStat::updateOrCreate(
                    [
                        'platform' => 'youtube',
                        'page_id' => $channelId,
                        'stat_date' => $date,
                        'stat_hour' => $hour,
                    ],
                    [
                        'user_id' => (string) $account->user_id,
                        'reach' => (int) $views,
                        'engagement' => (int) $engagement,
                        'followers' => (int) $subscribers,
                    ]
                );

            } catch (\Throwable $e) {
                \Log::error('YouTube fetch failed: ' . $e->getMessage());
            }
        }
    }
}