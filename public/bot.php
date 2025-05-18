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

if (isset($update["message"])) {
    $message = $update["message"];
    $chat_id = $message["chat"]["id"];
    $user_id = $message["from"]["id"];
    $message_id = $message["message_id"];
    $text = $message["text"] ?? null;

    // ✅ 歡迎訊息
    if ($text === "/start") {
        $welcome = "✨各位蒞臨潤匯港的貴賓你好\n有任何匯率相關的問題，請私訊我，我們將盡快為您服務！";
        sendMessage($chat_id, $welcome);
        exit;
    }

    // ✅ /公告（支援文字與圖片）
    if ($chat_id == $manager_group_id && isset($text) && strpos($text, "/公告") === 0) {
        $caption = trim(preg_replace('/^\/公告\s*/u', '', $text));
        logToFile("🎯 處理公告：$caption", "debug");

        foreach ($customer_group_ids as $target_id) {
            if (isset($message["photo"])) {
                logToFile("🖼️ 偵測到圖片公告", "debug");
                $photo = end($message["photo"])["file_id"];
                sendPhoto($target_id, $photo, $caption);
            } elseif (isset($message["video"])) {
                $video = $message["video"]["file_id"];
                sendVideo($target_id, $video, $caption);
            } else {
                sendMessage($target_id, "📢 $caption");
            }
        }
        exit;
    }

    // ✅ 私訊 → 管理群
    if ($chat_id > 0) {
        $forwarded = forwardMessageToGroup($chat_id, $message_id);
        if ($forwarded) {
            $result = json_decode($forwarded, true);
            if (isset($result['result']['message_id'])) {
                saveUserMapping($result['result']['message_id'], $user_id);
            }
        }
    }

    // ✅ 管理群回覆 → 客戶
    if ($chat_id == $manager_group_id && isset($message["reply_to_message"])) {
        $reply_to_id = $message["reply_to_message"]["message_id"];
        $target_user_id = getMappedUserId($reply_to_id);
        if ($target_user_id) {
            if (isset($message["voice"])) {
                logToFile("⛔ 忽略語音", "reply");
            } else {
                $reply_text = $text ?? '[非文字內容]';
                sendMessage($target_user_id, "💬 潤匯港客服回覆：\n" . $reply_text);
            }
        } else {
            logToFile("⚠️ 找不到對應 user_id", "error");
        }
    }
}

// ✅ 傳送文字
function sendMessage($chat_id, $text, $mode = null) {
    global $apiURL;
    $data = ['chat_id' => $chat_id, 'text' => $text];
    if ($mode) $data['parse_mode'] = $mode;
    $res = file_get_contents($apiURL . "sendMessage?" . http_build_query($data));
    logToFile("📤 sendMessage：$res", "message");
    return $res;
}

// ✅ 傳送圖片（使用 curl）
function sendPhoto($chat_id, $file_id, $caption = '') {
    global $token;
    $url = "https://api.telegram.org/bot$token/sendPhoto";
    $post_fields = [
        'chat_id' => $chat_id,
        'photo' => $file_id,
        'caption' => $caption
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type:multipart/form-data"]);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    $res = curl_exec($ch);
    curl_close($ch);
    logToFile("📷 sendPhoto：$res", "message");

    $decoded = json_decode($res, true);
    if (!$decoded || !$decoded['ok']) {
        logToFile("❌ 圖片發送失敗：" . $res, "error");
    }
}

// ✅ 傳送影片
function sendVideo($chat_id, $file_id, $caption = '') {
    global $apiURL;
    $data = ['chat_id' => $chat_id, 'video' => $file_id, 'caption' => $caption];
    $res = file_get_contents($apiURL . "sendVideo?" . http_build_query($data));
    logToFile("🎬 sendVideo：$res", "message");
}

// ✅ 轉發私訊
function forwardMessageToGroup($from_chat_id, $message_id) {
    global $apiURL, $manager_group_id;
    $data = ['chat_id' => $manager_group_id, 'from_chat_id' => $from_chat_id, 'message_id' => $message_id];
    $res = file_get_contents($apiURL . "forwardMessage?" . http_build_query($data));
    logToFile("🔁 forwardMessage：$res", "forward");
    return $res;
}

// ✅ 儲存與查找 user_id
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

// ✅ Log 機制
function logToFile($data, $filename = "general") {
    $dir = __DIR__ . "/logs";
    if (!file_exists($dir)) mkdir($dir, 0777, true);
    $path = "$dir/{$filename}_" . date("Ymd") . ".log";
    file_put_contents($path, date("[Y-m-d H:i:s] ") . $data . "\n", FILE_APPEND);
}
?>




