<?php

use App\Services\McpConnectionManager;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'interface' => 'NativePHP SuperNative',
]))->name('voice.index');

Route::redirect('/connections', '/')->name('connections.index');

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

    $scheme = config('nativephp.deeplink_scheme', 'personalos');

    return redirect()->away("{$scheme}://connections");
})->name('connections.oauth.callback');
