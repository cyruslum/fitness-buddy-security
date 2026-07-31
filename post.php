<?php
session_start();
require 'db.php';

// Check post ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: forum.php");
    exit();
}

$postId = $_GET['id'];
$userId = $_SESSION['user_id'] ?? null;

// Fetch the post with author information
$stmt = $conn->prepare("
    SELECT posts.*, users.username
    FROM posts
    JOIN users ON posts.user_id = users.id
    WHERE posts.id = :post_id
");
$stmt->bindParam(':post_id', $postId, PDO::PARAM_INT);
$stmt->execute();

// Does post exist?
if ($stmt->rowCount() == 0) {
    header("Location: forum.php");
    exit();
}

$post = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if user is premium
$isPremium = false;
if ($userId) {
    $premiumCheck = $conn->prepare("
        SELECT membership_tier FROM profile WHERE user_id = :user_id
    ");
    $premiumCheck->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $premiumCheck->execute();

    if ($premiumCheck->rowCount() > 0) {
        $profile = $premiumCheck->fetch(PDO::FETCH_ASSOC);
        $isPremium = ($profile['membership_tier'] == 'premium');
    }
}

// Process new reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_content']) && $isPremium && $userId) {
    $replyContent = trim($_POST['reply_content']);

    if (!empty($replyContent)) {
        $addReply = $conn->prepare("
            INSERT INTO replies (post_id, user_id, content)
            VALUES (:post_id, :user_id, :content)
        ");
        $addReply->bindParam(':post_id', $postId, PDO::PARAM_INT);
        $addReply->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $addReply->bindParam(':content', $replyContent, PDO::PARAM_STR);

        if ($addReply->execute()) {
            // Redirect to prevent form resubmission
            header("Location: post.php?id=$postId&replied=1");
            exit();
        }
    }
}

// Fetch all replies for this post
$repliesStmt = $conn->prepare("
    SELECT r.*, u.username
    FROM replies r
    JOIN users u ON r.user_id = u.id
    WHERE r.post_id = :post_id
    ORDER BY r.created_at ASC
");
$repliesStmt->bindParam(':post_id', $postId, PDO::PARAM_INT);
$repliesStmt->execute();
$replies = $repliesStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Details - Fitness Buddy</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="./css/navbar.css">
    <link rel="stylesheet" href="./css/post.css">
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: salmon;">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="index.php">Fitness Buddy</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false"
                aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="myProfile.php">Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="matches.php">Matches</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="send_message.php">Message</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="forum.php">Forum</a>
                    </li>
                </ul>

                <?php if ($userId): ?>
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a href="logout.php" class="btn btn-outline-light ms-2">Logout</a>
                        </li>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="post-container">
        <?php if (isset($_GET['updated']) && $_GET['updated'] == 1): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Post updated successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['replied']) && $_GET['replied'] == 1): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Your reply has been added successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="post-header">
            <h2><?= htmlspecialchars(substr($post['content'], 0, 50)) . (strlen($post['content']) > 50 ? '...' : '') ?>
            </h2>
            <p class="post-meta">
                Posted by <strong><?= htmlspecialchars($post['username']) ?></strong> on
                <?= date('F j, Y, g:i a', strtotime($post['created_at'])) ?>
            </p>
        </div>

        <div class="post-content">
            <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>
        </div>

        <div class="action-buttons">
            <a href="forum.php" class="btn btn-back">Back to Forum</a>

            <?php if ($userId == $post['user_id']): ?>
                <!-- Only shows edit & delete options to the post author -->
                <a href="editPost.php?id=<?= $post['id'] ?>" class="btn btn-primary">Edit Post</a>
                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                    Delete Post
                </button>
            <?php endif; ?>
        </div>

        <!-- Replies Section -->
        <div class="replies-section">
            <h4>Replies <?= count($replies) > 0 ? '(' . count($replies) . ')' : '' ?></h4>

            <?php if (empty($replies)): ?>
                <p class="text-muted">No replies yet.</p>
            <?php else: ?>
                <?php foreach ($replies as $reply): ?>
                    <div class="reply-card">
                        <p class="mb-1"><?= nl2br(htmlspecialchars($reply['content'])) ?></p>
                        <small class="text-muted">
                            <?= htmlspecialchars($reply['username']) ?> •
                            <?= date('F j, Y, g:i a', strtotime($reply['created_at'])) ?>
                        </small>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($userId): ?>
                <?php if ($isPremium): ?>
                    <!-- Reply form for premium users -->
                    <div class="reply-form">
                        <form action="post.php?id=<?= $postId ?>" method="POST">
                            <div class="mb-3">
                                <label for="reply_content" class="form-label">
                                    Add a reply <span class="premium-badge">PREMIUM</span>
                                </label>
                                <textarea name="reply_content" id="reply_content" class="form-control" rows="3"
                                    required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Submit Reply</button>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- Message for non-premium users -->
                    <div class="premium-lock">
                        <h5>Premium Feature</h5>
                        <p class="text-muted">Upgrade to premium to reply to posts and join the conversation.</p>
                        <a href="profileSetup.php" class="btn btn-warning">Upgrade Now</a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- Message for logged out users -->
                <div class="premium-lock">
                    <h5>Join Fitness Buddy</h5>
                    <p class="text-muted">Log in or create an account to participate in forum discussions.</p>
                    <a href="login.php" class="btn btn-primary me-2">Log In</a>
                    <a href="register.php" class="btn btn-outline-primary">Sign Up</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($userId == $post['user_id']): ?>
        <!-- Delete -->
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Are you sure you want to delete this post? This action cannot be undone.
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <a href="deletePost.php?id=<?= $post['id'] ?>" class="btn btn-danger">Delete</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>