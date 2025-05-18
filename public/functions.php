<?php
define('BOT_TOKEN', 'YOUR_BOT_TOKEN_HERE');
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

$manager_group_id = -1002143413473;
$customer_group_id = -1002363718529;

function logToFile($text, $tag = 'log') {
    file_put_contents("$tag.log", date("[Y-m-d H:i:s] ") . $text . PHP_EOL, FILE_APPEND);
}

function sendMessage($chat_id, $text) {
    return telegramSend('sendMessage', [
        'chat_id' => $chat_id,
        'text' => $text
    ]);
}

function sendPhoto($chat_id, $file_id, $caption = '') {
    return telegramSend('sendPhoto', [
        'chat_id' => $chat_id,
        'photo' => $file_id,
        'caption' => $caption
    ]);
}

function sendVideo($chat_id, $file_id, $caption = '') {
    return telegramSend('sendVideo', [
        'chat_id' => $chat_id,
        'video' => $file_id,
        'caption' => $caption
    ]);
}

function telegramSend($method, $data) {
    $ch = curl_init(API_URL . $method);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    logToFile("✅ curl 成功回傳：$result", 'reply');
    return json_decode($result, true);
}

function mapMessageToUser($msg_id, $user_id) {
    $map = file_exists('user_map.json') ? json_decode(file_get_contents('user_map.json'), true) : [];
    $map[$msg_id] = $user_id;
    file_put_contents('user_map.json', json_encode($map));
}

function getMappedUserId($msg_id) {
    $map = file_exists('user_map.json') ? json_decode(file_get_contents('user_map.json'), true) : [];
    return $map[$msg_id] ?? null;
}
