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

// ✅ 讀取並解析 webhook 請求
$update = json_decode(file_get_contents("php://input"), true);
if (!$update) {
    logToFile("⚠️ 無效的 webhook 請求", "webhook");
    exit;
}
logToFile("✅ 收到 webhook 請求：" . json_encode($update), "webhook");

// ✅ 設定群組 ID
$group_chat_id = -1002143413473;

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

    // ✅ 如果是私人訊息，整則轉發到群組（包含圖片、貼圖等）
    if ($chat_id > 0) {
        forwardMessageToGroup($chat_id, $message_id);
    }

    // ✅ 如果是群組的回覆訊息，轉發回原用戶
    if ($chat_id == $group_chat_id && isset($message["reply_to_message"])) {
        $replied_text = $message["reply_to_message"]["text"] ?? '';
        if (preg_match('/tg:\/\/user\?id=(\d+)/', $replied_text, $matches)) {
            $target_user_id = $matches[1];
            sendMessage($target_user_id, "💬 潤匯港客服回覆：\n" . $text);
        }
    }
}

// ✅ 傳送文字訊息
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
    logToFile("📤 傳送訊息結果：" . $res, "message");
}

// ✅ 轉發整則訊息
function forwardMessageToGroup($from_chat_id, $message_id) {
    global $apiURL, $group_chat_id;
    $data = [
        'chat_id' => $group_chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ];
    $res = file_get_contents($apiURL . "forwardMessage?" . http_build_query($data));
    logToFile("🔁 轉發訊息結果：" . $res, "forward");
}

// ✅ 記錄日誌函式
function logToFile($data, $filename = "general") {
    $dir = __DIR__ . "/logs";
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
    $path = "$dir/{$filename}_" . date("Ymd") . ".log";
    file_put_contents($path, date("[Y-m-d H:i:s] ") . $data . "\n", FILE_APPEND);
}
?>
