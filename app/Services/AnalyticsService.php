<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\SocialAccount;
use App\Models\Post;
use Carbon\Carbon;

class AnalyticsService
{
    protected string $graphVersion = 'v24.0';

    public function getAllAnalytics(string $userId, string $platformFilter = 'all', string $pageFilter = 'all', int $days = 7, string $instagramFilter = 'all', string $youtubeFilter = 'all'): array    
    {
        $response = [
            'totalReach' => 0,
            'totalEngagement' => 0,
            'totalClicks' => 0,
            'followerGrowth' => 0,
            'labels' => [],
            'engagementData' => [],
            'likesData' => [],
            'sharesData' => [],
            'reachData' => [],
            'platformReach' => [0, 0, 0],
            'platformEngagement' => [0, 0, 0],
            'pages' => [],
            'recentActivity' => []
        ];

        for ($i = $days - 1; $i >= 0; $i--) {
            $response['labels'][] = Carbon::now()->subDays($i)->format('d M');
            $response['engagementData'][] = 0;
            $response['likesData'][] = 0;
            $response['sharesData'][] = 0;
            $response['reachData'][] = 0;
        }

        $accounts = SocialAccount::where('user_id', $userId)
            ->where('status', 'connected')
            ->get()
            ->keyBy('platform');

        if (($platformFilter === 'all' || $platformFilter === 'facebook') && isset($accounts['facebook'])) {
            try {
                $fbData = $this->fetchFacebookAnalytics($accounts['facebook'], $pageFilter, $days, $userId);
                
                $response['totalReach'] += $fbData['reach'];
                $response['totalEngagement'] += $fbData['engagement'];
                $response['followerGrowth'] += $fbData['followers'];
                $response['platformReach'][0] = $fbData['reach'];
                $response['platformEngagement'][0] = $fbData['engagement'];
                $response['pages'] = $fbData['pages'];
                
                for ($i = 0; $i < $days; $i++) {
                    if (isset($fbData['reachData'][$i])) {
                        $response['reachData'][$i] += $fbData['reachData'][$i];
                    }
                    if (isset($fbData['engagementData'][$i])) {
                        $response['engagementData'][$i] += $fbData['engagementData'][$i];
                    }
                    if (isset($fbData['likesData'][$i])) {
                        $response['likesData'][$i] += $fbData['likesData'][$i];
                    }
                    if (isset($fbData['sharesData'][$i])) {
                        $response['sharesData'][$i] += $fbData['sharesData'][$i];
                    }
                }
                
                $response['recentActivity'] = array_merge($response['recentActivity'], $fbData['recentActivity']);
            } catch (\Throwable $e) {
                Log::error('Facebook failed', ['error' => $e->getMessage()]);
            }
        }

        if (($platformFilter === 'all' || $platformFilter === 'instagram') && isset($accounts['instagram'])) {
            try {
                $igData = $this->fetchInstagramAnalytics($accounts['instagram'], $days, $instagramFilter, $userId);
                
                $response['totalReach'] += $igData['reach'];
                $response['totalEngagement'] += $igData['engagement'];
                $response['platformReach'][1] = $igData['reach'];
                $response['platformEngagement'][1] = $igData['engagement'];
                $response['followerGrowth'] += $igData['followers'];
                
                for ($i = 0; $i < $days; $i++) {
                    if (isset($igData['reachData'][$i])) {
                        $response['reachData'][$i] += $igData['reachData'][$i];
                    }
                    if (isset($igData['engagementData'][$i])) {
                        $response['engagementData'][$i] += $igData['engagementData'][$i];
                    }
                }
                
                $response['recentActivity'] = array_merge($response['recentActivity'], $igData['recentActivity']);
            } catch (\Throwable $e) {
                Log::error('Instagram failed', ['error' => $e->getMessage()]);
            }
        }

        if (($platformFilter === 'all' || $platformFilter === 'youtube') && isset($accounts['youtube'])) {
            try {
                $ytData = $this->fetchYoutubeAnalytics($accounts['youtube'], $days, $youtubeFilter, $userId);
                
                $response['totalReach'] += $ytData['views'];
                $response['totalEngagement'] += $ytData['engagement'];
                $response['followerGrowth'] += $ytData['subscribers'];
                $response['platformReach'][2] = $ytData['views'];
                $response['platformEngagement'][2] = $ytData['engagement'];
                
                for ($i = 0; $i < $days; $i++) {
                    if (isset($ytData['viewsData'][$i])) {
                        $response['reachData'][$i] += $ytData['viewsData'][$i];
                    }
                    if (isset($ytData['engagementData'][$i])) {
                        $response['engagementData'][$i] += $ytData['engagementData'][$i];
                    }
                    if (isset($ytData['likesData'][$i])) {
                        $response['likesData'][$i] += $ytData['likesData'][$i];
                    }
                }
                
                $response['recentActivity'] = array_merge($response['recentActivity'], $ytData['recentActivity']);
            } catch (\Throwable $e) {
                Log::error('YouTube failed', ['error' => $e->getMessage()]);
            }
        }

        usort($response['recentActivity'], function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        $seenIds = [];
        $uniqueActivity = [];
        foreach ($response['recentActivity'] as $activity) {
            if (!isset($activity['id'])) {
                $uniqueActivity[] = $activity;
                continue;
            }
            $key = $activity['platform'] . '_' . $activity['id'];
            if (!in_array($key, $seenIds)) {
                $seenIds[] = $key;
                $uniqueActivity[] = $activity;
            }
        }
        $response['recentActivity'] = $uniqueActivity;
        $response['recentActivity'] = array_slice($response['recentActivity'], 0, 15);

        return $response;
    }

    private function fetchFacebookAnalytics($account, string $pageFilter = 'all', int $days = 7, string $userId = ''): array    
    {
        $result = [
            'reach' => 0,
            'engagement' => 0,
            'followers' => 0,
            'pages' => $account->pages ?? [],
            'reachData' => array_fill(0, $days, 0),
            'engagementData' => array_fill(0, $days, 0),
            'likesData' => array_fill(0, $days, 0),
            'sharesData' => array_fill(0, $days, 0),
            'recentActivity' => []
        ];

        if (empty($account->pages)) {
            return $result;
        }

        $since = Carbon::now()->subDays($days - 1)->startOfDay();
        $until = Carbon::now()->endOfDay();

        foreach ($account->pages as $page) {
            $pageId = $page['page_id'] ?? null;
            $token = $page['page_access_token'] ?? $account->credentials['page_access_token'] ?? $account->credentials['access_token'] ?? null;

            if (!$pageId || !$token) {
                continue;
            }

            if ($pageFilter !== 'all' && $pageId !== $pageFilter) {
                continue;
            }

            $followersRes = Http::timeout(10)->get("https://graph.facebook.com/{$this->graphVersion}/{$pageId}", [
                'fields' => 'followers_count,name,fan_count',
                'access_token' => $token
            ])->json();

            if (!isset($followersRes['error'])) {
                $result['followers'] += $followersRes['followers_count'] ?? $followersRes['fan_count'] ?? 0;
            }

            $viewsRes = Http::timeout(10)->get("https://graph.facebook.com/{$this->graphVersion}/{$pageId}/insights", [
                'metric' => 'page_impressions_unique',
                'period' => 'day',
                'since' => $since->toDateString(),
                'until' => $until->toDateString(),
                'access_token' => $token
            ])->json();
            
            $values = $viewsRes['data'][0]['values'] ?? [];
            $totalReach = 0;
            
            foreach ($values as $idx => $row) {
                $val = (int) ($row['value'] ?? 0);
                $totalReach += $val;
                if ($idx < $days) {
                    $result['reachData'][$idx] += $val;
                }
            }
            $result['reach'] += $totalReach;

            $dbPosts = Post::where('user_id', $userId)
                ->where('platforms', 'facebook')
                ->where('status', 'published')
                ->whereNotNull('facebook_post_id')
                ->orderBy('created_at', 'desc')
                ->limit(15)
                ->get();
            
            foreach ($dbPosts as $dbPost) {
                $reactions = 0;
                $comments = 0;
                $shares = 0;
                
                if ($dbPost->facebook_post_id && $token) {
                    try {
                        $fbPost = Http::timeout(5)->get("https://graph.facebook.com/{$this->graphVersion}/{$dbPost->facebook_post_id}", [
                            'fields' => 'reactions.summary(true),comments.summary(true),shares',
                            'access_token' => $token
                        ])->json();
                        
                        if (!isset($fbPost['error'])) {
                            $reactions = $fbPost['reactions']['summary']['total_count'] ?? 0;
                            $comments = $fbPost['comments']['summary']['total_count'] ?? 0;
                            $shares = $fbPost['shares']['count'] ?? 0;
                            
                            $postEngagement = $reactions + $comments;
                            $result['engagement'] += $postEngagement;
                            $result['engagementData'][$days - 1] += $postEngagement;
                        } else {
                            Log::warning('FB API Error for post ' . $dbPost->facebook_post_id . ': ' . ($fbPost['error']['message'] ?? 'Unknown'));
                        }
                    } catch (\Throwable $e) {
                        Log::error('FB Error for post ' . $dbPost->facebook_post_id . ': ' . $e->getMessage());
                    }
                }
                
                $result['recentActivity'][] = [
                    'platform' => 'facebook',
                    'platform_name' => 'Facebook',
                    'id' => (string) $dbPost->_id,
                    'title' => substr(strip_tags($dbPost->content ?? 'Facebook Post'), 0, 120),
                    'description' => '👍 ' . number_format($reactions) . ' likes | 💬 ' . number_format($comments) . ' comments',
                    'timestamp' => $dbPost->created_at->toIso8601String(),
                    'created_at' => $dbPost->created_at->diffForHumans(),
                    'media_url' => $dbPost->media_path ? asset('storage/' . $dbPost->media_path) : null,
                    'icon' => 'fab fa-facebook-f',
                    'icon_color' => 'blue',
                    'bg_color' => 'bg-blue-100',
                    'text_color' => 'text-blue-600',
                    'insights' => [
                        'likes' => $reactions,
                        'comments' => $comments,
                        'shares' => $shares,
                        'engagement' => $reactions + $comments
                    ]
                ];
            }
        }

        return $result;
    }

    private function fetchInstagramAnalytics($account, int $days = 7, string $profileFilter = 'all', string $userId = ''): array
    {
        $result = [
            'reach' => 0,
            'engagement' => 0,
            'followers' => 0,
            'reachData' => array_fill(0, $days, 0),
            'engagementData' => array_fill(0, $days, 0),
            'recentActivity' => []
        ];

        $instagramAccounts = SocialAccount::where('user_id', $account->user_id)
            ->where('platform', 'instagram')
            ->where('status', 'connected')
            ->get();

        if ($instagramAccounts->isEmpty()) {
            return $result;
        }

        $since = Carbon::now()->subDays($days - 1)->startOfDay();
        $until = Carbon::now()->endOfDay();

        foreach ($instagramAccounts as $igAccount) {
            if ($profileFilter !== 'all' && (string) $igAccount->_id !== $profileFilter) {
                continue;
            }
            
            $businessId = $igAccount->credentials['instagram_business_id'] ?? null;
            $token = $igAccount->credentials['page_access_token'] ?? $igAccount->credentials['access_token'] ?? null;

            if (!$businessId || !$token) {
                continue;
            }

            try {
                $profile = Http::timeout(10)->get("https://graph.facebook.com/{$this->graphVersion}/{$businessId}", [
                    'fields' => 'followers_count,username',
                    'access_token' => $token
                ])->json();

                if (!isset($profile['error'])) {
                    $result['followers'] += $profile['followers_count'] ?? 0;
                }

                $insights = Http::timeout(15)->get(
                    "https://graph.facebook.com/{$this->graphVersion}/{$businessId}/insights",
                    [
                        'metric' => 'reach',
                        'period' => 'day',
                        'since' => $since->toDateString(),
                        'until' => $until->toDateString(),
                        'access_token' => $token
                    ]
                )->json();
                
                if (!isset($insights['error']) && !empty($insights['data'])) {
                    foreach ($insights['data'] as $metric) {
                        $values = $metric['values'] ?? [];
                        $metricName = $metric['name'] ?? '';
                        $valuesReversed = array_reverse($values);
                        
                        if ($metricName === 'reach') {
                            foreach ($valuesReversed as $idx => $row) {
                                $val = (int) ($row['value'] ?? 0);
                                if ($idx < $days) {
                                    $result['reachData'][$idx] += $val;
                                    $result['reach'] += $val;
                                }
                            }
                        }
                    }
                }

                $dbPosts = Post::where('user_id', $userId)
                    ->where('platforms', 'instagram')
                    ->where('status', 'published')
                    ->whereNotNull('instagram_post_id')
                    ->orderBy('created_at', 'desc')
                    ->limit(15)
                    ->get();

                foreach ($dbPosts as $dbPost) {
                    $likes = 0;
                    $comments = 0;
                    $mediaType = $dbPost->ig_post_type ?? 'post';
                    $typeIcon = '📷';
                    if ($mediaType === 'reel') $typeIcon = '🎥';
                    if ($mediaType === 'story') $typeIcon = '📸';
                    
                    if ($dbPost->instagram_post_id && $token) {
                        try {
                            $igPost = Http::timeout(5)->get("https://graph.facebook.com/{$this->graphVersion}/{$dbPost->instagram_post_id}", [
                                'fields' => 'like_count,comments_count',
                                'access_token' => $token
                            ])->json();
                            
                            if (!isset($igPost['error'])) {
                                $likes = $igPost['like_count'] ?? 0;
                                $comments = $igPost['comments_count'] ?? 0;
                                
                                $postEngagement = $likes + $comments;
                                $result['engagement'] += $postEngagement;
                                $result['engagementData'][$days - 1] += $postEngagement;
                            } else {
                                Log::warning('Instagram API Error for post ' . $dbPost->instagram_post_id . ': ' . ($igPost['error']['message'] ?? 'Unknown'));
                            }
                        } catch (\Throwable $e) {
                            Log::error('Instagram Error for post ' . $dbPost->instagram_post_id . ': ' . $e->getMessage());
                        }
                    }
                    
                    $result['recentActivity'][] = [
                        'platform' => 'instagram',
                        'platform_name' => 'Instagram',
                        'id' => (string) $dbPost->_id,
                        'title' => substr(strip_tags($dbPost->content ?? 'Instagram Post'), 0, 120),
                        'description' => $typeIcon . ' ❤️ ' . number_format($likes) . ' likes | 💬 ' . number_format($comments) . ' comments',
                        'timestamp' => $dbPost->created_at->toIso8601String(),
                        'created_at' => $dbPost->created_at->diffForHumans(),
                        'media_url' => $dbPost->media_path ? asset('storage/' . $dbPost->media_path) : null,
                        'icon' => 'fab fa-instagram',
                        'icon_color' => 'pink',
                        'bg_color' => 'bg-pink-100',
                        'text_color' => 'text-pink-600',
                        'insights' => [
                            'likes' => $likes,
                            'comments' => $comments,
                            'engagement' => $likes + $comments,
                            'media_type' => $mediaType
                        ]
                    ];
                }
            } catch (\Throwable $e) {
                Log::error('Instagram fetch failed: ' . $e->getMessage());
            }
        }

        return $result;
    }

    private function fetchYoutubeAnalytics($account, int $days = 7, string $channelFilter = 'all', string $userId = ''): array
    {
        $result = [
            'views' => 0,
            'engagement' => 0,
            'subscribers' => 0,
            'likes' => 0,
            'comments' => 0,
            'engagementData' => array_fill(0, $days, 0),
            'likesData' => array_fill(0, $days, 0),
            'viewsData' => array_fill(0, $days, 0),
            'recentActivity' => []
        ];

        $youtubeAccounts = SocialAccount::where('user_id', $account->user_id)
            ->where('platform', 'youtube')
            ->where('status', 'connected')
            ->get();

        if ($youtubeAccounts->isEmpty()) {
            return $result;
        }

        foreach ($youtubeAccounts as $ytAccount) {
            if ($channelFilter !== 'all' && (string) $ytAccount->_id !== $channelFilter) {
                continue;
            }
            
            $creds = $ytAccount->credentials;

            if (empty($creds['refresh_token'])) {
                continue;
            }

            try {
                $tokenRes = Http::timeout(10)->asForm()->post(
                    'https://oauth2.googleapis.com/token',
                    [
                        'client_id' => env('YOUTUBE_CLIENT_ID'),
                        'client_secret' => env('YOUTUBE_CLIENT_SECRET'),
                        'refresh_token' => $creds['refresh_token'],
                        'grant_type' => 'refresh_token',
                    ]
                );

                if (!$tokenRes->successful()) {
                    continue;
                }

                $accessToken = $tokenRes->json('access_token');

                $channel = Http::timeout(10)->withToken($accessToken)->get(
                    'https://www.googleapis.com/youtube/v3/channels',
                    ['part' => 'statistics', 'mine' => 'true']
                )->json();

                $stats = $channel['items'][0]['statistics'] ?? [];
                $result['views'] += (int) ($stats['viewCount'] ?? 0);
                $result['subscribers'] += (int) ($stats['subscriberCount'] ?? 0);

                $dbPosts = Post::where('user_id', $userId)
                    ->where('platforms', 'youtube')
                    ->where('status', 'published')
                    ->whereNotNull('youtube_video_id')
                    ->orderBy('created_at', 'desc')
                    ->limit(15)
                    ->get();

                foreach ($dbPosts as $dbPost) {
                    $views = 0;
                    $likes = 0;
                    $comments = 0;
                    
                    if ($dbPost->youtube_video_id) {
                        try {
                            $ytVideo = Http::timeout(5)->withToken($accessToken)->get(
                                'https://www.googleapis.com/youtube/v3/videos',
                                [
                                    'part' => 'statistics',
                                    'id' => $dbPost->youtube_video_id
                                ]
                            )->json();
                            
                            $stats = $ytVideo['items'][0]['statistics'] ?? [];
                            $views = (int) ($stats['viewCount'] ?? 0);
                            $likes = (int) ($stats['likeCount'] ?? 0);
                            $comments = (int) ($stats['commentCount'] ?? 0);
                            
                            $postEngagement = $likes + $comments;
                            $result['engagement'] += $postEngagement;
                            $result['engagementData'][$days - 1] += $postEngagement;
                        } catch (\Throwable $e) {
                            Log::error('YouTube video fetch failed: ' . $e->getMessage());
                        }
                    }
                    
                    $result['recentActivity'][] = [
                        'platform' => 'youtube',
                        'platform_name' => 'YouTube',
                        'id' => (string) $dbPost->_id,
                        'title' => substr(strip_tags($dbPost->content ?? 'YouTube Video'), 0, 100),
                        'description' => '🎬 ' . number_format($views) . ' views | ❤️ ' . number_format($likes) . ' likes | 💬 ' . number_format($comments) . ' comments',
                        'timestamp' => $dbPost->created_at->toIso8601String(),
                        'created_at' => $dbPost->created_at->diffForHumans(),
                        'media_url' => $dbPost->media_path ? asset('storage/' . $dbPost->media_path) : null,
                        'icon' => 'fab fa-youtube',
                        'icon_color' => 'red',
                        'bg_color' => 'bg-red-100',
                        'text_color' => 'text-red-600',
                        'insights' => [
                            'views' => $views,
                            'likes' => $likes,
                            'comments' => $comments,
                            'engagement' => $likes + $comments
                        ]
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('YouTube fetch failed: ' . $e->getMessage());
            }
        }

        return $result;
    }
}