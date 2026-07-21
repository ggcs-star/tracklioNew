<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AICaptionController extends Controller
{
    public function generate(Request $request)
    {
        $request->validate([
            'image' => 'nullable|string',
            'platforms' => 'nullable|array',
            'tone' => 'nullable|string',
        ]);

        $platforms = implode(', ', $request->platforms ?? ['Facebook', 'Instagram']);
        $tone = $request->tone ?? 'professional';

        $response = Http::withToken(env('OPENAI_API_KEY'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a social media expert. Return JSON only.'
                    ],
                    [
                        'role' => 'user',
                        'content' => "Generate 3 captions for {$platforms}. Tone: {$tone}. Also add 5 hashtags. Return JSON: {\"captions\":[],\"hashtags\":[]}"
                    ]
                ],
                'response_format' => ['type' => 'json_object']
            ]);

        if (!$response->successful()) {
            return response()->json(['success' => false, 'error' => 'AI API failed'], 500);
        }

        $content = json_decode($response->json('choices.0.message.content'), true);

        return response()->json([
            'success' => true,
            'captions' => $content['captions'] ?? [],
            'hashtags' => $content['hashtags'] ?? [],
        ]);
    }
}