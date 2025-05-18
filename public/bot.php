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
$group_chat_id = -1002143413473; // ← 請換成你自己的群組 ID

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

    // ✅ 處理 /start 指令
    if ($text === "/start") {
        $welcome = "✨各位蒞臨潤匯港的貴賓你好\n有任何匯率相關的問題，請私訊我，我們將盡快為您服務！";
        sendMessage($chat_id, $welcome);
        exit;
    }

    // ✅ 私訊 → 轉發到群組 + 紀錄對照
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

    // ✅ 群組回覆訊息 → 查對照表回傳給原客戶
    if ($chat_id == $group_chat_id && isset($message["reply_to_message"])) {
        $replied_msg_id = $message["reply_to_message"]["message_id"];
        $target_user_id = getMappedUserId($replied_msg_id);
        if ($target_user_id) {
            sendMessage($target_user_id, "💬 潤匯港客服回覆：\n" . $text);
        } else {
            logToFile("⚠️ 無法找到回覆對應的使用者：訊息ID $replied_msg_id", "error");
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
    logToFile("📤 發送訊息結果：" . $res, "message");
    return $res;
}

// ✅ 轉發訊息並回傳回應 JSON
function forwardMessageToGroup($from_chat_id, $message_id) {
    global $apiURL, $group_chat_id;
    $data = [
        'chat_id' => $group_chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ];
    $res = file_get_contents($apiURL . "forwardMessage?" . http_build_query($data));
    logToFile("🔁 轉發訊息結果：" . $res, "forward");
    return $res;
}

// ✅ 記錄對照表（訊息 ID → 使用者 ID）
function saveUserMapping($group_msg_id, $user_id) {
    $file = __DIR__ . "/data/user_map.json";
    if (!file_exists(dirname($file))) {
        mkdir(dirname($file), 0777, true);
    }

    $map = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    $map[$group_msg_id] = $user_id;
    file_put_contents($file, json_encode($map, JSON_PRETTY_PRINT));
}

// ✅ 查詢對應使用者
function getMappedUserId($group_msg_id) {
    $file = __DIR__ . "/data/user_map.json";
    if (!file_exists($file)) return null;

    $map = json_decode(file_get_contents($file), true);
    return $map[$group_msg_id] ?? null;
}

// ✅ 記錄日誌
function logToFile($data, $filename = "general") {
    $dir = __DIR__ . "/logs";
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
    $path = "$dir/{$filename}_" . date("Ymd") . ".log";
    file_put_contents($path, date("[Y-m-d H:i:s] ") . $data . "\n", FILE_APPEND);
}
?>

