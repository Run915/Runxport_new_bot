<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$token = getenv('BOT_TOKEN');
if (!$token) exit('❌ BOT_TOKEN 未設定');
$apiURL = "https://api.telegram.org/bot$token/";

$manager_group_id = -1002143413473;
$customer_group_ids = [-1004894662524];

$update = json_decode(file_get_contents('php://input'), true);
if (!$update) exit;

logToFile("Webhook Received:", 'webhook');
logToFile(json_encode($update, JSON_UNESCAPED_UNICODE), 'webhook');

// ✅ 處理訊息
if (isset($update['message'])) {
    $msg = $update['message'];
    $chat_id = $msg['chat']['id'];
    $user_id = $msg['from']['id'];
    $message_id = $msg['message_id'];
    $text = $msg['text'] ?? null;

    // ✅ /start 歡迎私訊
    if ($text === '/start') {
        $welcome = "🌟 各位蒞臨潤匯港的貴賓你好\n有任何匯率相關的問題，請私訊我，我們將盡快為您服務！";
        sendMessage($chat_id, $welcome);
        exit;
    }

    // ✅ 管理群組使用 /公告
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

    // ✅ 私訊 → 轉發給管理群，並儲存對應
    if ($chat_id > 0) {
        $result = forwardMessage($manager_group_id, $chat_id, $message_id);
        if (isset($result['result']['message_id'])) {
            saveUserMapping($result['result']['message_id'], $user_id);
        }
        exit;
    }

    // ✅ 客服群組回覆 → 回傳至私訊客戶
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

// ✅ 發送文字
function sendMessage($chat_id, $text) {
    global $apiURL;
    file_get_contents($apiURL . "sendMessage?" . http_build_query([
        'chat_id' => $chat_id,
        'text' => $text
    ]));
}

// ✅ 發送圖片
function sendPhoto($chat_id, $file_id, $caption = '') {
    global $apiURL;
    file_get_contents($apiURL . "sendPhoto?" . http_build_query([
        'chat_id' => $chat_id,
        'photo' => $file_id,
        'caption' => $caption
    ]));
}

// ✅ 發送影片
function sendVideo($chat_id, $file_id, $caption = '') {
    global $apiURL;
    file_get_contents($apiURL . "sendVideo?" . http_build_query([
        'chat_id' => $chat_id,
        'video' => $file_id,
        'caption' => $caption
    ]));
}

// ✅ 轉發訊息 + 回傳資料
function forwardMessage($to, $from, $msg_id) {
    global $apiURL;
    $res = file_get_contents($apiURL . "forwardMessage?" . http_build_query([
        'chat_id' => $to,
        'from_chat_id' => $from,
        'message_id' => $msg_id
    ]));
    return json_decode($res, true);
}

// ✅ 儲存對應
function saveUserMapping($group_msg_id, $user_id) {
    $file = __DIR__ . '/data/user_map.json';
    if (!file_exists(dirname($file))) mkdir(dirname($file), 0777, true);
    $map = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    $map[$group_msg_id] = $user_id;
    file_put_contents($file, json_encode($map, JSON_PRETTY_PRINT));
}

// ✅ 查找對應
function getMappedUserId($group_msg_id) {
    $file = __DIR__ . '/data/user_map.json';
    if (!file_exists($file)) return null;
    $map = json_decode(file_get_contents($file), true);
    return $map[$group_msg_id] ?? null;
}

// ✅ 紀錄日誌
function logToFile($text, $type = 'log') {
    $dir = __DIR__ . '/logs';
    if (!file_exists($dir)) mkdir($dir, 0777, true);
    $file = $dir . "/{$type}_" . date('Ymd') . ".log";
    file_put_contents($file, "[" . date('H:i:s') . "] " . $text . "\n", FILE_APPEND);
}








