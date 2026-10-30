<?php

use Illuminate\Support\Facades\Http;

test('menu images are served from the application origin and cached', function () {
    Http::preventStrayRequests();
    Http::fake([
        'commons.wikimedia.org/w/api.php*' => Http::response([
            'query' => [
                'pages' => [
                    '12345' => [
                        'imageinfo' => [[
                            'thumburl' => 'https://thumb.wikimedia.org/commons/tea.jpg',
                        ]],
                    ],
                ],
            ],
        ]),
        'thumb.wikimedia.org/*' => Http::response('image-bytes', 200, [
            'Content-Type' => 'image/jpeg',
        ]),
    ]);

    $this->get('/menu-image/12345')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertSee('image-bytes');

    $this->get('/menu-image/12345')->assertOk();

    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => str_contains(
        $request->header('User-Agent')[0] ?? '',
        'DapurRapi/1.0',
    ));
});

test('menu image proxy rejects invalid page ids', function () {
    $this->get('/menu-image/not-a-page')->assertNotFound();
});
