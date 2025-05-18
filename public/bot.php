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
$client_group_ids = [-1002363718529];

// ✅ 私訊歡迎
if (isset($msg['text']) && $msg['text'] === '/start' && $chat_type === 'private') {
    sendMessage($user_id, "🌟 各位蒞臨潤匯港的貴賓你好\n有任何匯率相關的問題，請私訊我，我們將盡快為您服務！");
    exit;
}

// ✅ 公告處理
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
        // ✅ 不論哪種型態都記錄 user_map
        saveUserMapping($msg['message_id'], $msg['from']['id']);
    }
    exit;
}

// ✅ 客戶私訊 → 轉發至管理群組並記錄對應
if ($chat_type === 'private' && $chat_id == $user_id) {
    $from_name = $msg['from']['first_name'] ?? '用戶';

    if (isset($msg['text'])) {
        $message_id = sendMessage($manager_group_id, "💬 {$from_name} 傳來訊息：\n" . $msg['text']);
        saveUserMapping($message_id, $user_id);
    } elseif (isset($msg['photo'])) {
        $photo = end($msg['photo'])['file_id'];
        $caption = $msg['caption'] ?? '(圖片)';
        $message_id = sendPhoto($manager_group_id, $photo, "🖼️ {$from_name} 發送圖片：\n" . $caption);
        saveUserMapping($message_id, $user_id);
    } elseif (isset($msg['video'])) {
        $video = $msg['video']['file_id'];
        $caption = $msg['caption'] ?? '(影片)';
        $message_id = sendVideo($manager_group_id, $video, "🎞️ {$from_name} 發送影片：\n" . $caption);
        saveUserMapping($message_id, $user_id);
    }
    exit;
}


// ✅ 客戶私訊 → 轉發至管理群組並記錄對應
if ($chat_type === 'private' && $chat_id == $user_id) {
    if (isset($msg['text'])) {
        $message_id = sendMessage($manager_group_id, "💬 客戶來訊：\n" . $msg['text']);
        saveUserMapping($message_id, $user_id);
    } elseif (isset($msg['photo'])) {
        $photo = end($msg['photo'])['file_id'];
        $caption = $msg['caption'] ?? '(圖片)';
        $message_id = sendPhoto($manager_group_id, $photo, "🖼️ 客戶圖片：\n" . $caption);
        saveUserMapping($message_id, $user_id);
    } elseif (isset($msg['video'])) {
        $video = $msg['video']['file_id'];
        $caption = $msg['caption'] ?? '(影片)';
        $message_id = sendVideo($manager_group_id, $video, "🎞️ 客戶影片：\n" . $caption);
        saveUserMapping($message_id, $user_id);
    }
    exit;
}












