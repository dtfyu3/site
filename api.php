<?php
// header('Content-Type: application/json');
error_reporting();
$post_num = 10;
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
function getPosts($user_id = null, $page = 1, $limit = null, $offset = false, $query = null, $order = null)
{
    $conn = getDbConnection();
    global $post_num;
    if (is_null($limit)) $limit = $post_num;
    if ($offset == false) $start = ($page - 1) * $post_num;
    else $start = ($page * $post_num) - 1;
    $order = $order == null ? 'desc' : 'asc';
    try {
        if ($user_id != null) {
            $sql = "select cards.id, name as author, date, edit_date, content, score, vote_type as user_vote, (select count(*) from card_comments cc where cc.card_id = cards.id) as comment_count, (select count(*) from user_votes u where u.card_id = cards.id) as total_votes from cards inner join users on users.id = cards.author left join user_votes on cards.id = user_votes.card_id and user_votes.user_id = ?";
            if (is_null($query)) {
                $total_result = "select count(*) as total_result from ($sql order by date $order) as a";
                $stmt = $conn->prepare($total_result);
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $total_result = $stmt->get_result()->fetch_assoc()['total_result'];
                $sql = $sql . " order by date $order limit ?, ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('iii', $user_id, $start, $limit);
            } else {
                $query = '%' . $query . '%';
                $total_result = "select count(*) as total_result from ($sql where content like ? order by date $order) as a";
                $stmt = $conn->prepare($total_result);
                $stmt->bind_param('is', $user_id, $query);
                $stmt->execute();
                $total_result = $stmt->get_result()->fetch_assoc()['total_result'];
                $sql = $sql . " where content like ?  order by date $order limit ?, ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('isii', $user_id, $query, $start, $limit);
            }
        } else {
            $sql = 'select cards.id, name as author, date, edit_date, content, score, (select count(*) from card_comments cc where cc.card_id = cards.id) as comment_count, 
         (select count(*) from user_votes u where u.card_id = cards.id) as total_votes from cards inner join users on users.id = cards.author';
            if (is_null($query)) {
                $total_result = $conn->query("select count(*) as total_result from ($sql order by date $order) as a")->fetch_assoc()['total_result'];
                $sql = $sql . " order by date $order limit ?, ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ii', $start, $limit);
            } else {
                $query = '%' . $query . '%';
                $total_result = "select count(*) as total_result from ($sql where content like ? order by date $order) as a";
                $stmt = $conn->prepare($total_result);
                $stmt->bind_param('s', $query);
                $stmt->execute();
                $total_result = $stmt->get_result()->fetch_assoc()['total_result'];
                $sql = $sql . " where content like ? order by date $order limit ?, ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('sii', $query, $start, $limit);
            }
        }
        $posts = array();
        $response = ['success' => false];
        $stmt->execute();
        $result = $stmt->get_result();
        $total_pages = ceil($total_result / $limit);
        while ($row = $result->fetch_assoc()) {
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
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        $response['error'] = $e->getMessage();
    }

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
    $limit = 10;
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
    $stmt = $conn->query('select count(*) from cards');
    $total_result = $stmt->fetch_column();
    $response['total_result'] = $total_result;
    $stmt = $conn->prepare('select name from users where id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $response['data']['author'] = $result->fetch_column();
    $total = $conn->query('select count(*) as total from cards')->fetch_assoc()['total'];
    $total_pages = ceil($total / $limit);
    $response['total_pages'] = $total_pages;
    $stmt->close();
    $conn->close();
    echo json_encode($response);
}
function putComment($user_id, $card_id, $content)
{
    $conn = getDbConnection();
    $conn->begin_transaction();
    $content = strip_tags($content);
    $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    $comment_id = null;
    $date =  date('Y-m-d H:i:s');
    try {
        $stmt = $conn->prepare('insert into comments (user_id, content, date) values (?,?,?)');
        $stmt->bind_param('iss', $user_id, $content, $date);
        if ($stmt->execute()) {
            $comment_id = $stmt->insert_id;
            $response['data'] = [
                'id' => $comment_id,
                'content' => $content,
                'date' => $date,
                'score' => 0
            ];
        }
        $stmt = $conn->prepare('insert into card_comments (card_id, comment_id) values (?,?)');
        $stmt->bind_param('ii', $card_id, $comment_id);
        $stmt->execute();
        $stmt = $conn->prepare('select count(*) from card_comments where card_id = ?');
        $stmt->bind_param('i', $card_id);
        $stmt->execute();
        $comments_count = $stmt->get_result()->fetch_column();
        $conn->commit();
        $response['success'] = true;
        $response['comments_count'] = $comments_count;
    } catch (Exception $e) {
        $conn->rollback();
        $response['error'] = $e->getMessage();
    }
    $conn->close();
    echo json_encode($response);
}
function delete($card_id = null, $comment_id = null, $comment_list = null)
{
    if (!is_null($card_id)) {
        $limit = 10;
        $conn = getDbConnection();
        $conn->begin_transaction();
        $stmt = $conn->prepare('delete from cards where id = ?');
        $stmt->bind_param('i', $card_id);
        try {
            $stmt->execute();
            if (!empty($comment_list) && !is_null($comment_list)) {
                $str = implode(',', array_map('intval', $comment_list));
                $sql = "delete from comments where id in ($str)";
                $conn->query($sql);
            }
            $response['success'] = true;
            $total = $conn->query('select count(*) as total from cards')->fetch_assoc()['total'];
            $total_pages = ceil($total / $limit);
            $response['total_pages'] = $total_pages;
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $response['error'] = $e->getMessage();
        }
    } elseif (!is_null($comment_id)) {
        try {
            $conn = getDbConnection();
            $conn->begin_transaction();
            $stmt = $conn->prepare('delete from comments where id = ?');
            $stmt->bind_param('i', $comment_id);
            $stmt->execute();
            $response['success'] = true;
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $response['error'] = $e->getMessage();
        }
    }
    $conn->close();
    echo json_encode($response);
}
function getComments($card_id, $user_id)
{
    $conn = getDbConnection();
    $stmt = $conn->prepare('select c.id, name as author, content, date, score,vote_type as user_vote 
from comments c 
left join users on c.user_id = users.id 
left join card_comments cc on c.id = cc.comment_id
left join comments_votes cv on cv.user_id = ? and cv.comment_id = c.id
where cc.card_id = ?
order by date desc');
    $stmt->bind_param('ii', $user_id, $card_id);
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
                'user_vote' => isset($row['user_vote']) ? $row['user_vote'] : null,
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
function updateCard($card_id, $content, $is_comment = null)
{
    if (is_null($is_comment)) {
        $conn = getDbConnection();
        $content = strip_tags($content);
        $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        $date =  date('Y-m-d H:i:s');
        $stmt = $conn->prepare('update cards set content = ?, edit_date = ? where id = ?');
        $stmt->bind_param('ssi', $content, $date, $card_id);
        try {
            $stmt->execute();
            $response['success'] = true;
        } catch (Exception $e) {
            $response['error'] = $e->getMessage();
        }
        echo json_encode($response);
    }
}
function getPageCount(){
    $conn = getDbConnection();
    global $post_num;
    $total_result = $conn->query("select count(*) as total_result from cards")->fetch_assoc()['total_result'];
    $total_pages = ceil($total_result / $post_num);
    $response['success'] = true;
    $response['page_count'] = $total_pages;
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
            getPosts($user_id, $data['page'], $data['limit'], $data['offset'], $data['query'], $data['order']);
        } else getPosts(page: $data['page'], query: $data['query'], order: $data['order']);
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
    } elseif($get_action == 'getPageCount') getPageCount(); 
    else {
        $response = ['error' => 'Invalid action'];
        echo json_encode($response);
    };
}
