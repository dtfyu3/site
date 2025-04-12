<?php
// header('Content-Type: application/json');
require_once __DIR__ . '/tg-bot/telegram_api.php';
error_reporting();
$post_num = 10;
date_default_timezone_set('Europe/Moscow');
function getDbConnection($dbtype = 'mysql')
{
    try {
        // if ($dbtype == 'mysql') {
        $servername = "localhost";
        $user = "root";
        $password = "1q2w3e4r5t6y0";
        $dbname = "test";
        $dsn = "mysql:host=$servername;dbname=$dbname";
        $conn = new PDO($dsn, $user, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }



}
function getPosts($user_id = null, $page = 1, $limit = null, $offset = false, $query = null, $order = null)
{
    $conn = getDbConnection();
    global $post_num;
    if (is_null($limit)) $limit = $post_num;
    if ($offset == false) $start = ($page - 1) * $limit;
    else $start = ($page * $limit) - 1;
    $order = $order == null ? 'desc' : 'asc';
    $response = ['success' => false];
    try {
        if ($user_id != null) {
            $sql = "SELECT cards.id, name AS author, date, edit_date, content, score, vote_type AS user_vote, 
                           (SELECT COUNT(*) FROM card_comments cc WHERE cc.card_id = cards.id) AS comment_count, 
                           (SELECT COUNT(*) FROM user_votes u WHERE u.card_id = cards.id) AS total_votes 
                    FROM cards 
                    INNER JOIN users ON users.id = cards.author 
                    LEFT JOIN user_votes ON cards.id = user_votes.card_id AND user_votes.user_id = :user_id";

            if (is_null($query)) {
                $total_result_sql = "SELECT COUNT(*) AS total_result FROM ($sql ORDER BY date $order) AS a";
                $total_result_stmt = $conn->prepare($total_result_sql);
                $total_result_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $total_result_stmt->execute();
                $total_result = $total_result_stmt->fetch(PDO::FETCH_ASSOC)['total_result'];

                $sql .= " ORDER BY date $order LIMIT :limit OFFSET :start";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->bindParam(':start', $start, PDO::PARAM_INT);
                $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            } else {
                $query = htmlspecialchars($query);
                $query = '%' . $query . '%';
                $total_result_sql = "SELECT COUNT(*) AS total_result FROM ($sql WHERE content LIKE :query ORDER BY date $order) AS a";
                $total_result_stmt = $conn->prepare($total_result_sql);
                $total_result_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $total_result_stmt->bindParam(':query', $query, PDO::PARAM_STR);
                $total_result_stmt->execute();
                $total_result = $total_result_stmt->fetch(PDO::FETCH_ASSOC)['total_result'];

                $sql .= " WHERE content LIKE :query ORDER BY date $order LIMIT :limit OFFSET :start";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->bindParam(':query', $query, PDO::PARAM_STR);
                $stmt->bindParam(':start', $start, PDO::PARAM_INT);
                $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            }
        } else {
            $sql = 'SELECT cards.id, name AS author, date, edit_date, content, score, 
                           (SELECT COUNT(*) FROM card_comments cc WHERE cc.card_id = cards.id) AS comment_count, 
                           (SELECT COUNT(*) FROM user_votes u WHERE u.card_id = cards.id) AS total_votes 
                    FROM cards 
                    INNER JOIN users ON users.id = cards.author';

            if (is_null($query)) {
                $total_result_sql = "SELECT COUNT(*) AS total_result FROM ($sql ORDER BY date $order) AS a";
                $total_result_stmt = $conn->prepare($total_result_sql);
                $total_result_stmt->execute();
                $total_result = $total_result_stmt->fetch(PDO::FETCH_ASSOC)['total_result'];

<<<<<<< HEAD
                $sql .= " ORDER BY date $order LIMIT :limit OFFSET :start";
=======
                $sql .= " ORDER BY date $order LIMIT :limit OFFSET :start"
>>>>>>> 868230b469c4f2ece70cb5ca1d9baa905d4d48d8
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':start', $start, PDO::PARAM_INT);
                $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            } else {
                $query = htmlspecialchars($query);
                $query = '%' . $query . '%';
                $total_result_sql = "SELECT COUNT(*) AS total_result FROM ($sql WHERE content LIKE :query ORDER BY date $order) AS a";
                $total_result_stmt = $conn->prepare($total_result_sql);
                $total_result_stmt->bindParam(':query', $query, PDO::PARAM_STR);
                $total_result_stmt->execute();
                $total_result = $total_result_stmt->fetch(PDO::FETCH_ASSOC)['total_result'];

                $sql .= " WHERE content LIKE :query ORDER BY date $order LIMIT :limit OFFSET :start";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':query', $query, PDO::PARAM_STR);
                $stmt->bindParam(':start', $start, PDO::PARAM_INT);
                $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            }
        }

        $stmt->execute();
        $posts = [];
        $total_pages = ceil($total_result / $limit);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $posts[] = [
                'id' => $row['id'],
                'author' => $row['author'],
                'content' => $row['content'],
                'date' => $row['date'],
                'edit_date' => $row['edit_date'],
                'score' => $row['score'],
                'user_vote' => isset($row['user_vote']) ? $row['user_vote'] : null,
                'comment_count' => $row['comment_count'],
                'total_votes' => $row['total_votes']
            ];
        }

        $response['page'] = $page;
        $response['offset'] = $offset;
        $response['success'] = true;
        $response['start'] = $start;
        $response['limit'] = $limit;
        $response['posts'] = $posts;
        $response['total_pages'] = $total_pages;
        $response['total_result'] = $total_result;
        $stmt->closeCursor();
    } catch (PDOException $e) {
        $response['error'] = "Database error: " . $e->getMessage();
    } finally {
        $conn = null;
    }

    echo json_encode($response);
    return $response;
}
function unvote($conn, $user_id, $card_id, $is_comment)
{
    if (!$is_comment) {
        $votes_table = 'user_votes';
        $card_or_comm_id = 'card_id';
        $card_table = 'cards';
    } else {
        $votes_table = 'comments_votes';
        $card_or_comm_id = 'comment_id';
        $card_table = 'comments';
    }

    try {
        $stmt = $conn->prepare("SELECT vote_type FROM $votes_table WHERE user_id = :user_id AND $card_or_comm_id = :card_id LIMIT 1");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':card_id', $card_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $voteType = $result['vote_type'];
            if ($voteType === 'upvote') {
                $updateStmt = $conn->prepare("UPDATE $card_table SET score = score - 1 WHERE id = :card_id");
            } elseif ($voteType === 'downvote') {
                $updateStmt = $conn->prepare("UPDATE $card_table SET score = score + 1 WHERE id = :card_id");
            }
            if (isset($updateStmt)) {
                $updateStmt->bindParam(':card_id', $card_id, PDO::PARAM_INT);
                $updateStmt->execute();
            }
            $deleteStmt = $conn->prepare("DELETE FROM $votes_table WHERE user_id = :user_id AND $card_or_comm_id = :card_id");
            $deleteStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $deleteStmt->bindParam(':card_id', $card_id, PDO::PARAM_INT);
            $deleteStmt->execute();
            $deleteStmt->closeCursor();
        }
        $stmt->closeCursor();
    } catch (PDOException $e) {
        throw new Exception("Database error in unvote: " . $e->getMessage());
    }
}
function update($user_id, $card_id, $action, $is_comment)
{
    $conn = getDbConnection();
    try {
        $conn->beginTransaction(); // Start a transaction

        if (!$is_comment) {
            $votes_table = 'user_votes';
            $card_or_comm_id = 'card_id';
            $card_table = 'cards';
        } else {
            $votes_table = 'comments_votes';
            $card_or_comm_id = 'comment_id';
            $card_table = 'comments';
        }

        $response = ['success' => false];

        if ($action === 'upvote') {
            unvote($conn, $user_id, $card_id, $is_comment);

            $stmt = $conn->prepare("INSERT INTO $votes_table (user_id, $card_or_comm_id, vote_type) VALUES (:user_id, :card_id, 'upvote')");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':card_id', $card_id, PDO::PARAM_INT);
            $stmt->execute();
            $stmt->closeCursor();
            $updateStmt = $conn->prepare("UPDATE $card_table SET score = score + 1 WHERE id = :card_id");
            $updateStmt->bindParam(':card_id', $card_id, PDO::PARAM_INT);
            $updateStmt->execute();
            $updateStmt->closeCursor();
        } elseif ($action === 'downvote') {
            unvote($conn, $user_id, $card_id, $is_comment);

            $stmt = $conn->prepare("INSERT INTO $votes_table (user_id, $card_or_comm_id, vote_type) VALUES (:user_id, :card_id, 'downvote')");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':card_id', $card_id, PDO::PARAM_INT);
            $stmt->execute();
            $stmt->closeCursor();
            $updateStmt = $conn->prepare("UPDATE $card_table SET score = score - 1 WHERE id = :card_id");
            $updateStmt->bindParam(':card_id', $card_id, PDO::PARAM_INT);
            $updateStmt->execute();
            $updateStmt->closeCursor();
        } elseif ($action === 'unvote') {
            unvote($conn, $user_id, $card_id, $is_comment);
        } else {
            throw new Exception('Invalid action');
        }


        $resultStmt = $conn->prepare("SELECT score FROM $card_table WHERE id = :card_id");
        $resultStmt->bindParam(':card_id', $card_id, PDO::PARAM_INT);
        $resultStmt->execute();
        $newScore = $resultStmt->fetch(PDO::FETCH_ASSOC)['score'];
        $resultStmt->closeCursor();

        $conn->commit(); // Commit the transaction

        $response['success'] = true;
        $response['newScore'] = $newScore;
    } catch (Exception $e) {
        $conn->rollBack(); // Rollback the transaction on error
        $response['error'] = $e->getMessage();
    } finally {
        $conn = null;
    }

    echo json_encode($response);
}


function putPost($user_id, $content)
{
    $conn = getDbConnection();
    $limit = 10;
    $content = strip_tags($content);
    $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    $date =  date('Y-m-d H:i:s');
    $response = ['success' => false];

    try {
        $conn->beginTransaction();

        // Insert the new card
        $stmt = $conn->prepare('INSERT INTO cards (author, content, date) VALUES (:user_id, :content, :date)');
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':content', $content, PDO::PARAM_STR);
        $stmt->bindParam(':date', $date, PDO::PARAM_STR);
        $stmt->execute();
        $stmt->closeCursor();
        $sql = "SELECT cards.id, name AS author, date, edit_date, content, score, vote_type AS user_vote, 
                       (SELECT COUNT(*) FROM card_comments cc WHERE cc.card_id = cards.id) AS comment_count, 
                       (SELECT COUNT(*) FROM user_votes u WHERE u.card_id = cards.id) AS total_votes 
                FROM cards 
                INNER JOIN users ON users.id = cards.author 
                LEFT JOIN user_votes ON cards.id = user_votes.card_id AND user_votes.user_id = :user_id 
                ORDER BY cards.id DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ($row) {
            $response['data'] = [
                'id' => $row['id'],
                'author' => $row['author'],
                'date' => $row['date'],
                'edit_date' => $row['edit_date'],
                'content' => $row['content'],
                'score' => $row['score'],
                'user_vote' => $row['user_vote'],
                'comment_count' => $row['comment_count'],
                'total_votes' => $row['total_votes'],
            ];
        }

        $stmt = $conn->prepare('SELECT COUNT(*) FROM cards');
        $stmt->execute();
        $total_result = $stmt->fetchColumn();
        $stmt->closeCursor();
        $response['total_result'] = $total_result;

        $stmt = $conn->prepare('SELECT name FROM users WHERE id = :user_id');
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $author_name = $stmt->fetchColumn();
        $stmt->closeCursor();
        $response['data']['author'] = $author_name;
        $total_pages = ceil($total_result / $limit);
        $response['total_pages'] = $total_pages;
        $conn->commit();
        $response['success'] = true;
        try {
            $stmt = $conn->query("SELECT chat_id FROM subscribers");
            $subscribers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            die("Ошибка при получении подписчиков: " . $e->getMessage());
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        $response['error'] = "Database error: " . $e->getMessage();
    } finally {
        $conn = null;
    }
    $message = "📢 *Новая запись на сайте!* \n\n" .
        "🔹 *Автор:* $author_name\n" .
        "🔗 *Текст:* \n```\n$content\n```";
        echo json_encode($response);
    // sendTelegramMessage('451508739', $message);
    foreach ($subscribers as $chatId) {
        sendTelegramMessage($chatId, $message);
    }
}
function putComment($user_id, $card_id, $content)
{
    $conn = getDbConnection();
    $content = strip_tags($content);
    $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    $date = date('Y-m-d H:i:s');
    $response = ['success' => false];

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare('INSERT INTO comments (user_id, content, date) VALUES (:user_id, :content, :date)');
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':content', $content, PDO::PARAM_STR);
        $stmt->bindParam(':date', $date, PDO::PARAM_STR);
        $stmt->execute();
        $comment_id = $conn->lastInsertId();
        $stmt->closeCursor();


        $stmt = $conn->prepare('INSERT INTO card_comments (card_id, comment_id) VALUES (:card_id, :comment_id)');
        $stmt->bindParam(':card_id', $card_id, PDO::PARAM_INT);
        $stmt->bindParam(':comment_id', $comment_id, PDO::PARAM_INT);
        $stmt->execute();
        $stmt->closeCursor();


        $stmt = $conn->prepare('SELECT COUNT(*) FROM card_comments WHERE card_id = :card_id');
        $stmt->bindParam(':card_id', $card_id, PDO::PARAM_INT);
        $stmt->execute();
        $comments_count = $stmt->fetchColumn();
        $stmt->closeCursor();

        $conn->commit();

        $response['success'] = true;
        $response['comments_count'] = $comments_count;
        $response['data'] = [
            'id' => $comment_id,
            'content' => $content,
            'date' => $date,
            'score' => 0
        ];
    } catch (PDOException $e) {
        $conn->rollBack();
        $response['error'] = "Database error: " . $e->getMessage();
    } finally {
        $conn = null;
    }

    echo json_encode($response);
}
function delete($card_id = null, $comment_id = null, $comment_list = null)
{
    $response = ['success' => false];
    $conn = getDbConnection();
    $limit = 10;

    try {
        $conn->beginTransaction();

        if (!is_null($card_id)) {
            // Delete card and associated comments
            $stmt = $conn->prepare('DELETE FROM cards WHERE id = :card_id');
            $stmt->bindParam(':card_id', $card_id, PDO::PARAM_INT);
            $stmt->execute();
            $stmt->closeCursor();

            if (!empty($comment_list) && !is_null($comment_list)) {
                $str = implode(',', array_map('intval', $comment_list));
                $stmt = $conn->prepare("DELETE FROM comments WHERE id IN ($str)");
                $stmt->execute();
                $stmt->closeCursor();
            }

            $stmt = $conn->prepare('SELECT COUNT(*) FROM cards');
            $stmt->execute();
            $total = $stmt->fetchColumn();
            $stmt->closeCursor();

            $total_pages = ceil($total / $limit);
            $response['total_pages'] = $total_pages;
        } elseif (!is_null($comment_id)) {
            $stmt = $conn->prepare('DELETE FROM comments WHERE id = :comment_id');
            $stmt->bindParam(':comment_id', $comment_id, PDO::PARAM_INT);
            $stmt->execute();
            $stmt->closeCursor();
        }

        $conn->commit();
        $response['success'] = true;
    } catch (PDOException $e) {
        $conn->rollBack();
        $response['error'] = 'Database error: ' . $e->getMessage();
    } finally {
        $conn = null;
    }

    echo json_encode($response);
}
function getComments($card_id, $user_id)
{
    $conn = getDbConnection();
    $response = ['success' => false];

    try {
        $stmt = $conn->prepare('SELECT c.id, name AS author, content, date, score, vote_type AS user_vote 
                                FROM comments c 
                                LEFT JOIN users ON c.user_id = users.id 
                                LEFT JOIN card_comments cc ON c.id = cc.comment_id
                                LEFT JOIN comments_votes cv ON cv.user_id = :user_id AND cv.comment_id = c.id
                                WHERE cc.card_id = :card_id
                                ORDER BY date DESC');
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':card_id', $card_id, PDO::PARAM_INT);
        $stmt->execute();

        $comments = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $comments[] = [
                'id' => $row['id'],
                'author' => $row['author'],
                'content' => $row['content'],
                'date' => $row['date'],
                'score' => $row['score'],
                'user_vote' => isset($row['user_vote']) ? $row['user_vote'] : null,
            ];
        }
        $stmt->closeCursor();

        $response['success'] = true;
        $response['comments'] = $comments;
    } catch (PDOException $e) {
        $response['error'] = 'Database error: ' . $e->getMessage();
    } finally {
        $conn = null;
    }

    echo json_encode($response);
}
function updateCard($card_id, $content, $is_comment = null)
{
    if (is_null($is_comment)) {
        $conn = getDbConnection();
        $content = strip_tags($content);
        $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        $date =  date('Y-m-d H:i:s');
        $stmt = $conn->prepare('update cards set content = :content, edit_date = :date where id = :card_id');
        $stmt->bindParam(':content', $content, PDO::PARAM_STR);
        $stmt->bindParam(':date', $date, PDO::PARAM_STR);
        $stmt->bindParam(':card_id', $card_id, PDO::PARAM_INT);
        try {
            $stmt->execute();
            $response['success'] = true;
        } catch (Exception $e) {
            $response['error'] = $e->getMessage();
        }
        echo json_encode($response);
    }
}
function getPageCount()
{
    $conn = getDbConnection();
    global $post_num;
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as total_result FROM cards");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_result = $result['total_result'];
        $total_pages = ceil($total_result / $post_num);
        $response['success'] = true;
        $response['page_count'] = $total_pages;
    } catch (PDOException $e) {
        $response['success'] = false;
        $response['error'] = "Database error: " . $e->getMessage();
    }
    $conn = null;
    echo json_encode($response);
}
$data = json_decode(file_get_contents('php://input'), true);
$action = null;
$get_action = null;
if (isset($_GET['get_action'])) $get_action = $_GET['get_action'];
if ($get_action != null) {
    if ($get_action == 'getPosts') {
        // if (isset($data['user_id'])) {  
            $user_id = intval($data['user_id']);
            getPosts($user_id, $data['page'], $data['limit'], $data['offset'], $data['query'], $data['order']);
        // } 
        // else getPosts(page: $data['page'], query: $data['query'], order: $data['order']);
    } elseif ($get_action == 'update' && isset($data['user_id'])) {
        if (isset($data['card_id']) && isset($data['action'])) {
            $user_id = intval($data['user_id']);
            $card_id = intval($data['card_id']);
            $action = $data['action'];
            update($user_id, $card_id, $action, false);
        } elseif (isset($data['comment_id']) && isset($data['action'])) {
            $user_id = intval($data['user_id']);
            $comment_id = intval($data['comment_id']);
            $action = $data['action'];
            update($user_id, $comment_id, $action, true);
        }
    } elseif ($get_action == 'putPost' && isset($data['content'])) {
        putPost(intval($data['user_id']), $data['content']);
    } elseif ($get_action == 'getComments' && isset($data['card_id'])) {
        getComments($data['card_id'], $data['user_id']);
    } elseif ($get_action == 'putComment' && isset($data['card_id']) && isset($data['user_id'])) {
        $user_id = intval($data['user_id']);
        $card_id = intval($data['card_id']);
        $content = $data['content'];
        putComment($user_id, $card_id, $content);
    } elseif ($get_action == 'delete') {
        if (isset($data['comment_id'])) {
            delete(comment_id: intval($data['comment_id']));
        } else {
            delete(card_id: intval($data['card_id']), comment_list: $data['comment_list']);
        }
    } elseif ($get_action == 'updateCard') {
        updateCard($data['card_id'], $data['content']);
    } elseif ($get_action == 'search') {
        getPosts($user_id, $data['page'], $data['limit'], $data['offset'], $data['query'], $data['order']);
    } elseif ($get_action == 'getPageCount') getPageCount();
    else {
        $response = ['error' => 'Invalid action'];
        echo json_encode($response);
    };
}
