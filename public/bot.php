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
    $caption = $message["caption"] ?? null;
    $commandText = $text ?? $caption;

    if ($text === "/start") {
        $welcome = "✨ 各位蒞臨潤匯港的貴賓你好\n有任何匯率相關的問題，請私訊我，我們將盡快為您服務！";
        sendMessage($chat_id, $welcome);
        exit;
    }

    if ($chat_id == $manager_group_id && isset($commandText) && strpos($commandText, "/公告") === 0) {
        $captionText = trim(preg_replace('/^\/公告\s*/u', '', $commandText));
        logToFile("🎯 處理公告：$captionText", "debug");
        sendMessage($chat_id, "📢 公告處理中：$captionText");

        foreach ($customer_group_ids as $target_id) {
            if (isset($message["photo"])) {
                logToFile("🖼️ 偵測到圖片公告", "debug");
                $photo = end($message["photo"])["file_id"];
                sendMessage($chat_id, "🧪 即將發送圖片公告到群組 $target_id\n圖片ID: $photo");
                sendPhoto($target_id, $photo, $captionText, $chat_id);
            } elseif (isset($message["video"])) {
                $video = $message["video"]["file_id"];
                sendVideo($target_id, $video, $captionText);
            } else {
                sendMessage($target_id, "📢 $captionText");
            }
        }
        exit;
    }

    if ($chat_id > 0) {
        $forwarded = forwardMessageToGroup($chat_id, $message_id);
        if ($forwarded) {
            $result = json_decode($forwarded, true);
            if (isset($result['result']['message_id'])) {
                saveUserMapping($result['result']['message_id'], $user_id);
                logToFile("💾 已記錄對應關係 message_id {$result['result']['message_id']} → user_id $user_id", "reply");
            }
        }
    }

    if ($chat_id == $manager_group_id && isset($message["reply_to_message"])) {
        $reply_to_id = $message["reply_to_message"]["message_id"];
        logToFile("🔍 嘗試取得對應 user_id for message_id $reply_to_id", "reply");
        $target_user_id = getMappedUserId($reply_to_id);

        if ($target_user_id) {
            if (isset($message["voice"])) {
                logToFile("⛔ 忽略語音", "reply");
            } else {
                $reply_text = $text ?? '[非文字內容]';
                sendMessage($target_user_id, "💬 潤匯港客服回覆：\n" . $reply_text);
                sendMessage($chat_id, "✅ 已成功回覆用戶 $target_user_id");
            }
        } else {
            logToFile("⚠️ 找不到對應使用者 for message_id $reply_to_id", "error");
            sendMessage($chat_id, "⚠️ 找不到對應使用者，請確認是否是回覆機器人轉發的訊息。");
        }
    }
}

function sendMessage($chat_id, $text, $mode = null) {
    global $apiURL;
    $data = ['chat_id' => $chat_id, 'text' => $text];
    if ($mode) $data['parse_mode'] = $mode;
    $res = file_get_contents($apiURL . "sendMessage?" . http_build_query($data));
    logToFile("📤 sendMessage：$res", "message");
    return $res;
}

function sendPhoto($chat_id, $file_id, $caption = '', $debug_chat_id = null) {
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
        if ($debug_chat_id) {
            sendMessage($debug_chat_id, "❌ 發送圖片到 $chat_id 失敗\n錯誤訊息：" . ($decoded['description'] ?? '無回應'));
        }
    } else {
        if ($debug_chat_id) {
            sendMessage($debug_chat_id, "✅ 圖片公告成功送出到群組 $chat_id");
        }
    }
}

function sendVideo($chat_id, $file_id, $caption = '') {
    global $apiURL;
    $data = ['chat_id' => $chat_id, 'video' => $file_id, 'caption' => $caption];
    $res = file_get_contents($apiURL . "sendVideo?" . http_build_query($data));
    logToFile("🎬 sendVideo：$res", "message");
}

function forwardMessageToGroup($from_chat_id, $message_id) {
    global $apiURL, $manager_group_id;
    $data = ['chat_id' => $manager_group_id, 'from_chat_id' => $from_chat_id, 'message_id' => $message_id];
    $res = file_get_contents($apiURL . "forwardMessage?" . http_build_query($data));
    logToFile("🔁 forwardMessage：$res", "forward");
    return $res;
}

function saveUserMapping($group_msg_id, $user_id) {
    $file = __DIR__ . "/../data/user_map.json";
    if (!file_exists(dirname($file))) mkdir(dirname($file), 0777, true);
    $map = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    $map[$group_msg_id] = $user_id;
    file_put_contents($file, json_encode($map, JSON_PRETTY_PRINT));
    logToFile("📁 user_map 寫入成功：$group_msg_id → $user_id", "reply");
}

function getMappedUserId($group_msg_id) {
    $file = __DIR__ . "/../data/user_map.json";
    if (!file_exists($file)) {
        logToFile("❌ user_map 檔案不存在", "reply");
        return null;
    }
    $map = json_decode(file_get_contents($file), true);
    return $map[$group_msg_id] ?? null;
}

function logToFile($data, $filename = "general") {
    $dir = __DIR__ . "/logs";
    if (!is_dir($dir)) {
        $dir = realpath(__DIR__ . "/../logs");
    }
    if (!is_dir($dir)) return;
    $path = "$dir/{$filename}_" . date("Ymd") . ".log";
    file_put_contents($path, date("[Y-m-d H:i:s] ") . $data . "\n", FILE_APPEND);
}
?>






