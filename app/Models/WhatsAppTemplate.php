<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class WhatsAppTemplate extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'whatsapp_templates';

    protected $fillable = [

        'user_id',

        'whatsapp_account_id',

        'business_account_id',

        'template_id',

        'name',

        'language',

        'category',

        'status',

        'body',

        'header',

        'footer',

        'buttons',

        'variables',

        'meta_response',

    ];

    protected $casts = [

        'buttons' => 'array',

        'variables' => 'array',

        'meta_response' => 'array',

    ];
}