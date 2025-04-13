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
function formAMessage($post)
{
    $reply = "";
    $author = htmlspecialchars($post['author'] ?? null);
    $dateString = htmlspecialchars($post['date'] ?? null);
    $date = new DateTime($dateString) ?? null;
    $date = $date->format('j F Y, H:i') ?? null;
    $likes = htmlspecialchars($post['score'] ?? null);
    $comments = htmlspecialchars($post['comment_count'] ?? null);
    $content = htmlspecialchars($post['content'] ?? null);
    if (mb_strlen($content) > 500) {
        $content = mb_substr($content, 0, 500) . "…";
    }
    $content = "```\n" . htmlspecialchars($post['content']) . "\n```";
    $reply .= "👤 *{$author}*\n";
    $reply .= "📅 _{$date}_\n";
    $reply .= "{$content}";
    $reply .= "*Рейтинг:* " . $likes . "\n";
    $reply .= "*Комментариев:* " . $comments . "\n\n";
    return $reply;
}
