<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = $_POST['content'];
    $user_id = $_SESSION['user_id'] ?? null; // Get the logged-in user's ID

    if ($user_id && !empty($content)) {
        $stmt = $conn->prepare("INSERT INTO posts (user_id, content) VALUES (?, ?)");
        $stmt->execute([$user_id, $content]);

        // Redirect to the home page after creating a post
        header("Location: forum.php");
        exit();
    } else {
        // Handle invalid form submission or user not logged in
        echo "Please log in to post.";
    }
}
?>
