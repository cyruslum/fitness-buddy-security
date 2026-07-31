<!-- Jag -->
<?php
session_start();
require 'db.php';

// Check if user is logged in
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
$userId = $_SESSION['user_id'];

// Check post's existence
$stmt = $conn->prepare("SELECT * FROM posts WHERE id = :post_id");
$stmt->bindParam(':post_id', $postId, PDO::PARAM_INT);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    // Post doesn't exist
    header("Location: forum.php");
    exit();
}

$post = $stmt->fetch(PDO::FETCH_ASSOC);

// Checks if the logged user the owner of the post
if ($post['user_id'] != $userId) {
    // No editing authorization
    header("Location: forum.php");
    exit();
}

// Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = $_POST['content'];

    if (empty($content)) {
        $error = "Post content cannot be empty.";
    } else {
        // Update the post
        $updateStmt = $conn->prepare("UPDATE posts SET content = :content WHERE id = :post_id");
        $updateStmt->bindParam(':content', $content, PDO::PARAM_STR);
        $updateStmt->bindParam(':post_id', $postId, PDO::PARAM_INT);

        if ($updateStmt->execute()) {
            // Redirect to the post view page
            header("Location: post.php?id=$postId&updated=1");
            exit();
        } else {
            $error = "Failed to update post. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Post - Fitness Buddy</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="./css/navbar.css">
    <style>
        .form-container {
            max-width: 800px;
            margin: 30px auto;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-cancel {
            background-color: #f8f9fa;
            color: #666;
            border: 1px solid #ddd;
        }

        .btn-save {
            background-color: salmon;
            color: white;
            border: none;
        }

        .btn-save:hover {
            background-color: #ff7c66;
        }
    </style>
</head>

<body>
    <!-- Nav Bar Starts -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: salmon;">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="index.php">Fitness Buddy</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false"
                aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <!-- Left navigation items -->
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
                        <a class="nav-link" href="forum.php">Forum</a>
                    </li>
                </ul>

                <!-- Right-aligned logout -->
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a href="logout.php" class="btn btn-outline-light">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <!-- Nav Bar Ends Here -->

    <div class="form-container">
        <h2>Edit Post</h2>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label for="content" class="form-label">Post Content</label>
                <textarea class="form-control" id="content" name="content" rows="6"
                    required><?= htmlspecialchars($post['content']) ?></textarea>
            </div>

            <div class="action-buttons">
                <a href="post.php?id=<?= $postId ?>" class="btn btn-cancel">Cancel</a>
                <button type="submit" class="btn btn-save">Save Changes</button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>