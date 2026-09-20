<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McpServer extends Model
{
    protected $guarded = [];

    protected $hidden = [
        'bearer_token',
        'oauth_client_id',
        'oauth_client_secret',
        'oauth_token',
        'oauth_refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'bearer_token' => 'encrypted',
            'oauth_client_id' => 'encrypted',
            'oauth_client_secret' => 'encrypted',
            'oauth_token' => 'encrypted',
            'oauth_refresh_token' => 'encrypted',
            'oauth_expires_at' => 'datetime',
            'enabled' => 'boolean',
            'last_connected_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function isConnected(): Attribute
    {
        return Attribute::get(fn (): bool => match ($this->auth_type) {
            'none' => true,
            'bearer' => filled($this->bearer_token),
            'oauth' => filled($this->oauth_token),
            default => false,
        });
    }
}
