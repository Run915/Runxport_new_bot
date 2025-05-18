<?php
// 使用環境變數儲存 BOT_TOKEN
define('BOT_TOKEN', getenv('BOT_TOKEN') ?: 'YOUR_BOT_TOKEN_HERE');
define('API_URL', 'https://api.telegram.org/bot'.BOT_TOKEN.'/');

// 接收 Telegram webhook 請求
$content = file_get_contents("php://input");
$update = json_decode($content, true);

// 記錄請求以除錯
//file_put_contents('webhook.log', date('Y-m-d H:i:s') . " - Method: {$_SERVER['REQUEST_METHOD']}\nContent: $content\n", FILE_APPEND);

// 驗證 webhook 請求
if (!$update || !isset($update['message'])) {
    http_response_code(400);
    error_log('Invalid webhook request: ' . $content);
    exit;
}

// 處理 /start 指令（純文字訊息）
if (isset($update['message']['text']) && $update['message']['text'] === '/start') {
    $chat_id = $update['message']['chat']['id'];
    $text = urlencode(
        "🌈G-int 旅遊管家 - 您最貼心的生活幫手\n".
        "🌟計劃您的夢幻旅行從未如此簡單🌟\n".
        "使用 G-int 旅遊管家App，輕鬆搞定所有旅遊需求，讓您的旅程無憂無慮。\n\n".
        "⭕️ 支付：\n全境掃碼支付，安全快速讓您一掃即付。\n".
        "⭕️ 簽證：\n快速辦理簽證，讓您的準備更省時省心。\n".
        "⭕️ 旅遊：\n豐富景點餐廳，帶您探索在地美食美景。\n".
        "⭕️ 娛樂：\n獨享越式娛樂，讓您的旅行更精彩豐富。\n".
        "⭕️ 預訂：\n優惠訂房訂位，食衣住行育樂輕鬆搞定。\n\n".
        "💯G-int在手 - 越南🇻🇳旅行暢通遊💯"
    );

    $url = API_URL . "sendMessage?chat_id=" . $chat_id . "&text=" . $text;
    doget($url);
}

// 處理 ID 指令
if (isset($update['message']['text']) && $update['message']['text'] === 'ID') {
    $chat_id = $update['message']['chat']['id'];
    $txt = urlencode("Telegram Chat ID：$chat_id");
    $url = API_URL . "sendMessage?chat_id=" . $chat_id . "&text=" . $txt;
    doget($url);
}

function doget($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    $data = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log('cURL error: ' . curl_error($ch));
    }
    curl_close($ch);
    return $data;
}
?>
