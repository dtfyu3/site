<?php
session_start();
include '../api.php';
$conn = getDbConnection();
$errors = [];
$success = '';
$user_id = null;
$userName = null;
$result;
$response = ['success' => false];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim(htmlspecialchars($_POST['username']));
    $password = trim(htmlspecialchars($_POST['password']));

    if (empty($username)) {
        $errors[] = 'Username is required.';
    }
    if (empty($password)) {
        $errors[] = 'Password is required.';
    }

    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT * FROM users WHERE name = :username");
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if ($user) {
                $errors[] = 'Username already exists.';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (name, password) VALUES (:username, :password)");
                $stmt->bindParam(':username', $username, PDO::PARAM_STR);
                $stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
                if ($stmt->execute()) {
                    $stmt->closeCursor();
                    $stmt = $conn->prepare("SELECT id, name FROM users ORDER BY id DESC LIMIT 1");
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $stmt->closeCursor();
                    $user_id = $result['id'];
                    $userName = $result['name'];
                    $success = 'Registration successful! You can now <a href="login.php">login</a>.';
                } else {
                    $errors[] = 'Registration failed. Please try again.';
                }
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        } finally {
            $conn = null;
        }
    }

    if (!empty($errors)) {
        $response['message'] = implode('<br>', $errors);
    } else {
        $response['success'] = true;
        $response['data'] = [
            'userId' => $user_id,
            'userName' => $userName
        ];
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>

