<?php
require 'db.php';

$stmt = $pdo->query("
    SELECT 
        p.id, 
        p.content, 
        p.created_at, 
        u.username,
        (SELECT COUNT(*) FROM replies r WHERE r.post_id = p.id) AS reply_count
    FROM posts p
    JOIN users u ON p.user_id = u.id
    ORDER BY p.created_at DESC
");

$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($posts);
?>