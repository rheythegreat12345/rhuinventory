<?php

test('the application shell includes persistent sidebar navigation and a loading overlay', function () {
    $user = userWithPermissions(['medicines.view']);
    $user->update(['name' => 'Maria Santos']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-sidebar-navigation', false)
        ->assertSee('id="page-navigation-loader"', false)
        ->assertSee('data-time-greeting', false)
        ->assertSee('data-user-name="Maria Santos"', false)
        ->assertSee('Loading section');
});
