<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class DeveloperSocialAccount extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'developer_social_accounts';

    protected $fillable = [
        'platform',
        'status',
        'developer_id',
        'developer_name',
        'developer_email',
        'credentials',
        'pages',
    ];

    protected $casts = [
        'credentials' => 'array',
        'pages'       => 'array',
    ];

    public function userToken(): ?string
    {
        return $this->credentials['user_access_token'] ?? null;
    }

    public function facebookPages(): array
    {
        return $this->pages ?? [];
    }
}