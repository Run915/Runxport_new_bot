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

logToFile("📨 公告接收到的訊息類型：" . json_encode(array_keys($msg)));

$manager_group_id = -1002143413473;
$client_group_ids = [-1002363718529]; // 可加更多群組 ID

// ✅ 私訊歡迎
if (isset($msg['text']) && $msg['text'] === '/start' && $chat_type === 'private') {
    sendMessage($user_id, "🌟 各位蒞臨潤匯港的貴賓你好\n有任何匯率相關的問題，請私訊我，我們將盡快為您服務！");
    exit;
}

// ✅ 公告處理（來自管理群組）
if ($chat_id == $manager_group_id && strpos($text, '/公告') === 0) {
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

        // ⏺️ 不論哪種型態都記錄 user_map
        saveUserMapping($msg['message_id'], $msg['from']['id']);
    }
    exit;
}

// ✅ 客戶私訊 → 轉發到管理群組，並記錄對應 message_id → user_id
if ($chat_type === 'private' && $chat_id == $user_id) {
    $first_name = $msg['from']['first_name'] ?? '匿名';
    $username = $msg['from']['username'] ?? '';
    $from_name = $username ? "@$username（$first_name）" : $first_name;

    // 轉發原始訊息（保留可回覆）
    $forward_data = [
        'chat_id' => $manager_group_id,
        'from_chat_id' => $chat_id,
        'message_id' => $msg['message_id']
    ];
    $result = sendRequest("https://api.telegram.org/bot" . BOT_TOKEN . "/forwardMessage", $forward_data);

    if (isset($result)) {
        saveUserMapping($result, $user_id);
    }

    // 顯示來源使用者名稱
    sendMessage($manager_group_id, "💬 來自 {$from_name}");
    exit;
}

// ✅ 客服群組回覆訊息 → 回傳給原本私訊的客戶
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













