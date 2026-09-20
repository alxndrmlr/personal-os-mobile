<?php

use App\Livewire\Voice;
use Illuminate\Support\Facades\Route;

Route::get('/', Voice::class)->name('voice.index');
