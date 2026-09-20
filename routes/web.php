<?php

use App\Http\Controllers\VoiceController;
use Illuminate\Support\Facades\Route;

Route::get('/', [VoiceController::class, 'index'])->name('voice.index');
Route::post('/voice/turn', [VoiceController::class, 'turn'])->name('voice.turn');
