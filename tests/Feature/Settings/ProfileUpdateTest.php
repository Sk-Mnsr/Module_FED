<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user signature can be saved and reloaded', function () {
    $user = User::factory()->create(['signature' => null]);

    // PNG 1x1 pixel
    $png = base64_encode(hex2bin(
        '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789a63000100000500010d0a2db40000000049454e44ae426082'
    ));
    $dataUri = 'data:image/png;base64,'.$png;

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.signature.update'), [
            'signature' => $dataUri,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $user->refresh();
    expect($user->signature)->not->toBeNull();
    expect($user->signature)->toStartWith('data:image/');
    expect($user->has_signature)->toBeTrue();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Profile')
            ->where('savedSignature', $user->signature)
        );
});
