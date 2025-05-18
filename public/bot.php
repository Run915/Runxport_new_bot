<?php
require_once "functions.php";

// 讀取 Webhook 傳入資料
$data = file_get_contents("php://input");
file_put_contents("php://stdout", "[Webhook received]\n" . $data . "\n");
$update = json_decode($data, true);
$msg = $update['message'] ?? [];
$user_id = $msg['from']['id'] ?? '';
$chat_id = $msg['chat']['id'] ?? '';
$chat_type = $msg['chat']['type'] ?? '';
$text = $msg['text'] ?? ($msg['caption'] ?? '');

logToFile("📨 公告接收到的訊息類型：" . json_encode(array_keys($msg)));

$manager_group_id = -1002143413473;
$client_group_ids = [-1002363718529];

// ✅ 處理 /start 歡迎訊息
if (isset($msg['text']) && $msg['text'] === '/start' && $chat_type === 'private') {
    sendMessage($user_id, "🌟 各位蒞臨潤匯港的貴賓你好\n有任何匯率相關的問題，請私訊我，我們將盡快為您服務！");
    exit;
}

// ✅ 發送公告：由管理群組傳送指令 /公告 開頭
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
            $message_id = sendMessage($group_id, "📢 " . $text_content);
            saveUserMapping($msg['message_id'], $msg['from']['id']);
        }
    }
    exit;
}

// ✅ 客服群組回覆訊息 → 傳回原私訊者
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
?>












