<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Method Not Allowed");
}

$token = getenv("BOT_TOKEN");
if (!$token) {
    logToFile("❌ BOT_TOKEN 未設定", "error");
    exit("BOT_TOKEN 未設定");
}

$apiURL = "https://api.telegram.org/bot$token/";

// ✅ 群組設定
$manager_group_id = -1002143413473;
$customer_group_ids = [
    -4894662524
];

$update = json_decode(file_get_contents("php://input"), true);
if (!$update) {
    logToFile("⚠️ 無效 webhook 請求", "webhook");
    exit;
}
logToFile("✅ 收到 webhook：" . json_encode($update), "webhook");

// ✅ 處理訊息
if (isset($update["message"])) {
    $message = $update["message"];
    $chat_id = $message["chat"]["id"];
    $user_id = $message["from"]["id"];
    $message_id = $message["message_id"];
    $text = $message["text"] ?? null;

    // ✅ /start 指令（僅限私訊）
    if ($text === "/start") {
        $welcome = "✨各位蒞臨潤匯港的貴賓你好\n有任何匯率相關的問題，請私訊我，我們將盡快為您服務！";
        sendMessage($chat_id, $welcome);
        exit;
    }

    // ✅ /公告：文字或圖片公告（支援換行）
    if ($chat_id == $manager_group_id && isset($text) && strpos($text, "/公告") === 0) {
        $caption = trim(preg_replace('/^\/公告\s*/u', '', $text));
        logToFile("🎯 進入公告處理：$caption", "debug");

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

    // ✅ 客戶私訊 → 轉發給管理群
    if ($chat_id > 0) {
        $forwarded_msg = forwardMessageToGroup($chat_id, $message_id);
        if ($forwarded_msg) {
            $data = json_decode($forwarded_msg, true);
            if (isset($data['result']['message_id'])) {
                saveUserMapping($data['result']['message_id'], $user_id);
            }
        }
    }

    // ✅ 群組回覆 → 回傳給對應私訊客戶
    if ($chat_id == $manager_group_id && isset($message["reply_to_message"])) {
        $reply_to_id = $message["reply_to_message"]["message_id"];
        $target_user_id = getMappedUserId($reply_to_id);
        if ($target_user_id) {
            if (isset($message["voice"])) {
                logToFile("⛔ 忽略語音回覆", "reply");
            } else {
                $reply_text = $text ?? '[非文字內容]';
                sendMessage($target_user_id, "💬 潤匯港客服回覆：\n" . $reply_text);
            }
        } else {
            logToFile("⚠️ 找不到對應使用者 for $reply_to_id", "error");
        }
    }
}

// ✅ 發送文字
function sendMessage($chat_id, $text, $mode = null) {
    global $apiURL;
    $data = ['chat_id' => $chat_id, 'text' => $text];
    if ($mode) $data['parse_mode'] = $mode;
    $res = file_get_contents($apiURL . "sendMessage?" . http_build_query($data));
    logToFile("📤 sendMessage：$res", "message");
    return $res;
}

// ✅ 發送圖片
function sendPhoto($chat_id, $file_id, $caption = '') {
    global $apiURL;
    $data = ['chat_id' => $chat_id, 'photo' => $file_id, 'caption' => $caption];
    $url = $apiURL . "sendPhoto?" . http_build_query($data);
    $res = file_get_contents($url);
    logToFile("📷 sendPhoto：URL=$url\n結果=$res", "message");
}

// ✅ 發送影片
function sendVideo($chat_id, $file_id, $caption = '') {
    global $apiURL;
    $data = ['chat_id' => $chat_id, 'video' => $file_id, 'caption' => $caption];
    $res = file_get_contents($apiURL . "sendVideo?" . http_build_query($data));
    logToFile("🎬 sendVideo：$res", "message");
}

// ✅ 轉發訊息
function forwardMessageToGroup($from_chat_id, $message_id) {
    global $apiURL, $manager_group_id;
    $data = [
        'chat_id' => $manager_group_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ];
    $res = file_get_contents($apiURL . "forwardMessage?" . http_build_query($data));
    logToFile("🔁 forwardMessage：$res", "forward");
    return $res;
}

// ✅ 使用者對應記錄
function saveUserMapping($group_msg_id, $user_id) {
    $file = __DIR__ . "/data/user_map.json";
    if (!file_exists(dirname($file))) mkdir(dirname($file), 0777, true);
    $map = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    $map[$group_msg_id] = $user_id;
    file_put_contents($file, json_encode($map, JSON_PRETTY_PRINT));
}

function getMappedUserId($group_msg_id) {
    $file = __DIR__ . "/data/user_map.json";
    if (!file_exists($file)) return null;
    $map = json_decode(file_get_contents($file), true);
    return $map[$group_msg_id] ?? null;
}

// ✅ log 記錄
function logToFile($data, $filename = "general") {
    $dir = __DIR__ . "/logs";
    if (!file_exists($dir)) mkdir($dir, 0777, true);
    $path = "$dir/{$filename}_" . date("Ymd") . ".log";
    file_put_contents($path, date("[Y-m-d H:i:s] ") . $data . "\n", FILE_APPEND);
}
?>



