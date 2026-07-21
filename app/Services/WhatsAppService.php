<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\WhatsAppAccount;

class WhatsAppService
{
    /**
     * Send WhatsApp template message
     */
    public static function sendTemplate(
        string $to,
        string $template,
        array $variables = []
    ) {
        // Latest active account
        $account = WhatsAppAccount::where('status', 'active')
            ->latest()
            ->first();

        if (!$account) {
            throw new \Exception('No active WhatsApp account found');
        }

        $token = decrypt($account->access_token);

        Log::info([
            'phone_number_id' => $account->phone_number_id,
            'business_id'     => $account->business_account_id,
            'template'        => $template,
            'variables'       => $variables,
        ]);

        $url = "https://graph.facebook.com/v19.0/{$account->phone_number_id}/messages";

        // Dynamic variables
        $parameters = [];

        foreach ($variables as $value) {
            $parameters[] = [
                "type" => "text",
                "text" => (string) $value,
            ];
        }

        $payload = [
            "messaging_product" => "whatsapp",
            "to" => $to,
            "type" => "template",
            "template" => [
                "name" => $template,
                "language" => [
                    "code" => "en"
                ],
                "components" => [
                    [
                        "type" => "body",
                        "parameters" => $parameters
                    ]
                ]
            ]
        ];

        Log::info('WhatsApp Payload', $payload);

        $response = Http::withToken($token)
            ->post($url, $payload);

        if (!$response->successful()) {

            Log::error('WhatsApp Send Failed', [
                'to' => $to,
                'response' => $response->json()
            ]);

            throw new \Exception('WhatsApp API error');
        }

        return true;
    }
}