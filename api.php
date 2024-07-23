<?php
// header('Content-Type: application/json');
error_reporting();
function getDbConnection()
{
    $servername = "localhost";
    $username = "root";
    $password = "1q2w3e4r5t6y0";
    $dbname = "test";

    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}
function getPosts($user_id = null, $page = 1)
{
    $conn = getDbConnection();
    $limit = 10;
    $start = ($page - 1) * $limit;
    $limit = 10 * $page;
    if ($user_id != null) {
        $sql = "select cards.id, name as author, date, content, score, vote_type as user_vote from cards
        inner join users
        on users.id = cards.author
        left join user_votes on cards.id = user_votes.card_id and user_votes.user_id = ?
        order by date desc limit ?, ?";
    } else $sql = 'select cards.id, name as author, date, content, score from cards inner join users on users.id = cards.author order by date desc limit ?, ?';
    $posts = array();

    $response = ['success' => false];
    if ($stmt = $conn->prepare($sql)) {
        if (is_null($user_id)) $stmt->bind_param('ii', $start, $limit);
        else $stmt->bind_param('iii', $user_id, $start, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $total_result = $conn->query('select count(*) as total from cards')->fetch_assoc()['total'];
        $total_pages = ceil($total_result / $limit);
        while ($row = $result->fetch_assoc()) {
            $posts[] = [
                'id' => $row['id'],
                'author' => $row['author'],
                'content' => $row['content'],
                'date' => $row['date'],
                'score' => $row['score'],
                'user_vote' => isset($row['user_vote']) ? $row['user_vote'] : null
            ];
        }
        $response['success'] = true;
        // $response['userId'] = $user_id;
        $response['posts'] = $posts;
        $response['total_pages'] = $total_pages;
        $response['total_result'] = $total_result;
        $stmt->close();
    } else {
        $response['error'] = 'Query failed: ' . $conn->error;
    }
    $conn->close();

    echo json_encode($response);
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
    $result = $conn->query("SELECT vote_type FROM $votes_table WHERE user_id = $user_id AND $card_or_comm_id = $card_id LIMIT 1");
    if ($result->num_rows > 0) {
        $voteType = $result->fetch_assoc()['vote_type'];
        if ($voteType === 'upvote') {
            $conn->query("UPDATE $card_table SET score = score - 1 WHERE id = $card_id");
        } elseif ($voteType === 'downvote') {
            $conn->query("UPDATE $card_table SET score = score + 1 WHERE id = $card_id");
        }
        $stmt = $conn->prepare("DELETE FROM $votes_table WHERE user_id = ? AND $card_or_comm_id = ?");
        $stmt->bind_param('ii', $user_id, $card_id);
        $stmt->execute();
    }
}
function update($user_id, $card_id, $action, $is_comment)
{
    $conn = getDbConnection();
    $conn->begin_transaction();
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
    try {
        if ($action === 'upvote') {
            unvote($conn, $user_id, $card_id, $is_comment);
            $stmt = $conn->prepare("INSERT INTO $votes_table (user_id, $card_or_comm_id, vote_type) VALUES (?, ?, 'upvote')");
            $stmt->bind_param('ii', $user_id, $card_id);
            $stmt->execute();
            $conn->query("UPDATE $card_table SET score = score + 1 WHERE id = $card_id");
        } elseif ($action === 'downvote') {
            unvote($conn, $user_id, $card_id, $is_comment);
            $stmt = $conn->prepare("INSERT INTO $votes_table (user_id, $card_or_comm_id, vote_type) VALUES (?, ?, 'downvote')");
            $stmt->bind_param('ii', $user_id, $card_id);
            $stmt->execute();
            $conn->query("UPDATE $card_table SET score = score - 1 WHERE id = $card_id");
        } elseif ($action === 'unvote') {
            unvote($conn, $user_id, $card_id, $is_comment);
        } else {
            throw new Exception('Invalid action');
        }
        $conn->commit();
        $result = $conn->query("SELECT score FROM $card_table WHERE id = $card_id");
        $newScore = $result->fetch_assoc()['score'];

        $response['success'] = true;
        $response['newScore'] = $newScore;
    } catch (Exception $e) {
        $conn->rollback();
        $response['error'] = $e->getMessage();
    }
    $conn->close();
    echo json_encode($response);
}


function putPost($user_id, $content)
{
    $conn = getDbConnection();
    $content = strip_tags($content);
    $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    $date =  date('Y-m-d H:i:s');
    $stmt = $conn->prepare('insert into cards (author, content, date) values (?,?,?)');
    $stmt->bind_param('iss', $user_id, $content, $date);
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['data'] = [
            'id' => $stmt->insert_id,
            'content' => $content,
            'date' => $date,
            'score' => 0,
            'user_vote' => null,
        ];
    } else {
        $response['error'] = 'Database error: ' . $stmt->error;
    }
    $stmt = $conn->prepare('select name from users where id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $response['data']['author'] = $result->fetch_column();
    $stmt->close();
    $conn->close();
    echo json_encode($response);
}
function getComments($card_id)
{
    $conn = getDbConnection();
    $stmt = $conn->prepare('select c.id, name as author, content, date, score,vote_type as user_vote 
from comments c 
left join users on c.user_id = users.id 
left join card_comments cc on card_id = cc.card_id
left join comments_votes cv on cv.user_id = users.id and cv.comment_id = c.id
where cc.card_id = ?
order by date desc');
    $stmt->bind_param('i', $card_id);
    if ($stmt->execute()) {
        $comments = array();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $comments[] = [
                'id' => $row['id'],
                'author' => $row['author'],
                'content' => $row['content'],
                'date' => $row['date'],
                'score' => $row['score'],
                'user_vote' => isset($row['user_vote']) ? $row['user_vote'] : null
            ];
        }
        $response['success'] = true;
        $response['comments'] = $comments;
    } else {
        $response['error'] = 'Database error: ' . $stmt->error;
    }
    $conn->close();
    echo json_encode($response);
}

$data = json_decode(file_get_contents('php://input'), true);
$action = null;
$get_action = null;
if (isset($_GET['get_action'])) $get_action = $_GET['get_action'];
if ($get_action != null) {
    if ($get_action == 'getPosts') {
        if (isset($data['user_id'])) {
            $user_id = intval($data['user_id']);
            getPosts($user_id, $data['page']);
        } else getPosts(page: $data['page']);
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
        getComments($data['card_id']);
    } elseif ($get_action == 'update' && isset($data['comment_id'])) {
        $user_id = intval($data['user_id']);
        $comment_id = intval($data['comment_id']);
        $action = $data['action'];
        updateComment($user_id, $comment_id, $action);
    } else {
        $response = ['error' => 'Invalid action'];
        echo json_encode($response);
    };
}
