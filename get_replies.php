<?php
require 'db.php';
session_start();

if (!isset($_GET['post_id']) || !is_numeric($_GET['post_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid post ID']);
    exit();
}

$postId = $_GET['post_id'];

try {
    // Fetch all replies for this post
    $stmt = $pdo->prepare("
        SELECT r.*, u.username
        FROM replies r
        JOIN users u ON r.user_id = u.id
        WHERE r.post_id = :post_id
        ORDER BY r.created_at ASC
    ");
    $stmt->bindParam(':post_id', $postId, PDO::PARAM_INT);
    $stmt->execute();

    $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($replies);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>