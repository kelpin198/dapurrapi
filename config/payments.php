<?php

$bankAccounts = json_decode((string) env('PAYMENT_BANK_ACCOUNTS', '[]'), true);
$bankAccounts = is_array($bankAccounts) ? array_values(array_filter(
    $bankAccounts,
    fn ($bank): bool => is_array($bank)
        && filled($bank['code'] ?? null)
        && filled($bank['name'] ?? null)
        && filled($bank['account_number'] ?? null)
        && filled($bank['account_name'] ?? null),
)) : [];

return [
    'banks' => $bankAccounts,
    'qris_payload' => trim((string) env('PAYMENT_QRIS_PAYLOAD', '')),
    'qris_image_url' => trim((string) env('PAYMENT_QRIS_IMAGE_URL', '')),
];
