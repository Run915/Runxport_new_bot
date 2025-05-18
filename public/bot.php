<?php
// ✅ 驗證 POST 方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Method Not Allowed");
}

// ✅ 從環境變數讀取 BOT TOKEN
$token = getenv("BOT_TOKEN");
$apiURL = "https://api.telegram.org/bot$token/";

if (!$token) {
    logToFile("❌ BOT_TOKEN 未設定", "error");
    exit("BOT_TOKEN 未設定");
}

// ✅ 群組設定
$manager_group_id = -1002143413473; // 管理群
$customer_group_ids = [
    -1004894662524, // 客戶群 1（你可以加更多）
];

// ✅ 接收 Webhook 更新
$update = json_decode(file_get_contents("php://input"), true);
if (!$update) {
    logToFile("⚠️ 無效的 Webhook 請求", "webhook");
    exit;
}
logToFile("✅ 收到 Webhook：\n" . json_encode($update), "webhook");

// ✅ 主訊息邏輯
if (isset($update["message"])) {
    $msg = $update["message"];
    $chat_id = $msg["chat"]["id"];
    $user_id = $msg["from"]["id"];
    $message_id = $msg["message_id"];
    $text = $msg["text"] ?? null;

    // ✅ 歡迎訊息
    if ($text === "/start") {
        $welcome = "🌟 親愛的潤匯港貴賓您好！\n歡迎加入我們，有任何匯率相關問題，請直接私訊，將有專人為您服務 🧑‍💼";
        sendMessage($chat_id, $welcome);
        exit;
    }

    // ✅ 管理群輸入 /公告 → 廣播給所有客戶群
    if ($chat_id == $manager_group_id && isset($text) && strpos($text, "/公告") === 0) {
        $caption = trim(substr($text, 3));

        foreach ($customer_group_ids as $group_id) {
            if (isset($msg["photo"])) {
                $photo = end($msg["photo"])["file_id"];
                sendPhoto($group_id, $photo, "📢 $caption");
            } elseif (isset($msg["video"])) {
                $video = $msg["video"]["file_id"];
                sendVideo($group_id, $video, "📢 $caption");
            } else {
                sendMessage($group_id, "📢 $caption");
            }
        }
        return;
    }

    // ✅ 私訊 → 轉發到管理群，並記錄對應
    if ($chat_id > 0) {
        $forwarded = forwardMessageToGroup($chat_id, $message_id);
        if ($forwarded) {
            $data = json_decode($forwarded, true);
            if (isset($data["result"]["message_id"])) {
                $group_msg_id = $data["result"]["message_id"];
                saveUserMapping($group_msg_id, $user_id);
            }
        }
    }

    // ✅ 回覆 → 從管理群回傳給對應用戶
    if ($chat_id == $manager_group_id && isset($msg["reply_to_message"])) {
        $reply_msg_id = $msg["reply_to_message"]["message_id"];
        $target_user_id = getMappedUserId($reply_msg_id);
        if ($target_user_id) {
            if (isset($msg["text"])) {
                sendMessage($target_user_id, "📍 潤匯港客服回覆：\n" . $msg["text"]);
            } elseif (isset($msg["photo"])) {
                $file_id = end($msg["photo"])["file_id"];
                sendPhoto($target_user_id, $file_id, "🖼️ 客服圖片回覆");
            } elseif (isset($msg["video"])) {
                $file_id = $msg["video"]["file_id"];
                sendVideo($target_user_id, $file_id, "🎞️ 客服影片回覆");
            }
        } else {
            logToFile("⚠️ 找不到對應使用者，請確認是否是回覆機器人轉發的訊息。", "error");
        }
    }
}

// ✅ 傳送文字
function sendMessage($chat_id, $text, $mode = null) {
    global $apiURL;
    $data = ['chat_id' => $chat_id, 'text' => $text];
    if ($mode) $data['parse_mode'] = $mode;
    return file_get_contents($apiURL . "sendMessage?" . http_build_query($data));
}

// ✅ 傳送圖片
function sendPhoto($chat_id, $file_id, $caption = '') {
    global $apiURL;
    $data = ['chat_id' => $chat_id, 'photo' => $file_id, 'caption' => $caption];
    return file_get_contents($apiURL . "sendPhoto?" . http_build_query($data));
}

// ✅ 傳送影片
function sendVideo($chat_id, $file_id, $caption = '') {
    global $apiURL;
    $data = ['chat_id' => $chat_id, 'video' => $file_id, 'caption' => $caption];
    return file_get_contents($apiURL . "sendVideo?" . http_build_query($data));
}

// ✅ 轉發訊息到管理群
function forwardMessageToGroup($from_chat_id, $message_id) {
    global $apiURL, $manager_group_id;
    $data = ['chat_id' => $manager_group_id, 'from_chat_id' => $from_chat_id, 'message_id' => $message_id];
    return file_get_contents($apiURL . "forwardMessage?" . http_build_query($data));
}

// ✅ 儲存對應用戶
function saveUserMapping($group_msg_id, $user_id) {
    $file = __DIR__ . "/data/user_map.json";
    if (!file_exists(dirname($file))) mkdir(dirname($file), 0777, true);
    $map = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    $map[$group_msg_id] = $user_id;
    file_put_contents($file, json_encode($map, JSON_PRETTY_PRINT));
}

// ✅ 查詢用戶對應
function getMappedUserId($group_msg_id) {
    $file = __DIR__ . "/data/user_map.json";
    if (!file_exists($file)) return null;
    $map = json_decode(file_get_contents($file), true);
    return $map[$group_msg_id] ?? null;
}

// ✅ 寫入日誌
function logToFile($text, $type = "general") {
    $dir = __DIR__ . "/logs";
    if (!file_exists($dir)) mkdir($dir, 0777, true);
    $file = "$dir/{$type}_" . date("Ymd") . ".log";
    file_put_contents($file, "[" . date("H:i:s") . "] $text\n", FILE_APPEND);
}
?>






