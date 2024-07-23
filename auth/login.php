<?php
session_start();
require '../api.php';
$conn = getDbConnection();
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = htmlspecialchars($_POST['username']);
    $password = htmlspecialchars($_POST['password']);
    $stmt = $conn->prepare("SELECT * FROM users WHERE name = ?");
    $stmt->bind_param('s', $username);
    $user = null;
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        if (!is_null($user) && password_verify($password, $user['password'])) {
            $response['success'] = true;
            $response['data']['userId'] = $user['id'];
            $response['data']['userName'] = $username;
        } else {
            $response['error'] = true;
            $response['data'] = 'Invalid user or password';
        }
    } else {
        $response['error'] = 'Database error: ' . $stmt->error;
    }
    echo json_encode($response);
}
