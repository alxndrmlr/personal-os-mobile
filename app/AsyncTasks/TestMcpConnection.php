<?php

namespace App\AsyncTasks;

use App\Models\McpServer;
use App\Services\McpConnectionManager;
use App\Services\PersonalUser;
use Native\Mobile\AsyncTask;

class TestMcpConnection extends AsyncTask
{
    /**
     * @return array{name: string, tools: int}
     */
    public function handle(int $serverId): array
    {
        $user = app(PersonalUser::class)->get();
        $server = McpServer::query()
            ->whereBelongsTo($user)
            ->findOrFail($serverId);

        $count = app(McpConnectionManager::class)->client($server)->tools()->count();

        $server->forceFill([
            'last_connected_at' => now(),
            'last_error' => null,
        ])->save();

        return ['name' => $server->name, 'tools' => $count];
    }
}
