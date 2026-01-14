<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get(route('dashboard'))->assertOk();
});

test('user can update their twitter handle from dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('dashboard.profile.update'), [
            'twitter_handle' => 'testuser',
        ])
        ->assertRedirect();

    expect($user->refresh()->twitter_handle)->toBe('testuser');
});

test('user can clear their twitter handle from dashboard', function () {
    $user = User::factory()->create(['twitter_handle' => 'oldhandle']);

    $this->actingAs($user)
        ->patch(route('dashboard.profile.update'), [
            'twitter_handle' => '',
        ])
        ->assertRedirect();

    expect($user->refresh()->twitter_handle)->toBeNull();
});

test('twitter handle validation works from dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('dashboard.profile.update'), [
            'twitter_handle' => 'invalid@handle!',
        ])
        ->assertSessionHasErrors('twitter_handle');
});