<?php
define("BOT_TOKEN", "8199489325:AAH-bAZWkr3MlRoWGTrUkwK0oE-Njc7vpq0");
define("MAP_FILE", __DIR__ . "/user_map.json");

function sendMessage($chat_id, $text) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
    $post_fields = ['chat_id' => $chat_id, 'text' => $text];
    return sendRequest($url, $post_fields);
}

function sendPhoto($chat_id, $file_id, $caption = "") {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendPhoto";
    $post_fields = ['chat_id' => $chat_id, 'photo' => $file_id, 'caption' => $caption];
    return sendRequest($url, $post_fields);
}

function sendVideo($chat_id, $file_id, $caption = "") {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendVideo";
    $post_fields = ['chat_id' => $chat_id, 'video' => $file_id, 'caption' => $caption];
    return sendRequest($url, $post_fields);
}

function sendRequest($url, $data) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    logToFile("✅ curl 成功回傳：" . $result);
    return json_decode($result, true)['result']['message_id'] ?? null;
}

function saveUserMapping($message_id, $user_id) {
    $map = file_exists(MAP_FILE) ? json_decode(file_get_contents(MAP_FILE), true) : [];
    $map[$message_id] = $user_id;
    file_put_contents(MAP_FILE, json_encode($map));
}

function getMappedUserId($message_id) {
    $map = file_exists(MAP_FILE) ? json_decode(file_get_contents(MAP_FILE), true) : [];
    return $map[$message_id] ?? null;
}

function logToFile($text, $prefix = 'log') {
    $log = "[" . date("H:i:s") . "] " . $text . "\n";
    file_put_contents(__DIR__ . "/logs/{$prefix}.log", $log, FILE_APPEND);
}
?>
