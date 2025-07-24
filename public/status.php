<?php
header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'status' => 'Bot running',
    'timestamp' => date('Y-m-d H:i:s')
]);
