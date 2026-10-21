<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('cloudflare tunnel pages use built assets instead of inaccessible vite localhost', function () {
    $this->get('http://goto.trycloudflare.com/login')
        ->assertOk()
        ->assertSee('/build/assets/app-', false)
        ->assertDontSee('localhost:5173');
});
