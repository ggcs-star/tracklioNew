<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignSend;
use App\Models\BroadcastGroup;
use App\Models\BroadcastGroupContact;
use App\Models\Contact;
use App\Models\Notification;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppService;


class WhatsAppCampaignController extends Controller
{
    public function index()
    {
        try {
            $userId = (string) Auth::id();

            // Campaigns
            $campaigns = WhatsAppCampaign::where('user_id', $userId)
                ->latest()
                ->get()
                ->map(fn ($c) => [
                    '_id'    => (string) $c->_id,
                    'name'   => $c->name,
                    'status' => $c->status ?? 'active',
                ])
                ->values();

            // Broadcast Groups
            $groups = BroadcastGroup::where('user_id', $userId)
                ->get()
                ->map(function ($g) {
                    return [
                        '_id' => (string) $g->_id,
                        'name' => $g->name,
                        'contacts_count' => BroadcastGroupContact::where(
                            'group_id',
                            (string) $g->_id
                        )->count(),
                    ];
                })
                ->values();

            // ✅ Get ONLY APPROVED templates for sending
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
                    ];
                })
                ->values();

            $account = WhatsAppAccount::where('user_id', $userId)->first();

            return view('whatsapp_accounts.campaigns', [
                'campaigns' => $campaigns,
                'groups'    => $groups, 
                'templates' => $templates,
                'account'   => $account,
            ]);

        } catch (\Throwable $e) {
            Log::error('WhatsApp campaign index error', [
                'user_id' => Auth::id(),
                'error'   => $e->getMessage(),
            ]);

            abort(500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
            ]);

            $campaign = WhatsAppCampaign::create([
                'user_id' => (string) Auth::id(),
                'name'    => $request->name,
                'status'  => 'active', 
            ]);

            Notification::create([
                'user_id' => (string) Auth::id(),
                'type'    => 'whatsapp_campaign_created',
                'message' => 'WhatsApp campaign created successfully',
                'is_read' => false,
            ]);

            return response()->json([
                'success' => true,
                'data'    => [
                    '_id'    => (string) $campaign->_id,
                    'name'   => $campaign->name,
                    'status' => $campaign->status,
                ],
            ]);

        } catch (\Throwable $e) {
            Log::error('WhatsApp campaign store error', [
                'user_id' => Auth::id(),
                'payload' => $request->all(),
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create campaign',
            ], 500);
        }
    }

public function send(Request $request)
{
    Log::info('Send payload', $request->all());

    try {
        $request->validate([
            'campaign_id' => 'required|string',
            'group_id'    => 'required|string',
            'template'    => 'required|string',
        ]);

        $userId = (string) Auth::id();

        // Campaign validation
        $campaign = WhatsAppCampaign::where('_id', $request->campaign_id)
            ->where('user_id', $userId)
            ->firstOrFail();

        // WhatsApp account
        $account = WhatsAppAccount::where('user_id', $userId)
            ->firstOrFail();

        // ✅ Get contact IDs from group
        $contactIds = DB::connection('mongodb')
            ->table('broadcast_group_contacts')
            ->where('group_id', (string) $request->group_id)
            ->pluck('contact_id')
            ->toArray();

        Log::info('Contact IDs from group', [
            'group_id' => $request->group_id,
            'count' => count($contactIds),
            'ids' => $contactIds
        ]);

        if (empty($contactIds)) {
            throw new \Exception('No contacts found in group');
        }

        // ✅ Get contacts
        $contacts = DB::connection('mongodb')
            ->table('contacts')
            ->whereIn('_id', $contactIds)
            ->get();

        Log::info('Contacts found', [
            'count' => $contacts->count()
        ]);

        if ($contacts->isEmpty()) {
            throw new \Exception('No valid contacts found');
        }

        // Get template variables
        $template = WhatsAppTemplate::where('name', $request->template)
            ->where('user_id', $userId)
            ->first();

        $variables = [];
        if ($template && !empty($template->variables)) {
            foreach ($template->variables as $var) {
                $variables[] = $campaign->name;
            }
        }

        $sent = 0;
        $failed = 0;
        $sentContacts = [];

        foreach ($contacts as $contact) {
            try {
                $mobile = preg_replace('/\D/', '', $contact->phone_number ?? '');
                
                if (strlen($mobile) === 10) {
                    $mobile = '91' . $mobile;
                }

                if (strlen($mobile) !== 12) {
                    throw new \Exception('Invalid mobile number: ' . $mobile);
                }

                WhatsAppService::sendTemplate(
                    $mobile,
                    $request->template,
                    $variables
                );

                $sent++;
                // ✅ Contact ID as string
                $sentContacts[] = (string) $contact->id;

                Log::info('Message sent', [
                    'to' => $mobile,
                    'contact_id' => (string) $contact->id
                ]);

            } catch (\Throwable $e) {
                $failed++;
                Log::error('Campaign send failed', [
                    'phone' => $contact->phone_number ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // ✅ SAVE WITH ARRAYS (NOT JSON STRINGS)
        WhatsAppCampaignSend::create([
            'user_id'             => $userId,
            'campaign_id'         => (string) $campaign->_id,
            'whatsapp_account_id' => (string) $account->_id,
            'template_name'       => $request->template,
            'payload'             => [],  // ✅ Array
            'group_ids'           => [(string) $request->group_id],  // ✅ Array
            'contact_ids'         => $sentContacts,  // ✅ Array (multiple contacts support)
            'sent'                => $sent,
            'failed'              => $failed,
            'created_at'          => now(),
        ]);

        // $campaign->status = 'sent';
        // $campaign->save();

        return response()->json([
            'success'  => true,
            'sent'     => $sent,
            'failed'   => $failed,
            'campaign' => [
                '_id'    => (string) $campaign->_id,
                'name'   => $campaign->name,
                'status' => $campaign->status,
            ],
        ]);

    } catch (\Throwable $e) {
        Log::error('WhatsApp campaign send error', [
            'user_id' => Auth::id(),
            'error'   => $e->getMessage(),
            'trace'   => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Campaign send failed: ' . $e->getMessage(),
        ], 500);
    }
}
}