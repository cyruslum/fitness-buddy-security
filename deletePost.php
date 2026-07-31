<!-- Jag -->
<?php
session_start();
require 'db.php';

// Checks if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Checks for post ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: forum.php");
    exit();
}

$postId = $_GET['id'];
$userId = $_SESSION['user_id'];;

// Check post's existence
$stmt = $conn->prepare("SELECT user_id FROM posts WHERE id = :post_id");
$stmt->bindParam(':post_id', $postId, PDO::PARAM_INT);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    // Post doesn't exist
    header("Location: forum.php");
    exit();
}

$post = $stmt->fetch(PDO::FETCH_ASSOC);

// Checks if the logged user the owner of the post
if($post['user_id'] != $userId) {
    // No delete authorization
    header("Location: forum.php");
    exit();
}

// Delete the post
$deleteStmt = $conn->prepare("DELETE FROM posts WHERE id = :post_id");
$deleteStmt->bindParam(':post_id', $postId, PDO::PARAM_INT);

if($deleteStmt->execute()) {
    // Redirect upon success
    header("Location: forum.php?deleted=1");
} else {
    // Redirect with error message
    header("Location: forum.php?error=1");
}
exit();
?>