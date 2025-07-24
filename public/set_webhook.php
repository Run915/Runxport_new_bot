<?php
// ======= 必填 =======
$token = "8199489325:AAH-bAZWkr3MlRoWGTrUkwK0oE-Njc7vpq0";  // << 請改成你的 Bot Token
$url = "https://runxport-new-bot.onrender.com/bot.php";  // 你的 webhook 目標 URL
$secret = "run789azsx";  // 與 bot.php 中驗證一致的 token
// ===================

$api = "https://api.telegram.org/bot{$token}/setWebhook";

$data = [
    "url" => $url,
    "secret_token" => $secret
];

$options = [
    "http" => [
        "header" => "Content-Type: application/json\r\n",
        "method" => "POST",
        "content" => json_encode($data),
    ],
];

$context = stream_context_create($options);
$response = file_get_contents($api, false, $context);
echo $response;
