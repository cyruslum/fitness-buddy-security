<?php
require 'db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'You must be logged in to reply']);
    exit();
}

$userId = $_SESSION['user_id'];

// Check if request is POST and has required fields
if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !isset($_POST['post_id']) ||
    !isset($_POST['content']) ||
    !is_numeric($_POST['post_id'])
) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit();
}

$postId = $_POST['post_id'];
$content = trim($_POST['content']);

// Validate content
if (empty($content)) {
    http_response_code(400);
    echo json_encode(['error' => 'Reply content cannot be empty']);
    exit();
}

try {
    // Check if user is premium
    $premiumCheck = $pdo->prepare("
        SELECT membership_tier FROM profile WHERE user_id = :user_id
    ");
    $premiumCheck->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $premiumCheck->execute();

    if ($premiumCheck->rowCount() == 0 || $premiumCheck->fetch(PDO::FETCH_ASSOC)['membership_tier'] != 'premium') {
        http_response_code(403);
        echo json_encode(['error' => 'This feature requires a premium account']);
        exit();
    }

    // Check if post exists
    $postCheck = $pdo->prepare("SELECT id FROM posts WHERE id = :post_id");
    $postCheck->bindParam(':post_id', $postId, PDO::PARAM_INT);
    $postCheck->execute();

    if ($postCheck->rowCount() == 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Post not found']);
        exit();
    }

    // Add the reply
    $stmt = $pdo->prepare("
        INSERT INTO replies (post_id, user_id, content)
        VALUES (:post_id, :user_id, :content)
    ");
    $stmt->bindParam(':post_id', $postId, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':content', $content, PDO::PARAM_STR);
    $stmt->execute();

    // Get the username for the reply
    $userStmt = $pdo->prepare("SELECT username FROM users WHERE id = :user_id");
    $userStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $userStmt->execute();
    $username = $userStmt->fetch(PDO::FETCH_ASSOC)['username'];

    // Return the new reply data
    $replyId = $pdo->lastInsertId();
    $now = date('Y-m-d H:i:s');

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'reply' => [
            'id' => $replyId,
            'post_id' => $postId,
            'user_id' => $userId,
            'username' => $username,
            'content' => $content,
            'created_at' => $now,
            'formatted_date' => date('F j, Y, g:i a', strtotime($now))
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>