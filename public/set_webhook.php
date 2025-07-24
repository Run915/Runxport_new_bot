<?php
$bot_token = '8199489325:AAH-bAZWkr3MlRoWGTrUkwK0oE-Njc7vpq0';
$webhook_url = 'https://runxport-new-bot.onrender.com/bot.php';
$secret_token = 'run789azsx';

$url = "https://api.telegram.org/bot{$bot_token}/setWebhook";

$data = [
    'url' => $webhook_url,
    'secret_token' => $secret_token
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
curl_close($ch);

header('Content-Type: application/json');
echo $response;

