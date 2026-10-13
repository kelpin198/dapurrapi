<?php

function postJson($url, $data, $token = null)
{
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers)."\r\n",
            'content' => json_encode($data),
            'ignore_errors' => true,
        ],
    ];
    $ctx = stream_context_create($opts);
    $res = file_get_contents($url, false, $ctx);

    return [$res, $http_response_header ?? []];
}

function getJson($url, $token = null)
{
    $headers = ['Accept: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers)."\r\n",
            'ignore_errors' => true,
        ],
    ];
    $ctx = stream_context_create($opts);
    $res = file_get_contents($url, false, $ctx);

    return [$res, $http_response_header ?? []];
}

$base = 'http://127.0.0.1:8000';
[$loginRes, $loginHdrs] = postJson($base.'/api/login', ['email' => 'admin@gmail.com', 'password' => 'password123']);
echo "LOGIN RESPONSE:\n";
foreach ($loginHdrs as $h) {
    echo $h."\n";
}
echo $loginRes."\n\n";
$login = json_decode($loginRes, true);
if (! isset($login['token'])) {
    echo "Login failed, cannot fetch resources.\n";
    exit(1);
}
$token = $login['token'];

[$katRes, $katHdrs] = getJson($base.'/api/kategoris', $token);
echo "KATEGORIS RESPONSE:\n";
foreach ($katHdrs as $h) {
    echo $h."\n";
}
echo $katRes."\n\n";

[$makRes, $makHdrs] = getJson($base.'/api/makanans', $token);
echo "MAKANANS RESPONSE:\n";
foreach ($makHdrs as $h) {
    echo $h."\n";
}
echo $makRes."\n";
