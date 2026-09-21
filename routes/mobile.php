<?php

use App\NativeComponents\ConnectionDetail;
use App\NativeComponents\Connections;
use App\NativeComponents\Voice;
use App\NativeLayouts\AppLayout;
use Illuminate\Support\Facades\Route;

Route::nativeGroup(AppLayout::class, function (): void {
    Route::native('/', Voice::class)->name('native.voice');
    Route::native('/connections', Connections::class)->name('native.connections');
    Route::native('/connections/{server}', ConnectionDetail::class)->name('native.connections.show');
});
