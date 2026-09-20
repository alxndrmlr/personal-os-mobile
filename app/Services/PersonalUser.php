<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PersonalUser
{
    public function get(): User
    {
        return User::query()->firstOrCreate(
            ['email' => config('personal.user_email')],
            [
                'name' => config('personal.user_name'),
                'password' => Hash::make(Str::random(64)),
            ],
        );
    }
}
