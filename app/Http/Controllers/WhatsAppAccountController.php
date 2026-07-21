<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppAccount;
use App\Models\Notification;
use App\Models\WhatsAppTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class WhatsAppAccountController extends Controller
{
    public function page()
    {
        try {
            return view('whatsapp_accounts.index');
        } catch (\Throwable $e) {
            Log::error('WhatsApp page load error', [
                'user_id' => Auth::id(),
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            abort(500);
        }
    }

    public function index()
    {
        $accounts = WhatsAppAccount::where('user_id', (string) Auth::id())
            ->latest()
            ->get()
            ->map(function ($a) {
                return [
                    '_id' => (string) $a->_id,
                    'provider' => $a->provider,
                    'phone_number_id' => $a->phone_number_id,
                    'business_account_id' => $a->business_account_id,
                    'status' => $a->status,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'provider'             => 'required|string',
                'phone_number_id'      => 'required|string',
                'business_account_id'  => 'required|string',
                'access_token'         => 'required|string',
                'token_expires_at'     => 'nullable|date',
            ]);

            $account = WhatsAppAccount::create([
                'user_id'             => (string) Auth::id(),
                'provider'            => $request->provider,
                'phone_number_id'     => $request->phone_number_id,
                'business_account_id' => $request->business_account_id,
                'access_token'        => encrypt($request->access_token),
                'token_expires_at'    => $request->token_expires_at,
                'status'              => 'active',
            ]);

            // ✅ Sync templates after account creation
            try {
                $this->syncTemplates($account);
            } catch (\Throwable $e) {
                Log::error('Template Sync Failed', [
                    'error' => $e->getMessage()
                ]);
            }

            Notification::create([
                'user_id' => (string) Auth::id(),
                'type'    => 'whatsapp_account_connected',
                'message' => 'WhatsApp account connected successfully',
                'is_read' => false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'WhatsApp account connected successfully',
                'data'    => $account,
            ]);
        } catch (\Throwable $e) {
            Log::error('WhatsApp account store error', [
                'user_id' => Auth::id(),
                'payload' => $request->except('access_token'),
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to connect WhatsApp account',
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $account = WhatsAppAccount::where('_id', $id)
                ->where('user_id', (string) Auth::id())
                ->firstOrFail();

            $account->delete();
            
            Notification::create([
                'user_id' => (string) Auth::id(),
                'type'    => 'whatsapp_account_disconnected',
                'message' => 'WhatsApp account disconnected',
                'is_read' => false,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'WhatsApp account disconnected',
            ]);
        } catch (\Throwable $e) {
            Log::error('WhatsApp account delete error', [
                'user_id'   => Auth::id(),
                'account_id'=> $id,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to disconnect WhatsApp account',
            ], 500);
        }
    }

    public function syncTemplates(WhatsAppAccount $account)
    {
        try {
            $token = decrypt($account->access_token);
            
            // ✅ Fetch templates from WhatsApp API
            $response = Http::withToken($token)->get(
                "https://graph.facebook.com/v23.0/{$account->business_account_id}/message_templates"
            );

            if (!$response->successful()) {
                Log::error('Failed to fetch templates', [
                    'status' => $response->status(),
                    'body' => $response->json()
                ]);
                throw new \Exception('Failed to fetch templates from WhatsApp API');
            }

            $data = $response->json();
            $templates = $data['data'] ?? [];

            // ✅ Delete old templates for this account
            WhatsAppTemplate::where('whatsapp_account_id', (string) $account->_id)
                ->where('user_id', (string) $account->user_id)
                ->delete();

            // ✅ Store new templates
            foreach ($templates as $template) {
                // Extract body, header, footer from components
                $body = null;
                $header = null;
                $footer = null;
                $buttons = [];
                $variables = [];

                if (isset($template['components'])) {
                    foreach ($template['components'] as $component) {
                        if ($component['type'] === 'BODY') {
                            $body = $component['text'] ?? null;
                            // Extract variables from body
                            if (isset($component['text']) && preg_match_all('/{{(\d+)}}/', $component['text'], $matches)) {
                                $variables = $matches[1];
                            }
                        } elseif ($component['type'] === 'HEADER') {
                            $header = $component['text'] ?? null;
                        } elseif ($component['type'] === 'FOOTER') {
                            $footer = $component['text'] ?? null;
                        } elseif ($component['type'] === 'BUTTONS') {
                            $buttons = $component['buttons'] ?? [];
                        }
                    }
                }

                WhatsAppTemplate::create([
                    'user_id' => (string) $account->user_id,
                    'whatsapp_account_id' => (string) $account->_id,
                    'business_account_id' => $account->business_account_id,
                    'template_id' => $template['id'] ?? null,
                    'name' => $template['name'],
                    'language' => $template['language'] ?? 'en',
                    'category' => $template['category'] ?? 'MARKETING',
                    'status' => $template['status'] ?? 'PENDING',
                    'body' => $body,
                    'header' => $header,
                    'footer' => $footer,
                    'buttons' => $buttons,
                    'variables' => $variables,
                    'meta_response' => $template,
                ]);
            }

            Log::info('Templates synced successfully', [
                'account_id' => (string) $account->_id,
                'count' => count($templates)
            ]);

            return count($templates);

        } catch (\Throwable $e) {
            Log::error('Template sync error', [
                'account_id' => (string) $account->_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    // ✅ Add a method to manually refresh templates
    public function refreshTemplates(Request $request)
    {
        try {
            $userId = (string) Auth::id();
            
            $account = WhatsAppAccount::where('user_id', $userId)
                ->where('status', 'active')
                ->first();

            if (!$account) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active WhatsApp account found'
                ], 404);
            }

            $count = $this->syncTemplates($account);

            return response()->json([
                'success' => true,
                'message' => "Templates refreshed successfully. {$count} templates synced.",
                'count' => $count
            ]);

        } catch (\Throwable $e) {
            Log::error('Template refresh error', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh templates: ' . $e->getMessage()
            ], 500);
        }
    }

    // ✅ Get templates for dropdown
    public function getTemplates()
    {
        try {
            $userId = (string) Auth::id();
            
            $templates = WhatsAppTemplate::where('user_id', $userId)
                ->where('status', 'APPROVED')
                ->orderBy('name')
                ->get()
                ->map(function($t) {
                    return [
                        '_id' => (string) $t->_id,
                        'name' => $t->name,
                        'language' => $t->language,
                        'category' => $t->category,
                        'status' => $t->status,
                        'body' => $t->body,
                        'variables' => $t->variables ?? [],
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'data' => $templates,
            ]);

        } catch (\Throwable $e) {
            Log::error('Get templates error', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch templates'
            ], 500);
        }
    }
}