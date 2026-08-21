<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Post\PostCrudService;
use Illuminate\Support\Facades\Http;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    protected PostCrudService $posts;

    public function __construct(PostCrudService $posts)
    {
        $this->posts = $posts;
    }

    public function create()
    {
        return $this->posts->create();
    }

     public function store(Request $request)
    {
        // 🔥 CROPPED IMAGES HANDLE
        $croppedData = [];
        $tempFiles = [];
        
        if ($request->has('cropped_images')) {
            $croppedData = json_decode($request->cropped_images, true);
            
            foreach ($croppedData as $index => $crop) {
                // 🔥 ORIGINAL skip karo, sirf crop wali images save karo
                if ($crop['ratio'] !== 'original') {
                    try {
                        // Base64 se image save karo
                        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#', '', $crop['data']));
                        $filename = 'cropped_' . time() . '_' . $index . '.jpg';
                        $path = 'posts/' . $filename;
                        Storage::disk('public')->put($path, $imageData);
                        $tempFiles[$index] = $path;
                    } catch (\Exception $e) {
                        \Log::error('Crop save failed: ' . $e->getMessage());
                    }
                }
            }
        }
        
        // Store the post
        $result = $this->posts->store($request);
        
        // Agar post create ho gayi aur cropped images hain
        if ($result instanceof \App\Models\Post && !empty($tempFiles)) {
            $post = $result;
            $mediaPaths = $post->media_paths ?? [];
            
            // 🔥 Agar media_paths empty hai toh media_path se array banao
            if (empty($mediaPaths) && $post->media_path) {
                $mediaPaths = [$post->media_path];
            }
            
            foreach ($tempFiles as $index => $path) {
                $mediaPaths[$index] = $path;
            }
            
            $post->media_paths = $mediaPaths;
            $post->media_path = $mediaPaths[0] ?? null;
            $post->save();
        }
        
        return $result;
    }

        public function show($id)
    {
        $post = \App\Models\Post::find($id);

        if (!$post) {
            $post = \App\Models\Post::where('facebook_post_id', $id)
                ->orWhere('instagram_post_id', $id)
                ->orWhere('youtube_video_id', $id)
                ->first();
        }

        if (!$post) {
            abort(404, 'Post not found');
        }

        // Default values
        $reactions = 0;
        $comments = 0;
        $shares = 0;
        $views = 0;
        
        $platform = $post->platforms[0] ?? 'facebook';

        // ===== 1. DB se Data check karo =====
        $dbStats = \App\Models\SocialHourlyStat::where('post_id', (string) $post->_id)
            ->orderBy('stat_date', 'desc')
            ->first();

        if ($dbStats) {
            $reactions = (int) ($dbStats->likes ?? 0);
            $comments = (int) ($dbStats->comments ?? 0);
            $shares = (int) ($dbStats->shares ?? 0);
            $views = (int) ($dbStats->reach ?? 0);
            
            if ($platform === 'youtube') {
                $views = (int) ($dbStats->reach ?? 0);
            }
        }

        // ===== 2. Agar DB mein 0 hai, toh LIVE API call karo =====
        // Yeh check ensure karega ki agar DB mein 0 hai tabhi API call ho
        if ($reactions == 0 && $comments == 0 && $shares == 0) {
            if ($platform === 'facebook' && $post->facebook_post_id) {
                try {
                    $account = \App\Models\SocialAccount::where('user_id', (string) $post->user_id)
                        ->where('platform', 'facebook')
                        ->first();
                    
                    $token = null;
                    if ($account && !empty($account->pages)) {
                        foreach ($account->pages as $page) {
                            if ($page['page_id'] == $post->facebook_page_id) {
                                $token = $page['page_access_token'];
                                break;
                            }
                        }
                    }
                    
                    // Agar token nahi mila toh alternate token use karo
                    if (!$token && $account) {
                        $token = $account->credentials['user_access_token'] ?? null;
                    }
                    
                    if ($token) {
                        $fbPost = Http::timeout(10)->get("https://graph.facebook.com/v20.0/{$post->facebook_post_id}", [
                            'fields' => 'reactions.summary(true),comments.summary(true),shares',
                            'access_token' => $token
                        ])->json();
                        
                        $reactions = $fbPost['reactions']['summary']['total_count'] ?? 0;
                        $comments = $fbPost['comments']['summary']['total_count'] ?? 0;
                        $shares = $fbPost['shares']['count'] ?? 0;
                    }
                } catch (\Throwable $e) {
                    \Log::error('Facebook post fetch failed: ' . $e->getMessage());
                }
            }
            
            if ($platform === 'instagram' && $post->instagram_post_id) {
                try {
                    $token = null;
                    $instagramAccount = null;
                    
                    if ($post->instagram_profile_id) {
                        $instagramAccount = \App\Models\SocialAccount::where('user_id', (string) $post->user_id)
                            ->where('platform', 'instagram')
                            ->where('_id', $post->instagram_profile_id)
                            ->first();
                    }
                    
                    if (!$instagramAccount) {
                        $instagramAccount = \App\Models\SocialAccount::where('user_id', (string) $post->user_id)
                            ->where('platform', 'instagram')
                            ->first();
                    }
                    
                    if ($instagramAccount) {
                        $token = $instagramAccount->credentials['page_access_token'] ?? 
                                $instagramAccount->credentials['access_token'] ?? null;
                    }
                    
                    if ($token) {
                        $igPost = Http::timeout(10)->get("https://graph.facebook.com/v20.0/{$post->instagram_post_id}", [
                            'fields' => 'like_count,comments_count',
                            'access_token' => $token
                        ])->json();
                        
                        if (!isset($igPost['error'])) {
                            $reactions = $igPost['like_count'] ?? 0;
                            $comments = $igPost['comments_count'] ?? 0;
                        } else {
                            \Log::warning('Instagram API error for post: ' . $post->instagram_post_id, [
                                'error' => $igPost['error']['message'] ?? 'Unknown'
                            ]);
                        }
                        $shares = 0;
                    }
                } catch (\Throwable $e) {
                    \Log::error('Instagram post fetch failed: ' . $e->getMessage());
                }
            }
            
            if ($platform === 'youtube' && $post->youtube_video_id) {
                try {
                    $youtubeAccount = \App\Models\SocialAccount::where('user_id', (string) $post->user_id)
                        ->where('platform', 'youtube')
                        ->first();
                    
                    if ($post->youtube_account_id) {
                        $youtubeAccount = \App\Models\SocialAccount::where('user_id', (string) $post->user_id)
                            ->where('platform', 'youtube')
                            ->where('_id', $post->youtube_account_id)
                            ->first();
                    }
                    
                    if ($youtubeAccount) {
                        $creds = $youtubeAccount->credentials;
                        if (!empty($creds['refresh_token'])) {
                            $tokenRes = Http::timeout(10)->asForm()->post(
                                'https://oauth2.googleapis.com/token',
                                [
                                    'client_id' => env('YOUTUBE_CLIENT_ID'),
                                    'client_secret' => env('YOUTUBE_CLIENT_SECRET'),
                                    'refresh_token' => $creds['refresh_token'],
                                    'grant_type' => 'refresh_token',
                                ]
                            );
                            
                            if ($tokenRes->successful()) {
                                $accessToken = $tokenRes->json('access_token');
                                $ytVideo = Http::timeout(10)->withToken($accessToken)->get(
                                    "https://www.googleapis.com/youtube/v3/videos",
                                    [
                                        'part' => 'statistics',
                                        'id' => $post->youtube_video_id
                                    ]
                                )->json();
                                
                                $stats = $ytVideo['items'][0]['statistics'] ?? [];
                                $reactions = (int) ($stats['likeCount'] ?? 0);
                                $comments = (int) ($stats['commentCount'] ?? 0);
                                $shares = 0;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    \Log::error('YouTube post fetch failed: ' . $e->getMessage());
                }
            }
        }

        $platformIcons = [
            'facebook' => ['icon' => 'fab fa-facebook-f', 'color' => 'bg-blue-600', 'text' => 'text-blue-600'],
            'instagram' => ['icon' => 'fab fa-instagram', 'color' => 'bg-pink-600', 'text' => 'text-pink-600'],
            'youtube' => ['icon' => 'fab fa-youtube', 'color' => 'bg-red-600', 'text' => 'text-red-600'],
        ];

        $iconData = $platformIcons[$platform] ?? $platformIcons['facebook'];

        $mediaUrls = [];

        if (!empty($post->media_paths)) {
            foreach ($post->media_paths as $path) {
                $mediaUrls[] = asset('storage/' . $path);
            }
        } elseif (!empty($post->media_path)) {
            $mediaUrls[] = asset('storage/' . $post->media_path);
        }

        return view('posts.show', compact('post', 'iconData', 'mediaUrls', 'platform', 'reactions', 'comments', 'shares', 'views'));
    }
}