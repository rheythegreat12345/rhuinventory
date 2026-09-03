<?php

use App\Models\Medicine;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

test('the seeded application pages render for an administrator', function () {
    $this->seed(DatabaseSeeder::class);
    $administrator = User::query()->where('email', 'admin@rhu.test')->firstOrFail();
    $medicine = Medicine::query()->firstOrFail();

    $routes = [
        route('dashboard'),
        route('medicines.index'),
        route('medicines.show', $medicine),
        route('transactions.index'),
        route('reports.index'),
        route('analytics'),
        route('notifications.index'),
        route('users.index'),
        route('roles.index'),
        route('settings.edit'),
        route('scanner'),
    ];

    foreach ($routes as $url) {
        $this->actingAs($administrator)->get($url)->assertOk();
    }
});
