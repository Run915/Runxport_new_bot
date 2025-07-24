<?php
// ✅ 替換為你自己的 Bot Token
$bot_token = '8199489325:AAH-bAZWkr3MlRoWGTrUkwK0oE-Njc7vpq0';

// ✅ 替換為你的網址與 secret_token
$webhook_url = 'https://runxport-new-bot.onrender.com/bot.php';
$secret_token = 'run789azsx';

$url = "https://api.telegram.org/bot{$bot_token}/setWebhook";
$data = [
    'url' => $webhook_url,
    'secret_token' => $secret_token
];

$options = [
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json",
        'content' => json_encode($data)
    ]
];

$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);

header('Content-Type: application/json');
echo $result;
