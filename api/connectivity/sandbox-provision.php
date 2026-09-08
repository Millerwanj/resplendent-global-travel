<?php
declare(strict_types=1);
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode([
    'ok' => false,
    'message' => 'Manual sandbox provisioning was retired in Resplendent v12.0. Provisioning now follows verified payment automatically.'
], JSON_UNESCAPED_SLASHES);
