<?php

use App\Models\User;

it('redirects guests visiting / to the login page', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});

it('allows an authenticated user to access the dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertStatus(200);
});
