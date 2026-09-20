<?php

use App\Livewire\Connections;
use App\Livewire\Voice;
use App\Services\McpConnectionManager;
use Illuminate\Support\Facades\Route;

Route::get('/', Voice::class)->name('voice.index');
Route::get('/connections', Connections::class)->name('connections.index');

Route::get('/connections/{server}/oauth/connect', function (
    string $server,
    McpConnectionManager $connections,
) {
    $connection = $connections->find($server);
    abort_unless($connection->auth_type === 'oauth', 404);

    $callback = route('connections.oauth.callback', $connection->slug);

    return $connections->client($connection, $callback)
        ->oAuthClient()
        ->redirect(route('connections.index'));
})->name('connections.oauth.connect');

Route::get('/connections/{server}/oauth/callback', function (
    string $server,
    McpConnectionManager $connections,
) {
    $connection = $connections->find($server);
    abort_unless($connection->auth_type === 'oauth', 404);

    $callback = route('connections.oauth.callback', $connection->slug);
    $token = $connections->client($connection, $callback)
        ->oAuthClient()
        ->exchangeCallback();

    $connections->storeOAuthToken($connection->slug, $token);

    return redirect()->route('connections.index');
})->name('connections.oauth.callback');
