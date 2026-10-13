<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('bulk kategoris validation returns 422 instead of 500', function () {
    $user = User::factory()->create();
    $user->forceFill(['role' => 'admin'])->save();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/kategoris/bulk', [])
        ->assertStatus(422);
});
