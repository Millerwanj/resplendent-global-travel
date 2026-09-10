<?php
declare(strict_types=1);

http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode([
    'ok' => false,
    'message' => 'The development-only mock offer endpoint is retired. Use the live eSIM catalogue endpoint.'
], JSON_UNESCAPED_SLASHES);
