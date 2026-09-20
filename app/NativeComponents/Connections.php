<?php

namespace App\NativeComponents;

use App\Models\McpServer;
use App\Services\PersonalUser;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Native\Mobile\Edge\Layouts\Builders\TabBarOptions;
use Native\Mobile\Edge\NativeComponent;

class Connections extends NativeComponent
{
    public string $name = '';

    public string $url = '';

    public string $authType = 'OAuth 2.1';

    public string $token = '';

    public string $approvalMode = 'Writes and unknown';

    /** @var array<string, list<string>> */
    public array $errors = [];

    /** @var list<array<string, mixed>> */
    public array $servers = [];

    public function mount(): void
    {
        $this->refreshServers();
    }

    public function onResume(): void
    {
        $this->refreshServers();
    }

    public function navTitle(): string
    {
        return 'Connections';
    }

    public function tabBarOptions(): ?TabBarOptions
    {
        return TabBarOptions::make()->highlight('/connections');
    }

    public function addServer(): void
    {
        $authType = $this->authTypeValue();
        $approvalMode = $this->approvalModeValue();
        $validator = Validator::make([
            'name' => $this->name,
            'url' => $this->url,
            'auth_type' => $authType,
            'token' => $this->token,
            'approval_mode' => $approvalMode,
        ], [
            'name' => ['required', 'string', 'max:80'],
            'url' => ['required', 'url:http,https', 'max:2048'],
            'auth_type' => ['required', 'in:none,bearer,oauth'],
            'token' => ['nullable', 'string', 'max:12000'],
            'approval_mode' => ['required', 'in:writes,always,never'],
        ]);

        if ($validator->fails()) {
            $this->errors = $validator->errors()->toArray();

            return;
        }

        if (! $this->remoteUrlIsSafe($this->url)) {
            $this->errors = [
                'url' => ['Use a clean HTTPS MCP endpoint without embedded credentials or a fragment.'],
            ];

            return;
        }

        $user = app(PersonalUser::class)->get();
        $baseSlug = Str::slug($this->name) ?: 'server';
        $slug = $baseSlug;
        $suffix = 2;

        while (McpServer::query()->whereBelongsTo($user)->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        McpServer::query()->create([
            'user_id' => $user->getKey(),
            'name' => trim($this->name),
            'slug' => $slug,
            'url' => trim($this->url),
            'auth_type' => $authType,
            'bearer_token' => $authType === 'bearer' && filled($this->token)
                ? trim($this->token)
                : null,
            'approval_mode' => $approvalMode,
            'enabled' => true,
        ]);

        $this->name = '';
        $this->url = '';
        $this->token = '';
        $this->authType = 'OAuth 2.1';
        $this->approvalMode = 'Writes and unknown';
        $this->errors = [];
        $this->refreshServers();
    }

    public function openServer(int $id): void
    {
        $this->navigate("/connections/{$id}");
    }

    private function refreshServers(): void
    {
        $user = app(PersonalUser::class)->get();

        $this->servers = McpServer::query()
            ->whereBelongsTo($user)
            ->orderBy('name')
            ->get()
            ->map(fn (McpServer $server): array => [
                'id' => $server->id,
                'name' => $server->name,
                'url' => $server->url,
                'auth_type' => $server->auth_type,
                'enabled' => $server->enabled,
                'connected' => $server->is_connected,
                'last_error' => $server->last_error,
            ])
            ->all();
    }

    private function authTypeValue(): string
    {
        return match ($this->authType) {
            'None' => 'none',
            'Bearer token' => 'bearer',
            default => 'oauth',
        };
    }

    private function approvalModeValue(): string
    {
        return match ($this->approvalMode) {
            'Every tool call' => 'always',
            'Never' => 'never',
            default => 'writes',
        };
    }

    private function remoteUrlIsSafe(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ! isset($parts['fragment'])
            && (($parts['scheme'] ?? null) === 'https' || app()->isLocal());
    }
}
