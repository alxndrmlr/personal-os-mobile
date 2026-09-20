<?php

namespace App\NativeComponents;

use App\AsyncTasks\TestMcpConnection;
use App\Models\McpServer;
use App\Services\McpConnectionManager;
use App\Services\PersonalUser;
use Illuminate\View\View;
use Native\Mobile\Attributes\Locked;
use Native\Mobile\Browser;
use Native\Mobile\Edge\Layouts\Builders\TabBarOptions;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Exceptions\AsyncTaskException;

class ConnectionDetail extends NativeComponent
{
    #[Locked]
    public int $serverId;

    public string $name = '';

    public string $url = '';

    public string $authType = '';

    public bool $enabled = true;

    public bool $connected = false;

    public string $approvalMode = 'Writes and unknown';

    public string $token = '';

    public string $clientId = '';

    public string $clientSecret = '';

    public string $scope = '';

    public string $status = '';

    public string $error = '';

    public bool $testing = false;

    public function mount(): void
    {
        $this->serverId = (int) $this->param('server');
        $this->loadServer();
    }

    public function navTitle(): string
    {
        return $this->name ?: 'Connection';
    }

    public function tabBarOptions(): ?TabBarOptions
    {
        return TabBarOptions::make()->hidden();
    }

    public function updatedEnabled(bool $value): void
    {
        $this->ownedServer()->update(['enabled' => $value]);
        $this->status = $value ? 'Server enabled.' : 'Server disabled.';
    }

    public function updatedApprovalMode(string $value): void
    {
        $mode = match ($value) {
            'Every tool call' => 'always',
            'Never' => 'never',
            default => 'writes',
        };

        $this->ownedServer()->update(['approval_mode' => $mode]);
        $this->status = 'Approval policy updated.';
    }

    public function saveCredentials(): void
    {
        $server = $this->ownedServer();
        $this->error = '';

        if ($server->auth_type === 'bearer') {
            if (trim($this->token) === '') {
                $this->error = 'Enter a bearer token.';

                return;
            }

            $server->forceFill([
                'bearer_token' => trim($this->token),
                'last_error' => null,
            ])->save();
            $this->token = '';
            $this->status = 'Bearer token saved.';
            $this->loadServer();

            return;
        }

        if ($server->auth_type === 'oauth') {
            $server->forceFill([
                'oauth_client_id' => filled($this->clientId)
                    ? trim($this->clientId)
                    : $server->oauth_client_id,
                'oauth_client_secret' => filled($this->clientSecret)
                    ? trim($this->clientSecret)
                    : $server->oauth_client_secret,
                'oauth_scope' => trim($this->scope) ?: null,
                'last_error' => null,
            ])->save();

            $this->clientId = '';
            $this->clientSecret = '';
            $this->status = 'OAuth settings saved.';
            $this->loadServer();
        }
    }

    public function authorize(): void
    {
        $server = $this->ownedServer();
        $this->error = '';

        try {
            $callback = route('connections.oauth.callback', $server->slug);
            $redirect = app(McpConnectionManager::class)
                ->client($server, $callback)
                ->oAuthClient()
                ->redirect('/connections');

            if (! app(Browser::class)->auth($redirect->getTargetUrl())) {
                $this->error = 'The system authentication session could not be opened.';
            }
        } catch (\Throwable $exception) {
            report($exception);
            $this->error = 'OAuth could not be started. Check the endpoint and client settings.';
        }
    }

    public function testConnection(): void
    {
        $this->testing = true;
        $this->status = 'Checking tools…';
        $this->error = '';

        TestMcpConnection::dispatch($this->serverId)
            ->timeout(60)
            ->finished(function (array $result): void {
                $this->testing = false;
                $this->status = "{$result['name']} exposed {$result['tools']} tools.";
                $this->loadServer();
            })
            ->failed(function (AsyncTaskException $exception): void {
                report($exception);
                $this->testing = false;
                $this->error = 'Connection failed. Check the URL and credentials.';
                $this->ownedServer()->forceFill([
                    'last_error' => (string) str($exception->getMessage())->limit(500),
                ])->save();
                $this->loadServer();
            });
    }

    public function remove(): void
    {
        $this->ownedServer()->delete();
        $this->back();
    }

    public function render(): View
    {
        return view('native.connection-detail');
    }

    private function loadServer(): void
    {
        $server = $this->ownedServer();

        $this->name = $server->name;
        $this->url = $server->url;
        $this->authType = $server->auth_type;
        $this->enabled = $server->enabled;
        $this->connected = $server->is_connected;
        $this->approvalMode = match ($server->approval_mode) {
            'always' => 'Every tool call',
            'never' => 'Never',
            default => 'Writes and unknown',
        };
        $this->scope = $server->oauth_scope ?? '';

        if ($server->last_error) {
            $this->error = $server->last_error;
        }
    }

    private function ownedServer(): McpServer
    {
        return McpServer::query()
            ->whereBelongsTo(app(PersonalUser::class)->get())
            ->findOrFail($this->serverId);
    }
}
