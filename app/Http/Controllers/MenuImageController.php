<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

class MenuImageController extends Controller
{
    public function __invoke(string $pageId): Response
    {
        abort_unless(ctype_digit($pageId), 404);

        $image = Cache::remember("menu-image:{$pageId}", now()->addDay(), function () use ($pageId): ?array {
            try {
                $metadataResponse = Http::timeout(8)
                    ->connectTimeout(3)
                    ->withUserAgent('DapurRapi/1.0 (menu image proxy)')
                    ->get('https://commons.wikimedia.org/w/api.php', [
                        'action' => 'query',
                        'pageids' => $pageId,
                        'prop' => 'imageinfo',
                        'iiprop' => 'url',
                        'iiurlwidth' => '720',
                        'format' => 'json',
                    ]);

                if (! $metadataResponse->successful()) {
                    return null;
                }

                $imageUrl = data_get($metadataResponse->json(), "query.pages.{$pageId}.imageinfo.0.thumburl");

                if (! is_string($imageUrl) || ! $this->isAllowedImageUrl($imageUrl)) {
                    return null;
                }

                $imageResponse = Http::timeout(10)
                    ->connectTimeout(3)
                    ->withUserAgent('DapurRapi/1.0 (menu image proxy)')
                    ->withOptions([
                        'allow_redirects' => [
                            'max' => 3,
                            'protocols' => ['https'],
                            'on_redirect' => function (RequestInterface $request, ResponseInterface $response, UriInterface $uri): void {
                                abort_unless($this->isAllowedImageUrl((string) $uri), 404);
                            },
                        ],
                    ])
                    ->get($imageUrl);

                $contentType = strtolower(strtok($imageResponse->header('Content-Type', ''), ';'));
                $allowedContentTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

                if (! $imageResponse->successful()
                    || ! in_array($contentType, $allowedContentTypes, true)
                    || strlen($imageResponse->body()) > 5_000_000) {
                    return null;
                }

                return [
                    'content' => base64_encode($imageResponse->body()),
                    'content_type' => $contentType,
                ];
            } catch (ConnectionException) {
                return null;
            }
        });

        abort_if($image === null, 404);

        return response(base64_decode($image['content'], true), 200, [
            'Cache-Control' => 'public, max-age=86400',
            'Content-Type' => $image['content_type'],
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function isAllowedImageUrl(string $url): bool
    {
        $parts = parse_url($url);

        return ($parts['scheme'] ?? null) === 'https'
            && in_array(strtolower($parts['host'] ?? ''), [
                'thumb.wikimedia.org',
                'upload.wikimedia.org',
            ], true);
    }
}
