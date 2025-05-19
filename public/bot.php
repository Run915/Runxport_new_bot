<?php
require_once "functions.php";

$data = file_get_contents("php://input");
logToFile("[Webhook received]\n" . $data);
$update = json_decode($data, true);
$msg = $update['message'] ?? [];

$user_id = $msg['from']['id'] ?? '';
$chat_id = $msg['chat']['id'] ?? '';
$chat_type = $msg['chat']['type'] ?? '';
$text = $msg['text'] ?? ($msg['caption'] ?? '');
$message_id = $msg['message_id'] ?? '';

// 🧱 防止 bot 自己送出的訊息被 webhook 再次觸發（避免無限公告）
$bot_id = 8199489325; // ← 請填入你 bot 的 ID
if ($user_id == $bot_id) {
    logToFile("🛑 忽略機器人自己的訊息");
    exit;
}

// ✅ 群組 ID 設定
$manager_group_id = -1002143413473;
$client_group_ids = [-1002363718529,
    ]; // 可加入更多群組 ID

// ✅ 私訊歡迎訊息
if ($chat_type === 'private' && $text === '/start') {
    sendMessage($user_id, "🌟 各位蒞臨潤匯港的貴賓你好\n有任何匯率相關的問題，請私訊我，我們將盡快為您服務！");
    exit;
}

// ✅ /公告 功能（處理文字、圖片、影片）＋記憶最近 5 則 message_id
if ($chat_id == $manager_group_id && strpos($text, '/公告') === 0) {
    $cache_file = 'announcement_cache.json';
    $cache = [];

    if (file_exists($cache_file)) {
        $json = file_get_contents($cache_file);
        $cache = json_decode($json, true) ?: [];
    }

    if (in_array($message_id, $cache)) {
        logToFile("⚠️ 跳過重複公告 message_id: {$message_id}");
        exit;
    }

    // 寫入新的 message_id 到 cache，保留最多 5 筆
    $cache[] = $message_id;
    if (count($cache) > 5) {
        $cache = array_slice($cache, -5); // 保留最後 5 筆
    }
    file_put_contents($cache_file, json_encode($cache));

    // 開始發送公告
    $text_content = trim(str_replace('/公告', '', $text));
    $media_caption = $text_content ?: '📢';

    foreach ($client_group_ids as $group_id) {
        if (isset($msg['photo'])) {
            $photo = end($msg['photo'])['file_id'];
            sendPhoto($group_id, $photo, "📢 " . $media_caption);
        } elseif (isset($msg['video'])) {
            $video = $msg['video']['file_id'];
            sendVideo($group_id, $video, "📢 " . $media_caption);
        } elseif (!empty($text_content)) {
            sendMessage($group_id, "📢 " . $text_content);
        }

        saveUserMapping($msg['message_id'], $msg['from']['id']);
        usleep(500000); // 延遲 0.5 秒
    }

    exit;
}

// ✅ 客戶私訊 → 轉發到管理群組
if ($chat_type === 'private' && $chat_id == $user_id) {
    $first_name = $msg['from']['first_name'] ?? '匿名';
    $username = $msg['from']['username'] ?? '';
    $from_name = $username ? "@$username（$first_name）" : $first_name;

    $forward_data = [
        'chat_id' => $manager_group_id,
        'from_chat_id' => $chat_id,
        'message_id' => $message_id
    ];
    $result = sendRequest("https://api.telegram.org/bot" . BOT_TOKEN . "/forwardMessage", $forward_data);

    if (isset($result)) {
        saveUserMapping($result, $user_id);
    }

    exit;
}

// ✅ 客服群組回覆訊息 → 回傳給原私訊客戶
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

    exit;
}














