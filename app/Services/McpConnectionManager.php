<?php

namespace App\Services;

use App\Ai\Tools\ApprovableMcpTool;
use App\Models\McpServer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Client;
use Laravel\Mcp\Client\OAuth\TokenSet;
use Laravel\Mcp\WebClient;
use Throwable;

class McpConnectionManager
{
    public function __construct(private readonly PersonalUser $personalUser) {}

    /**
     * @return array<int, ApprovableMcpTool>
     */
    public function tools(): array
    {
        return $this->servers()
            ->flatMap(function (McpServer $server): array {
                try {
                    $tools = $this->client($server)->tools();

                    $server->forceFill([
                        'last_connected_at' => now(),
                        'last_error' => null,
                    ])->save();

                    return $tools
                        ->map(fn ($tool) => new ApprovableMcpTool(
                            $tool,
                            $server->slug,
                            $server->name,
                            $server->approval_mode,
                        ))
                        ->values()
                        ->all();
                } catch (Throwable $exception) {
                    $server->forceFill([
                        'last_error' => str($exception->getMessage())->limit(500),
                    ])->save();

                    Log::warning('An MCP server could not provide tools.', [
                        'server' => $server->slug,
                        'exception' => $exception::class,
                    ]);

                    return [];
                }
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, McpServer>
     */
    public function servers(): Collection
    {
        return McpServer::query()
            ->whereBelongsTo($this->personalUser->get())
            ->where('enabled', true)
            ->get()
            ->filter(fn (McpServer $server): bool => $server->is_connected)
            ->values();
    }

    public function find(string $slug): McpServer
    {
        return McpServer::query()
            ->whereBelongsTo($this->personalUser->get())
            ->where('slug', $slug)
            ->firstOrFail();
    }

    public function client(McpServer $server, ?string $redirectUri = null): WebClient
    {
        $client = Client::web($server->url)->withTimeout(config('mcp-connections.timeout', 20));

        if ($server->auth_type === 'oauth') {
            $client->withOAuth(
                clientId: $server->oauth_client_id,
                clientSecret: $server->oauth_client_secret,
                scope: $server->oauth_scope ?? '',
                redirectUri: $redirectUri,
            );

            $this->refreshIfNeeded($server, $client);

            if (filled($server->oauth_token)) {
                $client->withToken($server->oauth_token);
            }
        } elseif ($server->auth_type === 'bearer' && filled($server->bearer_token)) {
            $client->withToken($server->bearer_token);
        }

        return $client;
    }

    public function storeOAuthToken(string $slug, TokenSet $token): McpServer
    {
        $server = $this->find($slug);

        $server->forceFill([
            'oauth_token' => $token->accessToken,
            'oauth_refresh_token' => $token->refreshToken ?? $server->oauth_refresh_token,
            'oauth_expires_at' => $token->expiresAt ? now()->setTimestamp($token->expiresAt) : null,
            'oauth_client_id' => $token->clientId ?? $server->oauth_client_id,
            'oauth_client_secret' => $token->clientSecret ?? $server->oauth_client_secret,
            'last_error' => null,
        ])->save();

        return $server;
    }

    private function refreshIfNeeded(McpServer $server, WebClient $client): void
    {
        if (blank($server->oauth_refresh_token)
            || ! $server->oauth_expires_at
            || $server->oauth_expires_at->isAfter(now()->addMinute())) {
            return;
        }

        $token = $client->oAuthClient()->refreshCredentials(
            $server->oauth_refresh_token,
            $server->oauth_client_id,
            $server->oauth_client_secret,
        );

        $this->storeOAuthToken($server->slug, $token);
        $server->refresh();
    }
}
