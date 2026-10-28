<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('queue:make-admin', function () {
    $name = $this->ask('Administrator name');
    $email = $this->ask('Administrator email');
    $password = $this->secret('Password (minimum 12 characters)');
    $phone = $this->ask('Phone number (optional)');
    if (! $name || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen((string) $password) < 12) {
        $this->error('A name, valid email, and password of at least 12 characters are required.');
        return self::FAILURE;
    }

    User::query()->updateOrCreate(
        ['email' => $email],
        ['name' => $name, 'phone' => $phone ?: null, 'password' => Hash::make($password), 'role' => 'admin', 'is_active' => true],
    );
    $this->info('Administrator account is ready.');

    return self::SUCCESS;
})->purpose('Create or update an administrator account');
