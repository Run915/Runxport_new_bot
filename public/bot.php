<?php
// ✅ 僅允許 POST 請求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$token = getenv('BOT_TOKEN');
if (!$token) exit('❌ BOT_TOKEN 未設定');
$apiURL = "https://api.telegram.org/bot$token/";

// ✅ 群組設定
$manager_group_id = -1002143413473;
$customer_group_ids = [-1004894662524];

// ✅ 接收訊息
$update = json_decode(file_get_contents('php://input'), true);
if (!$update) exit;

logToFile("Webhook Received:", 'webhook');
logToFile(json_encode($update, JSON_UNESCAPED_UNICODE), 'webhook');

if (isset($update['message'])) {
    $msg = $update['message'];
    $chat_id = $msg['chat']['id'];
    $user_id = $msg['from']['id'];
    $message_id = $msg['message_id'];
    $text = $msg['text'] ?? null;

    // ✅ /start 私訊歡迎詞
    if ($text === '/start') {
        sendMessage($chat_id, "🌟 各位蒞臨潤匯港的貴賓你好\n有任何匯率相關的問題，請私訊我，我們將盡快為您服務！");
        exit;
    }

    // ✅ /公告 群組發送
    if ($chat_id == $manager_group_id && $text && strpos($text, '/公告') === 0) {
        $caption = trim(substr($text, 3));
        foreach ($customer_group_ids as $target) {
            if (isset($msg['photo'])) {
                $photo = end($msg['photo'])['file_id'];
                sendPhoto($target, $photo, "📢 $caption");
            } elseif (isset($msg['video'])) {
                $video = $msg['video']['file_id'];
                sendVideo($target, $video, "📢 $caption");
            } else {
                sendMessage($target, "📢 $caption");
            }
        }
        exit;
    }

    // ✅ 客戶私訊 → 轉發至群組
    if ($chat_id > 0) {
        $result = forwardMessage($manager_group_id, $chat_id, $message_id);
        $data = json_decode($result, true);
        if (isset($data['result']['message_id'])) {
            saveUserMapping($data['result']['message_id'], $user_id);
        }
        exit;
    }

    // ✅ 群組回覆訊息 → 傳回私訊
    if ($chat_id == $manager_group_id && isset($msg['reply_to_message'])) {
        $reply_id = $msg['reply_to_message']['message_id'];
        $target_user_id = getMappedUserId($reply_id);

        if ($target_user_id) {
            if (isset($msg['text'])) {
                sendMessage($target_user_id, "📍 潤匯港客服回覆：\n" . $msg['text']);
            } elseif (isset($msg['photo'])) {
                $photo = end($msg['photo'])['file_id'];
                sendPhoto($target_user_id, $photo, "🖼️ 潤匯港客服圖片回覆");
            } elseif (isset($msg['video'])) {
                $video = $msg['video']['file_id'];
                sendVideo($target_user_id, $video, "🎞️ 潤匯港客服影片回覆");
            }
        } else {
            logToFile("⚠️ 找不到對應使用者，請確認是否是回覆機器人轉發的訊息。", 'reply');
        }
    }
}

// ✅ 工具函式區
function sendMessage($chat_id, $text) {
    global $apiURL;
    file_get_contents($apiURL . "sendMessage?" . http_build_query([
        'chat_id' => $chat_id,
        'text' => $text
    ]));
}

function sendPhoto($chat_id, $file_id, $caption = '') {
    global $apiURL;
    file_get_contents($apiURL . "sendPhoto?" . http_build_query([
        'chat_id' => $chat_id,
        'photo' => $file_id,
        'caption' => $caption
    ]));
}

function sendVideo($chat_id, $file_id, $caption = '') {
    global $apiURL;
    file_get_contents($apiURL . "sendVideo?" . http_build_query([
        'chat_id' => $chat_id,
        'video' => $file_id,
        'caption' => $caption
    ]));
}

function forwardMessage($to, $from, $msg_id) {
    global $apiURL;
    return file_get_contents($apiURL . "forwardMessage?" . http_build_query([
        'chat_id' => $to,
        'from_chat_id' => $from,
        'message_id' => $msg_id
    ]));
}

function saveUserMapping($group_msg_id, $user_id) {
    $file = __DIR__ . '/data/user_map.json';
    if (!file_exists(dirname($file))) mkdir(dirname($file), 0777, true);
    $map = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    $map[$group_msg_id] = $user_id;
    file_put_contents($file, json_encode($map));
}

function getMappedUserId($group_msg_id) {
    $file = __DIR__ . '/data/user_map.json';
    if (!file_exists($file)) return null;
    $map = json_decode(file_get_contents($file), true);
    return $map[$group_msg_id] ?? null;
}

function logToFile($text, $type = 'log') {
    $dir = __DIR__ . '/logs';
    if (!file_exists($dir)) mkdir($dir, 0777, true);
    $file = $dir . "/{$type}_" . date('Ymd') . ".log";
    file_put_contents($file, "[" . date('H:i:s') . "] " . $text . "\n", FILE_APPEND);
}










