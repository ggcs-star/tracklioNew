<?php
// app/Http/Controllers/DashboardController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AnalyticsService;
use App\Models\SocialAccount;
use App\Models\ShortLink;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index()
    {
        try {
            $userId = (string) auth()->user()->_id;

            $facebookAccount = SocialAccount::forUser($userId)
                ->where('platform', 'facebook')
                ->where('status', 'connected')
                ->first();

            $instagramAccounts = SocialAccount::forUser($userId)
                ->where('platform', 'instagram')
                ->where('status', 'connected')
                ->get();

            $youtubeAccounts = SocialAccount::forUser($userId)
                ->where('platform', 'youtube')
                ->where('status', 'connected')
                ->get();
                
            $shortLinks = ShortLink::where('user_id', $userId)->get();
            $shortLinkStats = [
                'total_links' => $shortLinks->count(),
                'total_clicks' => $shortLinks->sum('click_count'),
                'most_clicked' => $shortLinks->max('click_count') ?? 0,
                'top_link' => $shortLinks->sortByDesc('click_count')->first(),
            ];
            
            return view('dashboard.index', [
                'shortLinkStats' => $shortLinkStats,
                'facebookPages' => $facebookAccount->pages ?? [],
                'instagramAccounts' => $instagramAccounts,
                'youtubeAccounts' => $youtubeAccounts,
                'hasFacebook' => !is_null($facebookAccount),
                'hasInstagram' => $instagramAccounts->count() > 0,
                'hasYoutube' => $youtubeAccounts->count() > 0,
            ]);

        } catch (\Throwable $e) {
            Log::error('Dashboard index error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
            
            return view('dashboard.index', [
                'facebookPages' => [],
                'instagramAccounts' => collect(),
                'youtubeAccounts' => collect(),
                'hasFacebook' => false,
                'hasInstagram' => false,
                'hasYoutube' => false,
            ]);
        }
    }

    public function live(Request $request)
    {
        try {
            $userId = (string) auth()->user()->_id;
            $platform = $request->get('platform', 'all');
            $pageId = $request->get('page', 'all');
            $instagramProfile = $request->get('instagram_profile', 'all');
            $youtubeChannel = $request->get('youtube_channel', 'all');
            $range = $request->get('range', '7');
            $metric = $request->get('metric', 'reach');
            $forceRefresh = $request->get('refresh', false);

            $days = $range === 'today' ? 1 : (int) $range;

            $cacheKey = "dashboard_live_{$userId}_{$platform}_{$pageId}_{$instagramProfile}_{$youtubeChannel}_{$days}_{$metric}";
            
            if ($forceRefresh) {
                Cache::forget($cacheKey);
            }
            
            $cachedData = Cache::get($cacheKey);
            
            if ($cachedData) {
                return response()->json($cachedData);
            }

            $analyticsService = new AnalyticsService();
            $data = $analyticsService->getAllAnalytics(
                $userId, 
                $platform, 
                $pageId, 
                $days, 
                $instagramProfile, 
                $youtubeChannel
            );

            $labelsCount = count($data['labels'] ?? []);
            $engagementData = array_pad($data['engagementData'] ?? [], $labelsCount, 0);
            $likesData = array_pad($data['likesData'] ?? [], $labelsCount, 0);
            $sharesData = array_pad($data['sharesData'] ?? [], $labelsCount, 0);
            $reachData = array_pad($data['reachData'] ?? [], $labelsCount, 0);

            $chartData = $reachData;
            if ($metric === 'likes') $chartData = $likesData;
            if ($metric === 'shares') $chartData = $sharesData;

            $shortLinkCacheKey = "short_links_{$userId}";
            $shortLinks = Cache::remember($shortLinkCacheKey, 3600, function () use ($userId) {
                return ShortLink::where('user_id', $userId)->get();
            });
            
            $shortLinkStats = [
                'total_links' => $shortLinks->count(),
                'total_clicks' => $shortLinks->sum('click_count'),
                'most_clicked' => $shortLinks->max('click_count') ?? 0,
            ];

            $response = [
                'success' => true,
                'totalReach' => $data['totalReach'] ?? 0,
                'totalEngagement' => $data['totalEngagement'] ?? 0,
                'totalClicks' => $data['totalClicks'] ?? 0,
                'followerGrowth' => $data['followerGrowth'] ?? 0,
                'labels' => $data['labels'] ?? [],
                'engagementData' => $engagementData,
                'likesData' => $likesData,
                'sharesData' => $sharesData,
                'reachData' => $reachData,
                'chartData' => $chartData,
                'platformReach' => $data['platformReach'] ?? [0,0,0],
                'platformEngagement' => $data['platformEngagement'] ?? [0,0,0],
                'pages' => $data['pages'] ?? [],
                'recentActivity' => $data['recentActivity'] ?? [],
                'shortLinkStats' => $shortLinkStats,
                'from_cache' => false,
                'from_db' => true
            ];

            Cache::put($cacheKey, $response, 3600);

            return response()->json($response);

        } catch (\Throwable $e) {
            Log::error('Dashboard live API error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
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
                'recentActivity' => [],
                'shortLinkStats' => ['total_links' => 0, 'total_clicks' => 0, 'most_clicked' => 0],
                'from_cache' => false,
                'from_db' => false
            ], 200);
        }
    }
}