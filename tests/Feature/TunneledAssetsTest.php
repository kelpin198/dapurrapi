<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('cloudflare tunnel pages use built assets instead of inaccessible vite localhost', function () {
    $this->get('http://goto.trycloudflare.com/login')
        ->assertOk()
        ->assertSee('/build/assets/app-', false)
        ->assertDontSee('localhost:5173');
});

test('cloudflare proxy headers use built assets when the origin host is rewritten', function () {
    $this->withHeaders([
        'CF-Ray' => 'test-ray-id',
        'CF-Connecting-IP' => '203.0.113.10',
    ])
        ->get('/login')
        ->assertOk()
        ->assertSee('/build/assets/app-', false)
        ->assertDontSee('localhost:5173');
});

test('local cloudflare tunnels can check a source revision for mobile reload', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->getJson('http://goto.trycloudflare.com/__dev/revision')
        ->assertOk()
        ->assertJsonStructure(['revision']);
});

test('local mobile devices can check a source revision through a LAN address', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->getJson('http://192.168.1.20/__dev/revision')
        ->assertOk()
        ->assertJsonStructure(['revision']);
});

test('source revision endpoint is not available outside local development', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->getJson('http://goto.trycloudflare.com/__dev/revision')
        ->assertNotFound();
});
