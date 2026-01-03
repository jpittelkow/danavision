<?php

use App\Models\User;

test('authenticated users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $response->assertRedirect('/login');
    $this->assertGuest();
});

test('unauthenticated users cannot access dashboard', function () {
    $response = $this->get('/dashboard');

    $response->assertRedirect('/login');
});
