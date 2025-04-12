<?php

function sendTelegramMessage($chatId, $text, $parseMode = 'Markdown', $keyboard = null)
{
    if (is_null($keyboard)) {
        $keyboard = [
            'keyboard' => [
                ['📋 Получить записи'],
                ['🔔 Подписаться на уведомления', '❌ Отписаться от уведомлений'],
            ],
            'resize_keyboard' => true,  // Оптимизировать размер
            'one_time_keyboard' => false // Не скрывать после нажатия
        ];
    }

    $replyMarkup = json_encode($keyboard);
    $token = getenv('TELEGRAM_TOKEN');
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => $parseMode,
        'reply_markup' => $replyMarkup
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}
