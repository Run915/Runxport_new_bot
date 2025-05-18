<?php
// ✅ 驗證 HTTP 方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Method Not Allowed");
}

// ✅ 從環境變數讀取 TOKEN
$token = getenv("BOT_TOKEN");
if (!$token) {
    logToFile("❌ BOT_TOKEN 未設定", "error");
    exit("BOT_TOKEN 未設定");
}

$apiURL = "https://api.telegram.org/bot$token/";

// ✅ 管理群組 & 客戶群組們
$manager_group_id = -1002143413473; // 管理群
$customer_group_ids = [
    -1005557779999, // 客戶群 1
    -1006668887777  // 客戶群 2
];

$update = json_decode(file_get_contents("php://input"), true);
if (!$update) {
    logToFile("⚠️ 無效的 webhook 請求", "webhook");
    exit;
}
logToFile("✅ 收到 webhook 請求：" . json_encode($update), "webhook");

// ✅ 處理訊息
if (isset($update["message"])) {
    $message = $update["message"];
    $chat_id = $message["chat"]["id"];
    $user_id = $message["from"]["id"];
    $message_id = $message["message_id"];
    $text = $message["text"] ?? null;

    // ✅ /start 指令
    if ($text === "/start") {
        $welcome = "✨各位蒞臨潤匯港的貴賓你好\n有任何匯率相關的問題，請私訊我，我們將盡快為您服務！";
        sendMessage($chat_id, $welcome);
        exit;
    }

    // ✅ 管理群組發送 /公告：轉發到所有客戶群
    if ($chat_id == $manager_group_id && isset($text) && strpos($text, "/公告") === 0) {
        $caption = trim(substr($text, 3)); // 去掉 "/公告"

        foreach ($customer_group_ids as $target_id) {
            if (isset($message["photo"])) {
                $photo = end($message["photo"])["file_id"];
                sendPhoto($target_id, $photo, $caption);
                logToFile("📷 圖片公告 → $target_id", "broadcast");

            } elseif (isset($message["video"])) {
                $video = $message["video"]["file_id"];
                sendVideo($target_id, $video, $caption);
                logToFile("🎬 影片公告 → $target_id", "broadcast");

            } else {
                sendMessage($target_id, "📢 $caption");
                logToFile("💬 文字公告 → $target_id", "broadcast");
            }
        }
        exit;
    }

    // ✅ 私訊 → 轉發到管理群，並記錄對應
    if ($chat_id > 0) {
        $forwarded_msg = forwardMessageToGroup($chat_id, $message_id);
        if ($forwarded_msg) {
            $forwarded_data = json_decode($forwarded_msg, true);
            if (isset($forwarded_data['result']['message_id'])) {
                $group_msg_id = $forwarded_data['result']['message_id'];
                saveUserMapping($group_msg_id, $user_id);
            }
        }
    }

    // ✅ 群組回覆 → 回原私訊用戶
    if ($chat_id == $manager_group_id && isset($message["reply_to_message"])) {
        $replied_msg_id = $message["reply_to_message"]["message_id"];
        $target_user_id = getMappedUserId($replied_msg_id);

        if ($target_user_id) {
            if (isset($message["voice"])) {
                // 不處理語音
                logToFile("⛔ 忽略語音回覆", "reply");
            } else {
                $reply_text = $text ?? '[非文字內容]';
                sendMessage($target_user_id, "💬 潤匯港客服回覆：\n" . $reply_text);
            }
        } else {
            logToFile("⚠️ 找不到對應使用者 for message_id $replied_msg_id", "error");
        }
    }
}

// ✅ 發送文字訊息
function sendMessage($chat_id, $text, $mode = null) {
    global $apiURL;
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
    ];
    if ($mode) {
        $data['parse_mode'] = $mode;
    }
    $res = file_get_contents($apiURL . "sendMessage?" . http_build_query($data));
    logToFile("📤 傳送文字：" . $res, "message");
    return $res;
}

// ✅ 發送圖片
function sendPhoto($chat_id, $file_id, $caption = '') {
    global $apiURL;
    $data = [
        'chat_id' => $chat_id,
        'photo' => $file_id,
        'caption' => $caption
    ];
    $res = file_get_contents($apiURL . "sendPhoto?" . http_build_query($data));
    logToFile("📷 發送圖片：" . $res, "message");
}

// ✅ 發送影片
function sendVideo($chat_id, $file_id, $caption = '') {
    global $apiURL;
    $data = [
        'chat_id' => $chat_id,
        'video' => $file_id,
        'caption' => $caption
    ];
    $res = file_get_contents($apiURL . "sendVideo?" . http_build_query($data));
    logToFile("🎬 發送影片：" . $res, "message");
}

// ✅ 轉發私訊到管理群組
function forwardMessageToGroup($from_chat_id, $message_id) {
    global $apiURL, $manager_group_id;
    $data = [
        'chat_id' => $manager_group_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ];
    $res = file_get_contents($apiURL . "forwardMessage?" . http_build_query($data));
    logToFile("🔁 轉發到管理群：" . $res, "forward");
    return $res;
}

// ✅ 儲存使用者對應關係
function saveUserMapping($group_msg_id, $user_id) {
    $file = __DIR__ . "/data/user_map.json";
    if (!file_exists(dirname($file))) {
        mkdir(dirname($file), 0777, true);
    }
    $map = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    $map[$group_msg_id] = $user_id;
    file_put_contents($file, json_encode($map, JSON_PRETTY_PRINT));
}

// ✅ 查找對應使用者
function getMappedUserId($group_msg_id) {
    $file = __DIR__ . "/data/user_map.json";
    if (!file_exists($file)) return null;
    $map = json_decode(file_get_contents($file), true);
    return $map[$group_msg_id] ?? null;
}

// ✅ 日誌記錄
function logToFile($data, $filename = "general") {
    $dir = __DIR__ . "/logs";
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
    $path = "$dir/{$filename}_" . date("Ymd") . ".log";
    file_put_contents($path, date("[Y-m-d H:i:s] ") . $data . "\n", FILE_APPEND);
}
?>


