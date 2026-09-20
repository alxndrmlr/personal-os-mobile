<?php

namespace App\Livewire;

use App\Models\McpServer;
use App\Services\McpConnectionManager;
use App\Services\PersonalUser;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Throwable;

class Connections extends Component
{
    public string $name = '';

    public string $url = '';

    public string $authType = 'oauth';

    public string $token = '';

    public string $approvalMode = 'writes';

    /** @var array<int, string> */
    public array $tokens = [];

    /** @var array<int, string> */
    public array $clientIds = [];

    /** @var array<int, string> */
    public array $clientSecrets = [];

    /** @var array<int, string> */
    public array $scopes = [];

    public function addServer(PersonalUser $personalUser): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:80'],
            'url' => ['required', 'url:http,https', 'max:2048'],
            'authType' => ['required', Rule::in(['none', 'bearer', 'oauth'])],
            'token' => ['nullable', 'string', 'max:12000'],
            'approvalMode' => ['required', Rule::in(['writes', 'always', 'never'])],
        ]);

        $this->guardRemoteUrl($validated['url']);

        $baseSlug = Str::slug($validated['name']) ?: 'server';
        $slug = $baseSlug;
        $suffix = 2;

        while (McpServer::query()
            ->whereBelongsTo($personalUser->get())
            ->where('slug', $slug)
            ->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        McpServer::query()->create([
            'user_id' => $personalUser->get()->getKey(),
            'name' => $validated['name'],
            'slug' => $slug,
            'url' => $validated['url'],
            'auth_type' => $validated['authType'],
            'bearer_token' => $validated['authType'] === 'bearer' ? $validated['token'] : null,
            'approval_mode' => $validated['approvalMode'],
            'enabled' => true,
        ]);

        $this->reset(['name', 'url', 'token']);
        $this->authType = 'oauth';
        $this->approvalMode = 'writes';
    }

    public function saveCredentials(int $id, McpConnectionManager $connections): void
    {
        $server = $this->ownedServer($id, $connections);

        if ($server->auth_type === 'bearer') {
            $token = trim($this->tokens[$id] ?? '');

            if ($token === '') {
                throw ValidationException::withMessages(["tokens.{$id}" => 'Enter a bearer token.']);
            }

            $server->forceFill(['bearer_token' => $token, 'last_error' => null])->save();
            unset($this->tokens[$id]);

            return;
        }

        if ($server->auth_type === 'oauth') {
            $server->forceFill([
                'oauth_client_id' => filled($this->clientIds[$id] ?? null)
                    ? trim($this->clientIds[$id])
                    : $server->oauth_client_id,
                'oauth_client_secret' => filled($this->clientSecrets[$id] ?? null)
                    ? trim($this->clientSecrets[$id])
                    : $server->oauth_client_secret,
                'oauth_scope' => array_key_exists($id, $this->scopes)
                    ? trim($this->scopes[$id]) ?: null
                    : $server->oauth_scope,
                'last_error' => null,
            ])->save();

            unset($this->clientIds[$id], $this->clientSecrets[$id], $this->scopes[$id]);
        }
    }

    public function toggle(int $id, McpConnectionManager $connections): void
    {
        $server = $this->ownedServer($id, $connections);
        $server->update(['enabled' => ! $server->enabled]);
    }

    public function setApprovalMode(int $id, string $mode, McpConnectionManager $connections): void
    {
        abort_unless(in_array($mode, ['writes', 'always', 'never'], true), 422);
        $this->ownedServer($id, $connections)->update(['approval_mode' => $mode]);
    }

    public function remove(int $id, McpConnectionManager $connections): void
    {
        $this->ownedServer($id, $connections)->delete();
    }

    public function test(int $id, McpConnectionManager $connections): void
    {
        $server = $this->ownedServer($id, $connections);

        try {
            $count = $connections->client($server)->tools()->count();
            $server->forceFill([
                'last_connected_at' => now(),
                'last_error' => null,
            ])->save();

            $this->dispatch('connection-tested', message: "{$server->name} exposed {$count} tools.");
        } catch (Throwable $exception) {
            $server->forceFill([
                'last_error' => (string) str($exception->getMessage())->limit(500),
            ])->save();

            $this->addError("server.{$id}", 'Connection failed. Check the URL and credentials.');
        }
    }

    public function render(PersonalUser $personalUser)
    {
        return view('livewire.connections', [
            'servers' => McpServer::query()
                ->whereBelongsTo($personalUser->get())
                ->orderBy('name')
                ->get(),
        ])->layout('layouts.app');
    }

    private function ownedServer(int $id, McpConnectionManager $connections): McpServer
    {
        $server = McpServer::query()->findOrFail($id);

        abort_unless($connections->find($server->slug)->is($server), 404);

        return $server;
    }

    private function guardRemoteUrl(string $url): void
    {
        $parts = parse_url($url);

        if (! is_array($parts)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
            || (($parts['scheme'] ?? null) !== 'https' && ! app()->isLocal())) {
            throw ValidationException::withMessages([
                'url' => 'Use a clean HTTPS MCP endpoint without embedded credentials or a fragment.',
            ]);
        }
    }
}
