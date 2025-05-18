<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Method Not Allowed");
}

$token = getenv("BOT_TOKEN");
if (!$token) {
    logToFile("\u274c BOT_TOKEN \u672a\u8a2d\u5b9a", "error");
    exit("BOT_TOKEN \u672a\u8a2d\u5b9a");
}

$apiURL = "https://api.telegram.org/bot$token/";

$manager_group_id = -1002143413473;
$customer_group_ids = [
    -4894662524
];

$update = json_decode(file_get_contents("php://input"), true);
if (!$update) {
    logToFile("\u26a0\ufe0f \u7121\u6548 webhook \u8acb\u6c42", "webhook");
    exit;
}
logToFile("\u2705 \u6536\u5230 webhook：" . json_encode($update), "webhook");

if (isset($update["message"])) {
    $message = $update["message"];
    $chat_id = $message["chat"]["id"];
    $user_id = $message["from"]["id"];
    $message_id = $message["message_id"];
    $text = $message["text"] ?? null;

    if ($text === "/start") {
        $welcome = "\u2728\u5404\u4f4d\u84cb\u81e8\u6f64\u532f\u6e2f\u7684\u8cb4\u8cd3\u4f60\u597d\n\u6709\u4efb\u4f55\u532f\u7387\u76f8\u95dc\u7684\u554f\u984c\uff0c\u8acb\u79c1\u8a0a\u6211\uff0c\u6211\u5011\u5c07\u76f8\u7b49\u70ba\u60a8\u670d\u52d9\uff01";
        sendMessage($chat_id, $welcome);
        exit;
    }

    if ($chat_id == $manager_group_id && isset($text) && strpos($text, "/\u516c\u544a") === 0) {
        $caption = trim(preg_replace('/^\/\u516c\u544a\s*/u', '', $text));
        logToFile("\ud83c\udf1f \u8655\u7406\u516c\u544a\uff1a$caption", "debug");

        foreach ($customer_group_ids as $target_id) {
            if (isset($message["photo"])) {
                logToFile("\ud83d\uddbc\ufe0f \u5075\u6e2c\u5230\u5716\u7247\u516c\u544a", "debug");
                $photo = end($message["photo"])["file_id"];
                sendPhoto($target_id, $photo, $caption);
            } elseif (isset($message["video"])) {
                $video = $message["video"]["file_id"];
                sendVideo($target_id, $video, $caption);
            } else {
                sendMessage($target_id, "\ud83d\udce2 $caption");
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
            }
        }
    }

    if ($chat_id == $manager_group_id && isset($message["reply_to_message"])) {
        $reply_to_id = $message["reply_to_message"]["message_id"];
        $target_user_id = getMappedUserId($reply_to_id);
        if ($target_user_id) {
            if (isset($message["voice"])) {
                logToFile("\u26d4\ufe0f \u5ffd\u7565\u8a9e\u97f3", "reply");
            } else {
                $reply_text = $text ?? '[\u975e\u6587\u5b57\u5167\u5bb9]';
                sendMessage($target_user_id, "\ud83d\udcac \u6f64\u532f\u6e2f\u5ba2\u670d\u56de\u8986\uff1a\n" . $reply_text);
            }
        } else {
            logToFile("\u26a0\ufe0f \u627e\u4e0d\u5230\u5c0d\u61c9 user_id", "error");
        }
    }
}

function sendMessage($chat_id, $text, $mode = null) {
    global $apiURL;
    $data = ['chat_id' => $chat_id, 'text' => $text];
    if ($mode) $data['parse_mode'] = $mode;
    $res = file_get_contents($apiURL . "sendMessage?" . http_build_query($data));
    logToFile("\ud83d\udce4 sendMessage\uff1a$res", "message");
    return $res;
}

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
    logToFile("\ud83d\udcf7 sendPhoto\uff1a$res", "message");
    $decoded = json_decode($res, true);
    if (!$decoded || !$decoded['ok']) {
        logToFile("\u274c \u5716\u7247\u767c\u9001\u5931\u6557\uff1a" . $res, "error");
    }
}

function sendVideo($chat_id, $file_id, $caption = '') {
    global $apiURL;
    $data = ['chat_id' => $chat_id, 'video' => $file_id, 'caption' => $caption];
    $res = file_get_contents($apiURL . "sendVideo?" . http_build_query($data));
    logToFile("\ud83c\udfae sendVideo\uff1a$res", "message");
}

function forwardMessageToGroup($from_chat_id, $message_id) {
    global $apiURL, $manager_group_id;
    $data = ['chat_id' => $manager_group_id, 'from_chat_id' => $from_chat_id, 'message_id' => $message_id];
    $res = file_get_contents($apiURL . "forwardMessage?" . http_build_query($data));
    logToFile("\ud83d\udd01 forwardMessage\uff1a$res", "forward");
    return $res;
}

function saveUserMapping($group_msg_id, $user_id) {
    $file = __DIR__ . "/../data/user_map.json";
    if (!file_exists(dirname($file))) mkdir(dirname($file), 0777, true);
    $map = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    $map[$group_msg_id] = $user_id;
    file_put_contents($file, json_encode($map, JSON_PRETTY_PRINT));
}

function getMappedUserId($group_msg_id) {
    $file = __DIR__ . "/../data/user_map.json";
    if (!file_exists($file)) return null;
    $map = json_decode(file_get_contents($file), true);
    return $map[$group_msg_id] ?? null;
}

function logToFile($data, $filename = "general") {
    $dir = __DIR__ . "/logs";
    if (!is_dir($dir)) {
        $dir = realpath(__DIR__ . "/../logs"); // fallback
    }
    if (!is_dir($dir)) return;
    $path = "$dir/{$filename}_" . date("Ymd") . ".log";
    file_put_contents($path, date("[Y-m-d H:i:s] ") . $data . "\n", FILE_APPEND);
}
?>



