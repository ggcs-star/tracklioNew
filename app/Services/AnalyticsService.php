<?php
// app/Services/AnalyticsService.php

namespace App\Services;

use App\Models\SocialAccount;
use App\Models\SocialHourlyStat;
use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AnalyticsService
{
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

        // Labels
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

        // ===== FACEBOOK =====
        if (($platformFilter === 'all' || $platformFilter === 'facebook') && isset($accounts['facebook'])) {
            $fbData = $this->getFacebookFromDB($userId, $pageFilter, $days);
            
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
            }
            
            $response['recentActivity'] = array_merge($response['recentActivity'], $fbData['recentActivity']);
        }

        // ===== INSTAGRAM =====
        if (($platformFilter === 'all' || $platformFilter === 'instagram') && isset($accounts['instagram'])) {
            $igData = $this->getInstagramFromDB($userId, $days, $instagramFilter);
            
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
        }

        // ===== YOUTUBE =====
        if (($platformFilter === 'all' || $platformFilter === 'youtube') && isset($accounts['youtube'])) {
            $ytData = $this->getYoutubeFromDB($userId, $days, $youtubeFilter);
            
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
            }
            
            $response['recentActivity'] = array_merge($response['recentActivity'], $ytData['recentActivity']);
        }

        // Sort Recent Activity
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
        $response['recentActivity'] = array_slice($uniqueActivity, 0, 15);

        return $response;
    }

    // ===== FACEBOOK - DB SE READ =====
    private function getFacebookFromDB(string $userId, string $pageFilter = 'all', int $days = 7): array
{
    $result = [
        'reach' => 0,
        'engagement' => 0,
        'followers' => 0,
        'pages' => [],
        'reachData' => array_fill(0, $days, 0),
        'engagementData' => array_fill(0, $days, 0),
        'recentActivity' => []
    ];

    $account = SocialAccount::where('user_id', $userId)
        ->where('platform', 'facebook')
        ->where('status', 'connected')
        ->first();

    if (!$account || empty($account->pages)) {
        return $result;
    }

    $isAllPages = ($pageFilter === 'all');
    $pagesToProcess = $account->pages;
    
    if (!$isAllPages) {
        $pagesToProcess = array_filter($account->pages, function($page) use ($pageFilter) {
            return ($page['page_id'] ?? null) === $pageFilter;
        });
    }

    foreach ($pagesToProcess as $page) {
        $pageId = $page['page_id'] ?? null;
        if (!$pageId) continue;

        $result['pages'][] = [
            'page_id' => $pageId,
            'page_name' => $page['page_name'] ?? 'Unknown Page',
            'page_access_token' => $page['page_access_token'] ?? null,
            'profile_name' => $page['profile_name'] ?? 'Facebook Profile'
        ];

        // Today ka latest data
        $latestStat = SocialHourlyStat::where('platform', 'facebook')
            ->where('page_id', $pageId)
            ->where('stat_date', now()->toDateString())
            ->orderBy('stat_hour', 'desc')
            ->first();

        if ($latestStat) {
            if ($isAllPages) {
                $result['followers'] += (int) $latestStat->followers;
                $result['reach'] += (int) $latestStat->reach;
                $result['engagement'] += (int) $latestStat->engagement;
            } else {
                $result['followers'] = (int) $latestStat->followers;
                $result['reach'] = (int) $latestStat->reach;
                $result['engagement'] = (int) $latestStat->engagement;
            }
        }

        // Chart data
        $startDate = Carbon::now()->subDays($days - 1)->toDateString();
        $endDate = Carbon::now()->toDateString();

        $stats = SocialHourlyStat::where('platform', 'facebook')
            ->where('page_id', $pageId)
            ->whereBetween('stat_date', [$startDate, $endDate])
            ->orderBy('stat_date', 'asc')
            ->get();

        if ($stats->isNotEmpty()) {
            $dailyStats = $stats->groupBy('stat_date');
            $currentDate = Carbon::now()->subDays($days - 1);
            
            for ($i = 0; $i < $days; $i++) {
                $dateKey = $currentDate->toDateString();
                if (isset($dailyStats[$dateKey])) {
                    $dayStats = $dailyStats[$dateKey];
                    $latestStats = $dayStats->last();
                    
                    if ($isAllPages) {
                        $result['reachData'][$i] += (int) $latestStats->reach;
                        $result['engagementData'][$i] += (int) $latestStats->engagement;
                    } else {
                        $result['reachData'][$i] = (int) $latestStats->reach;
                        $result['engagementData'][$i] = (int) $latestStats->engagement;
                    }
                }
                $currentDate->addDay();
            }
        }
    }

    // ===== RECENT ACTIVITY - USER KI POSTS =====
       // ===== RECENT ACTIVITY - USER KI PUBLISHED POSTS =====
    $dbPosts = Post::where('user_id', $userId)
        ->where('status', 'published')
        ->whereNotNull('facebook_post_id')
        ->orderBy('created_at', 'desc')
        ->limit(10)
        ->get();

    // Filter: platforms array mein 'facebook' ho
    $dbPosts = $dbPosts->filter(function($post) {
        return in_array('facebook', $post->platforms ?? []);
    });

    foreach ($dbPosts as $dbPost) {
        $result['recentActivity'][] = [
            'platform' => 'facebook',
            'platform_name' => 'Facebook',
            'id' => (string) $dbPost->_id,
            'title' => substr(strip_tags($dbPost->content ?? 'Facebook Post'), 0, 120),
            'description' => '📝 Posted on Facebook',
            'timestamp' => $dbPost->created_at->toIso8601String(),
            'created_at' => $dbPost->created_at->diffForHumans(),
            'media_url' => $dbPost->media_path ? asset('storage/' . $dbPost->media_path) : null,
            'icon' => 'fab fa-facebook-f',
            'icon_color' => 'blue',
            'bg_color' => 'bg-blue-100',
            'text_color' => 'text-blue-600',
            'insights' => []
        ];
    }

    return $result;
}
    // ===== INSTAGRAM - DB SE READ =====
    private function getInstagramFromDB(string $userId, int $days = 7, string $profileFilter = 'all'): array
    {
        $result = [
            'reach' => 0,
            'engagement' => 0,
            'followers' => 0,
            'reachData' => array_fill(0, $days, 0),
            'engagementData' => array_fill(0, $days, 0),
            'recentActivity' => []
        ];

        $accounts = SocialAccount::where('user_id', $userId)
            ->where('platform', 'instagram')
            ->where('status', 'connected')
            ->get();

        if ($accounts->isEmpty()) {
            return $result;
        }

        $isAllProfiles = ($profileFilter === 'all');

        foreach ($accounts as $account) {
            if (!$isAllProfiles && (string) $account->_id !== $profileFilter) {
                continue;
            }

            $businessId = $account->credentials['instagram_business_id'] ?? null;
            if (!$businessId) continue;

            $latestStat = SocialHourlyStat::where('platform', 'instagram')
                ->where('page_id', $businessId)
                ->where('stat_date', now()->toDateString())
                ->orderBy('stat_hour', 'desc')
                ->first();

            if ($latestStat) {
                if ($isAllProfiles) {
                    $result['followers'] += (int) $latestStat->followers;
                    $result['reach'] += (int) $latestStat->reach;
                    $result['engagement'] += (int) $latestStat->engagement;
                } else {
                    $result['followers'] = (int) $latestStat->followers;
                    $result['reach'] = (int) $latestStat->reach;
                    $result['engagement'] = (int) $latestStat->engagement;
                }
            }

            // Chart data
            $startDate = Carbon::now()->subDays($days - 1)->toDateString();
            $endDate = Carbon::now()->toDateString();

            $stats = SocialHourlyStat::where('platform', 'instagram')
                ->where('page_id', $businessId)
                ->whereBetween('stat_date', [$startDate, $endDate])
                ->orderBy('stat_date', 'asc')
                ->get();

            if ($stats->isNotEmpty()) {
                $dailyStats = $stats->groupBy('stat_date');
                $currentDate = Carbon::now()->subDays($days - 1);
                
                for ($i = 0; $i < $days; $i++) {
                    $dateKey = $currentDate->toDateString();
                    if (isset($dailyStats[$dateKey])) {
                        $dayStats = $dailyStats[$dateKey];
                        $latestStats = $dayStats->last();
                        
                        if ($isAllProfiles) {
                            $result['reachData'][$i] += (int) $latestStats->reach;
                            $result['engagementData'][$i] += (int) $latestStats->engagement;
                        } else {
                            $result['reachData'][$i] = (int) $latestStats->reach;
                            $result['engagementData'][$i] = (int) $latestStats->engagement;
                        }
                    }
                    $currentDate->addDay();
                }
            }
        }

            // ===== RECENT ACTIVITY - USER KI PUBLISHED POSTS =====
    $dbPosts = Post::where('user_id', $userId)
        ->where('status', 'published')
        ->whereNotNull('instagram_post_id')
        ->orderBy('created_at', 'desc')
        ->limit(10)
        ->get();

    // Filter: platforms array mein 'instagram' ho
    $dbPosts = $dbPosts->filter(function($post) {
        return in_array('instagram', $post->platforms ?? []);
    });

    foreach ($dbPosts as $dbPost) {
        $result['recentActivity'][] = [
            'platform' => 'instagram',
            'platform_name' => 'Instagram',
            'id' => (string) $dbPost->_id,
            'title' => substr(strip_tags($dbPost->content ?? 'Instagram Post'), 0, 120),
            'description' => '📷 Posted on Instagram',
            'timestamp' => $dbPost->created_at->toIso8601String(),
            'created_at' => $dbPost->created_at->diffForHumans(),
            'media_url' => $dbPost->media_path ? asset('storage/' . $dbPost->media_path) : null,
            'icon' => 'fab fa-instagram',
            'icon_color' => 'pink',
            'bg_color' => 'bg-pink-100',
            'text_color' => 'text-pink-600',
            'insights' => []
        ];
    }

        return $result;
    }

    // ===== YOUTUBE - DB SE READ =====
    private function getYoutubeFromDB(string $userId, int $days = 7, string $channelFilter = 'all'): array
    {
        $result = [
            'views' => 0,
            'engagement' => 0,
            'subscribers' => 0,
            'engagementData' => array_fill(0, $days, 0),
            'viewsData' => array_fill(0, $days, 0),
            'recentActivity' => []
        ];

        $accounts = SocialAccount::where('user_id', $userId)
            ->where('platform', 'youtube')
            ->where('status', 'connected')
            ->get();

        if ($accounts->isEmpty()) {
            return $result;
        }

        $isAllChannels = ($channelFilter === 'all');

        foreach ($accounts as $account) {
            if (!$isAllChannels && (string) $account->_id !== $channelFilter) {
                continue;
            }

            $channelId = $account->credentials['channel_id'] ?? null;
            if (!$channelId) continue;

            $latestStat = SocialHourlyStat::where('platform', 'youtube')
                ->where('page_id', $channelId)
                ->where('stat_date', now()->toDateString())
                ->orderBy('stat_hour', 'desc')
                ->first();

            if ($latestStat) {
                if ($isAllChannels) {
                    $result['subscribers'] += (int) $latestStat->followers;
                    $result['views'] += (int) $latestStat->reach;
                    $result['engagement'] += (int) $latestStat->engagement;
                } else {
                    $result['subscribers'] = (int) $latestStat->followers;
                    $result['views'] = (int) $latestStat->reach;
                    $result['engagement'] = (int) $latestStat->engagement;
                }
            }

            // Chart data
            $startDate = Carbon::now()->subDays($days - 1)->toDateString();
            $endDate = Carbon::now()->toDateString();

            $stats = SocialHourlyStat::where('platform', 'youtube')
                ->where('page_id', $channelId)
                ->whereBetween('stat_date', [$startDate, $endDate])
                ->orderBy('stat_date', 'asc')
                ->get();

            if ($stats->isNotEmpty()) {
                $dailyStats = $stats->groupBy('stat_date');
                $currentDate = Carbon::now()->subDays($days - 1);
                
                for ($i = 0; $i < $days; $i++) {
                    $dateKey = $currentDate->toDateString();
                    if (isset($dailyStats[$dateKey])) {
                        $dayStats = $dailyStats[$dateKey];
                        $latestStats = $dayStats->last();
                        
                        if ($isAllChannels) {
                            $result['viewsData'][$i] += (int) $latestStats->reach;
                            $result['engagementData'][$i] += (int) $latestStats->engagement;
                        } else {
                            $result['viewsData'][$i] = (int) $latestStats->reach;
                            $result['engagementData'][$i] = (int) $latestStats->engagement;
                        }
                    }
                    $currentDate->addDay();
                }
            }
        }

        // ===== RECENT ACTIVITY - YOUTUBE =====
        $dbPosts = Post::where('user_id', $userId)
            ->where('platforms', 'youtube')
            ->where('status', 'published')
            ->whereNotNull('youtube_video_id')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        foreach ($dbPosts as $dbPost) {
            $result['recentActivity'][] = [
                'platform' => 'youtube',
                'platform_name' => 'YouTube',
                'id' => (string) $dbPost->_id,
                'title' => substr(strip_tags($dbPost->content ?? 'YouTube Video'), 0, 120),
                'description' => '🎬 Posted on YouTube',
                'timestamp' => $dbPost->created_at->toIso8601String(),
                'created_at' => $dbPost->created_at->diffForHumans(),
                'media_url' => $dbPost->media_path ? asset('storage/' . $dbPost->media_path) : null,
                'icon' => 'fab fa-youtube',
                'icon_color' => 'red',
                'bg_color' => 'bg-red-100',
                'text_color' => 'text-red-600',
                'insights' => []
            ];
        }

        return $result;
    }
}