<?php
// ✅ 僅允許 POST 請求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// ✅ 載入 BOT TOKEN（Render 平台設定的環境變數）
$token = getenv('BOT_TOKEN');
if (!$token) exit('❌ BOT_TOKEN 未設定');
$apiURL = "https://api.telegram.org/bot$token/";

// ✅ 管理群組與客戶群組（可多個）
$manager_group_id = -1002143413473;
$customer_group_ids = [-1004894662524]; // 可新增其他群組 ID

// ✅ 接收 Telegram Webhook 傳入資料
$update = json_decode(file_get_contents('php://input'), true);
if (!$update) exit;

// ✅ 紀錄 webhook 資訊
logToFile(json_encode($update, JSON_UNESCAPED_UNICODE), 'webhook');

// ✅ 處理收到的 message
if (isset($update['message'])) {
    $msg = $update['message'];
    $chat_id = $msg['chat']['id'];
    $user_id = $msg['from']['id'];
    $message_id = $msg['message_id'];
    $text = $msg['text'] ?? null;

    // ✅ 私訊 /start → 傳送歡迎訊息
    if ($text === '/start') {
        $welcome = "🌟 各位蒞臨潤匯港的貴賓你好\n有任何匯率相關的問題，請私訊我，我們將盡快為您服務！";
        sendMessage($chat_id, $welcome);
        exit;
    }

    // ✅ 在管理群組中使用 /公告 指令
    if ($chat_id == $manager_group_id && $text && strpos($text, '/公告') === 0) {
        $caption = trim(substr($text, 3)); // 去除 /公告 開頭

        foreach ($customer_group_ids as $target) {
            // 發送圖片公告
            if (isset($msg['photo'])) {
                $file_id = end($msg['photo'])['file_id'];
                sendPhoto($target, $file_id, "📢 $caption");
            }
            // 發送影片公告
            elseif (isset($msg['video'])) {
                $file_id = $msg['video']['file_id'];
                sendVideo($target, $file_id, "📢 $caption");
            }
            // 發送文字公告
            else {
                sendMessage($target, "📢 $caption");
            }
        }
        exit;
    }

    // ✅ 客戶私訊 → 轉發到管理群，並記錄對應關係
    if ($chat_id > 0) {
        $result = forwardMessage($manager_group_id, $chat_id, $message_id);
        $data = json_decode($result, true);
        if (isset($data['result']['message_id'])) {
            saveUserMapping($data['result']['message_id'], $user_id);
        }
        exit;
    }

    // ✅ 管理群組回覆 → 回傳給對應客戶
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
            logToFile("⚠️ 找不到對應使用者，請確認是否回覆的是機器人轉發的訊息。", 'reply');
        }
    }
}

// ✅ 傳送純文字
function sendMessage($chat_id, $text) {
    global $apiURL;
    file_get_contents($apiURL . "sendMessage?" . http_build_query([
        'chat_id' => $chat_id,
        'text' => $text
    ]));
}

// ✅ 傳送圖片
function sendPhoto($chat_id, $file_id, $caption = '') {
    global $apiURL;
    file_get_contents($apiURL . "sendPhoto?" . http_build_query([
        'chat_id' => $chat_id,
        'photo' => $file_id,
        'caption' => $caption
    ]));
}

// ✅ 傳送影片
function sendVideo($chat_id, $file_id, $caption = '') {
    global $apiURL;
    file_get_contents($apiURL . "sendVideo?" . http_build_query([
        'chat_id' => $chat_id,
        'video' => $file_id,
        'caption' => $caption
    ]));
}

// ✅ 轉發訊息
function forwardMessage($to, $from, $msg_id) {
    global $apiURL;
    return file_get_contents($apiURL . "forwardMessage?" . http_build_query([
        'chat_id' => $to,
        'from_chat_id' => $from,
        'message_id' => $msg_id
    ]));
}

// ✅ 存 mapping（訊息 ID → 使用者 ID）
function saveUserMapping($group_msg_id, $user_id) {
    $file = __DIR__ . '/data/user_map.json';
    if (!file_exists(dirname($file))) mkdir(dirname($file), 0777, true);
    $map = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    $map[$group_msg_id] = $user_id;
    file_put_contents($file, json_encode($map));
}

// ✅ 查 mapping
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
?>








