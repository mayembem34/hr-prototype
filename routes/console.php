<?php
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

Artisan::command('hr:platform-admin {email} {name}', function () {
    if (User::where('email', $this->argument('email'))->exists()) { $this->error('Email already exists.'); return 1; }
    $password = $this->secret('Password (at least 12 characters)');
    if (! is_string($password) || strlen($password) < 12) { $this->error('Password must be at least 12 characters.'); return 1; }
    $user = new User(['name' => $this->argument('name'), 'email' => $this->argument('email'), 'password' => Hash::make($password)]);
    $user->is_platform_admin = true; $user->save();
    $this->info('Platform administrator created.'); return 0;
})->purpose('Create a platform administrator');

Artisan::command('training:remind', function () { $this->info('Training reminders sent: '.app(\App\Services\TrainingReminders::class)->sendDue()); })->purpose('Send due training reminders');
\Illuminate\Support\Facades\Schedule::command('training:remind')->daily()->withoutOverlapping();
