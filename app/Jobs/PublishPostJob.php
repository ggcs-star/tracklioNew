<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\YouTubeTokenService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class PublishPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1200; // 20 minutes

    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function handle()
    {
        set_time_limit(0);

        $videoPath = storage_path("app/public/{$this->data['media_path']}");

        $accessToken = YouTubeTokenService::getAccessToken(
            $this->data['user_id']
        );

        $init = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'Content-Type'  => 'application/json; charset=UTF-8',
            'X-Upload-Content-Type' => 'video/mp4',
            'X-Upload-Content-Length' => filesize($videoPath),
        ])->post(
            'https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status',
            [
                'snippet' => [
                    'title' => substr($this->data['content'] ?? 'OmniPost Video', 0, 90),
                ],
                'status' => [
                    'privacyStatus' => 'public'
                ]
            ]
        );

        $uploadUrl = $init->header('Location');

        if (!$uploadUrl) {
            return; 
        }

        $upload = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'Content-Type'  => 'video/mp4',
        ])->withBody(
            fopen($videoPath, 'r'),
            'video/mp4'
        )->put($uploadUrl);

        $data = $upload->json();

        if (!isset($data['id'])) {
            return; 
        }

        Post::create([
            'user_id'    => $this->data['user_id'],
            'content'    => $this->data['content'],
            'platforms'  => $this->data['platforms'],
            'media_path' => $this->data['media_path'],
            'status'     => 'published',
            'meta'       => [
                'youtube' => [
                    'video_id' => $data['id'],
                    'url' => 'https://youtube.com/watch?v=' . $data['id'],
                ]
            ]
        ]);
    }
}
