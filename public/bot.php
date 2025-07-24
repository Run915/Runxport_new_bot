<?php
<?php
// ✅ 安全驗證：只允許 Telegram 官方請求
if (!isset($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN']) || $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] !== 'run789azsx') {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

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
$bot_id = 8199489325;
if ($user_id == $bot_id) {
    logToFile("🛑 忽略機器人自己的訊息");
    exit;
}

// ✅ 群組 ID 設定
$manager_group_id = -1002143413473;
$admin_user_ids = [
    5198507553, // 您的 ID
    545162861, // IVAN ID
    5136922793, // 詠 ID
];
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
    -1002422641488,  // 大海
    -1002256943526,  // 美侖翁哩U
    -1002351309490,  // 老虎
    -1002571330054,  // 環球一人
    -1002561643091,  // 勳
    
    ];

// ✅ 私訊歡迎訊息
if ($chat_type === 'private' && $text === '/start') {
    sendMessage($user_id, "🌟 各位蒞臨潤匯港的貴賓你好\n有任何匯率相關的問題，請私訊我，我們將盡快為您服務！");
    exit;
}

// ✅ /公告 功能：支援文字、圖片、影片公告，並記錄處理過的 message_id
if ($chat_id == $manager_group_id && strpos($text, '/公告') === 0) {
    // 限制只有 admin_user_ids 才能發公告
    if (!in_array($user_id, $admin_user_ids)) {
        logToFile("❌ 非授權用戶嘗試發送公告：{$user_id}");
        exit;
    }

    $cache_file = 'announcement_cache.json';
    $cache = [];

    // 讀取已處理記錄
    if (file_exists($cache_file)) {
        $json = file_get_contents($cache_file);
        $cache = json_decode($json, true) ?: [];
    }

    $now = time();
    $expired = 86400; // 24 小時

    // 自動清除 24 小時前的紀錄
    foreach ($cache as $id => $timestamp) {
        if ($now - $timestamp > $expired) {
            unset($cache[$id]);
        }
    }

    // 已處理過的 message_id：跳過
    if (isset($cache[$message_id])) {
        logToFile("⚠️ 跳過重複公告 message_id: {$message_id}");
        exit;
    }

    // 寫入這次處理的 message_id
    $cache[$message_id] = $now;
    file_put_contents($cache_file, json_encode($cache));

    // 正式公告處理
    $text_content = trim(str_replace('/公告', '', $text));
    $media_caption = $text_content ?: '📢【公告通知】';

    foreach ($client_group_ids as $group_id) {
        if (isset($msg['photo'])) {
            $photo = end($msg['photo'])['file_id'];
            sendPhoto($group_id, $photo, "📢【公告通知】\n" . $media_caption);
        } elseif (isset($msg['video'])) {
            $video = $msg['video']['file_id'];
            sendVideo($group_id, $video, "📢【公告通知】\n" . $media_caption);
        } elseif (!empty($text_content)) {
            sendMessage($group_id, "📢【公告通知】\n" . $text_content);
        }

        saveUserMapping($msg['message_id'], $msg['from']['id']);
        usleep(500000);
    }

    exit;
}

// ✅ 客戶私訊 → 轉發到管理群組
if ($chat_type === 'private' && $chat_id == $user_id) {
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

// ✅ 客服群組回覆 → 回傳給原私訊客戶
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















