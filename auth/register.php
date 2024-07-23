<?php
session_start();
include '../api.php';
$conn = getDbConnection();
$errors = [];
$success = '';
$user_id;
$userName;
$result;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username)) {
        $errors[] = 'Username is required.';
    }
    if (empty($password)) {
        $errors[] = 'Password is required.';
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE name = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user) {
            $errors[] = 'Username already exists.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, password) VALUES (?,?)");
            if ($stmt->execute([$username, $hashed_password])) {
                $result = $conn->query('select id, name from users order by id desc limit 1 ');
                $result = $result->fetch_assoc();
                $user_id = $result['id'];
                $userName = $result['name'];
                $success = 'Registration successful! You can now <a href="login.php">login</a>.';
            } else {
                $errors[] = 'Registration failed. Please try again.';
            }
        }
    }
    if (!empty($errors)) {
        $response['message'] = implode('<br>', $errors);
    } else {
        $response['success'] = true;
        $response['data']['userId'] = $user_id;
        $response['data']['userName'] = $userName;
    }
    header('Content-Type: application/json');
    echo json_encode($response);
   
    exit;
}
?>

