<?php
require_once __DIR__ . '/../api.php';
require_once __DIR__ . '/telegram_api.php';

$input = json_decode(file_get_contents('php://input'), true);
$secretToken = getenv("SECRET_TOKEN");
$headers = getallheaders();
if (!isset($headers['X-Telegram-Bot-Api-Secret-Token']) && $headers['X-Telegram-Bot-Api-Secret-Token'] !== $secretToken) {
    http_response_code(403);
    die('Access denied');
}
//handle callback
if (isset($input['callback_query'])) {
    $callback = $input['callback_query'];
    $chatId = $callback['message']['chat']['id'];
    $data = $callback['data'];

    if (preg_match('/^get_page_(\d+)$/', $data, $matches)) {
        $page = (int)$matches[1];
        sendPaginatedPosts($chatId, $page);
    }
    file_get_contents("https://api.telegram.org/bot" . getenv("TELEGRAM_TOKEN") . "/answerCallbackQuery?callback_query_id=" . $callback['id']);
    exit;
}

//handle message
if (isset($input['message'])) {
    $chatId = $input['message']['chat']['id'];
    $text = $input['message']['text'];
    $username = $input['message']['chat']['username'] ?? null;
    // Обрабатываем команды
    if ($text === '/start') {
        $reply = "Привет! Я бот для вашего сайта. Подпишитесь на уведомления /subscribe. Используйте /help для списка команд.";
    } elseif ($text === '/help') {
        $reply = "Доступные команды:\n/get - Получить записи с сайта\n/subscribe - Подписаться на уведомления\n/unsubscribe - Отписаться от уведомлений";
    } elseif ($text === '📋 Получить записи' || $text === '/get') {
       sendPaginatedPosts($chatId);
       exit;
    } elseif ($text === '/subscribe' || $text === '🔔 Подписаться на уведомления') {
        try {
            $conn = getDbConnection();
            $stmt = $conn->prepare("SELECT 1 FROM subscribers WHERE chat_id = :chat_id");
            $stmt->bindParam(':chat_id', $chatId, PDO::PARAM_INT);
            $stmt->execute();
            $exists = $stmt->fetchColumn();
            if (!$exists) {
                $stmt = $conn->prepare("INSERT INTO subscribers (chat_id, username) VALUES (:chat_id, :username)");
                $stmt->bindParam(':chat_id', $chatId, PDO::PARAM_INT);
                $stmt->bindParam(':username', $username, PDO::PARAM_STR);
                $stmt->execute();
                $reply = "✅ Вы подписаны на уведомления!";
            } else {
                $reply = "🔔 Вы уже подписаны.";
            }
        } catch (PDOException $e) {
            $reply = "❌ Ошибка: " . $e->getMessage();
        }
    } elseif ($text === '/unsubscribe' || $text === '❌ Отписаться от уведомлений') {
        try {
            $conn = getDbConnection();
            $stmt = $conn->prepare("DELETE FROM subscribers WHERE chat_id = :chat_id");
            $stmt->bindParam(':chat_id', $chatId, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->rowCount();

            $reply = $rows > 0
                ? "❌ Вы отписались от уведомлений."
                : "ℹ️ Вы не были подписаны.";
        } catch (PDOException $e) {
            $reply = "🚫 Ошибка: " . $e->getMessage();
        }
    } else {
        $reply = "Неизвестная команда {$text}. Напишите /help для помощи.";
    }

    sendTelegramMessage($chatId, $reply);
    exit;
}
function sendPaginatedPosts($chatId, int $page = 1){
    $limit = 3;
    $data = getPosts(limit: $limit, page:$page);
    $total_pages = $data['total_pages'];
    $reply = "*📝 Последние записи (Страница {$page}/{$total_pages}):*\n\n";
    if (isset($data['posts'])) {
        try {
            foreach ($data['posts'] as $post) {
               $reply .= formAMessage($post);
            }
        } catch (Exception $e) {
            $reply = "❌ Не удалось получить записи." . $e->getMessage();
        }
    } else {
        $reply = "❌ Не удалось получить записи.";
    }
    $keyboard = [];
    if ($page > 1) {
        // Кнопка "Назад", если страница больше 1
        $keyboard[] = [
            'text' => '◀️ Назад',
            'callback_data' => 'get?page_' . ($page - 1),
        ];
    }
    if ($page < $data['total_pages']) {
        // Кнопка "Вперед", если есть еще страницы
        $keyboard[] = [
            'text' => 'Вперед ▶️',
            'callback_data' => 'get_page_' . ($page + 1),
        ];
    }
    if (!empty($keyboard)) {
        $inlineKeyboard = [
            'inline_keyboard' => [$keyboard],
        ];
    }
    // $inlineKeyboard = [
    //     'inline_keyboard' => [[
    //         ['text' => '⬅️ Назад', 'callback_data' => 'get_page_' . max(1, $page - 1)],
    //         ['text' => '➡️ Вперед', 'callback_data' => 'get_page_' . ($page + 1)]
    //     ]]
    // ];
    echo ($reply);
    sendTelegramMessage($chatId, $reply, $inlineKeyboard);
}
