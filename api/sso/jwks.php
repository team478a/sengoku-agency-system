<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');

try {
    $settings = getAgencySsoSettings();
    if (empty($settings['public_key']) || empty($settings['key_id'])) {
        http_response_code(404);
        echo json_encode([
            'keys' => [],
            'error' => 'sso_key_not_configured',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $jwk = pemPublicKeyToJwk($settings['public_key'], $settings['key_id']);
    if (!$jwk) {
        http_response_code(500);
        echo json_encode([
            'keys' => [],
            'error' => 'invalid_public_key',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode([
        'keys' => [$jwk],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'keys' => [],
        'error' => 'jwks_error',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
