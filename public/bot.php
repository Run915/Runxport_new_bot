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
$client_group_ids = [-1002363718529,
    -1002262366712,  // 胖老師
    -1002419330776,  // 高利台
    -1002585834840,  // VNDL
    -1002546589537,  // KN
    -1002543542407,  // 傳奇
    -1002656754349,  // Tiger
    -1002272795617,  // 高利U
    -1002331016366,  // 小魔專區
    -1002667765748,  // MING哥gpay
    -1002611226056,  // 馬來
    -1002583357811,  // 艾迪
    -1002672988972,  // 小蘋果
    -1002660922163,  // Ted
    -1002659789686,  // 樂哥
    -1002692610658,  // VNKS
    -1002609666114,  // 財專區
    -1002577656320,  // BAT-USDT-VND
    -1002654255744,  // NICK07USDT
    -1002654029358,  // 余生白資
    -1002652412828,  // TT專屬
    -1002647024821,  // B哥專屬
    -1002621153417,  // 福多多
    -1002631806173,  // Usdt-jacky168
    -1002622443028,  // 學弟專區
    -1002580971428,  // 天下匯泡泡龍
    -1002526826240,  // 漢哥
    -1002528761432,  // 文欽哥
    -1002478919269,  // 炫
    -1002495229694,  // Lin哥
    -1002593962697,  // 帥爺
    -1002592360915,  // Jerry04usdt
    -1002591998504,  // 照哥
    -1002589448096,  // 不點
    -1002574767082,  // 達哥
    -1002574406922,  // 志哥
    -1002574005956,  // 05韋
    -1002570517982,  // 金塊
    -1002563613963,  // 史第芬
    -1002559271889,  // 永恆
    -1002558279623,  // Rookie韓國兄弟
    -1002494575222,  // 杰倫專用
    -1001445980815,  // VNZN
    -1002605653197,  // 皇專區
    -1002339880435,  // VNXD
    -1002653960122,  // 打工仔
    -1002349352875,  // 樺
    -1002631720380,  // Dong哥
    -1002509693616,  // OTCNAM
    -1002526808563,  // Jacky
    -1002622507901,  // 瓜
    -1002657956350,  // Vndtohkdhk
    -1002538324453,  // 05高
    -1002579658564,  // HRC眼鏡仔
    -1002422641488,  // 大海
    
    ]; // 可加入更多群組 ID

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

        // ⏺️ 記錄對應訊息
        saveUserMapping($msg['message_id'], $msg['from']['id']);

        // 💤 每次延遲 0.5 秒，避免觸發限速
        usleep(500000);
    }

    exit;
}

// ✅ 客戶私訊 → 轉發到管理群組，並記錄對應
if ($chat_type === 'private' && $chat_id == $user_id) {
    $first_name = $msg['from']['first_name'] ?? '匿名';
    $username = $msg['from']['username'] ?? '';
    $from_name = $username ? "@$username（$first_name）" : $first_name;

    $forward_data = [
        'chat_id' => $manager_group_id,
        'from_chat_id' => $chat_id,
        'message_id' => $msg['message_id']
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













