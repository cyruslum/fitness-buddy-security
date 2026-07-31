<?php
require 'db.php';  // Include the database connection

// Updated query to get posts with reply counts and profile pictures
$stmt = $conn->query("
    SELECT 
        p.id,
        p.user_id,
        p.content, 
        p.created_at, 
        u.username,
        COUNT(r.id) AS reply_count,
        pr.profile_picture
    FROM posts p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN replies r ON p.id = r.post_id
    LEFT JOIN profile pr ON u.id = pr.user_id
    GROUP BY p.id
    ORDER BY p.created_at DESC
");
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if the user is logged in
session_start();
$user_id = $_SESSION['user_id'] ?? null;

// Check if user is premium
$isPremium = false;
if ($user_id) {
    $premiumCheck = $conn->prepare("
        SELECT membership_tier FROM profile WHERE user_id = :user_id
    ");
    $premiumCheck->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $premiumCheck->execute();

    if ($premiumCheck->rowCount() > 0) {
        $profile = $premiumCheck->fetch(PDO::FETCH_ASSOC);
        $isPremium = ($profile['membership_tier'] == 'premium');
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fitness Buddy - Forum</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./css/navbar.css">
    <link rel="stylesheet" href="./css/forum.css">
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
                        <a class="nav-link active" href="forum.php">Forum</a>
                    </li>
                </ul>

                <!-- Right-aligned logout -->
                <ul class="navbar-nav ms-auto">
                    <?php if ($user_id): ?>
                        <?php if ($isPremium): ?>
                            <li class="nav-item me-2">
                                <span class="badge bg-warning text-dark d-flex align-items-center h-100">
                                    <i class="fas fa-crown me-1"></i> Premium
                                </span>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a href="logout.php" class="btn btn-outline-light">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a href="login.php" class="btn btn-outline-light me-2">Login</a>
                        </li>
                        <li class="nav-item">
                            <a href="register.php" class="btn btn-light">Sign Up</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <!-- Nav Bar Ends Here -->

    <div class="container mt-4">
        <div class="forum-header">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0">Fitness Forum</h2>
                <?php if ($isPremium): ?>
                    <span class="badge bg-warning text-dark">
                        <i class="fas fa-crown me-1"></i> Premium Member
                    </span>
                <?php endif; ?>
            </div>
            <p class="text-muted mt-2 mb-0">
                Share your fitness journey, ask questions, and connect with other fitness enthusiasts
                <?php if (!$isPremium): ?>
                    <a href="profileSetup.php" class="ms-2 text-decoration-none">
                        <span class="badge bg-warning text-dark">
                            <i class="fas fa-crown me-1"></i> Upgrade to Premium
                        </span>
                    </a>
                <?php endif; ?>
            </p>
        </div>

        <!-- Check if the user is logged in -->
        <?php if ($user_id): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <form action="create_post.php" method="POST">
                        <div class="mb-3">
                            <label for="content" class="form-label">Create a Post</label>
                            <textarea name="content" id="content" class="form-control" rows="3"
                                placeholder="What's on your fitness mind today?" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-1"></i> Post
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Success and Failure of Deletion -->
        <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Post deleted successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error']) && $_GET['error'] == 1): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                An error occurred while deleting the post.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Display posts or message if no posts -->
        <div id="posts-container">
            <?php if (empty($posts)): ?>
                <div class="text-center p-5">
                    <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                    <p class="lead">No posts yet. Be the first to share your fitness journey!</p>
                    <?php if (!$user_id): ?>
                        <a href="login.php" class="btn btn-primary mt-2">Login to Post</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="post-header">
                                    <?php if (!empty($post['profile_picture'])): ?>
                                        <img src="<?= htmlspecialchars($post['profile_picture']) ?>" alt="Profile"
                                            class="profile-pic">
                                    <?php else: ?>
                                        <div
                                            class="profile-pic d-flex align-items-center justify-content-center bg-secondary text-white">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                    <h5 class="card-title post-author">
                                        <?= htmlspecialchars($post['username']) ?>
                                    </h5>
                                </div>
                                <small class="text-muted">
                                    <?= date('F j, Y, g:i a', strtotime($post['created_at'])) ?>
                                </small>
                            </div>

                            <div class="post-preview mt-3 mb-3">
                                <?= nl2br(htmlspecialchars($post['content'])) ?>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <div>
                                    <a href="post.php?id=<?= $post['id'] ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-eye me-1"></i> View Full Post
                                    </a>

                                    <?php if ($post['reply_count'] > 0): ?>
                                        <span class="badge badge-replies ms-2">
                                            <i class="fas fa-comments me-1"></i> <?= $post['reply_count'] ?> Replies
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($isPremium): ?>
                                        <a href="post.php?id=<?= $post['id'] ?>#reply"
                                            class="btn btn-sm btn-outline-secondary ms-2">
                                            <i class="fas fa-reply me-1"></i> Reply <span class="premium-badge">PREMIUM</span>
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <?php if ($user_id == $post['user_id']): ?>
                                    <div>
                                        <a href="editPost.php?id=<?= $post['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="#" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $post['id'] ?>"
                                            class="btn btn-sm btn-outline-danger ms-1">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>

                                    <!-- Delete Modal for each post -->
                                    <div class="modal fade" id="deleteModal<?= $post['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Confirm Deletion</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                        aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    Are you sure you want to delete this post? This action cannot be undone.
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary"
                                                        data-bs-dismiss="modal">Cancel</button>
                                                    <a href="deletePost.php?id=<?= $post['id'] ?>" class="btn btn-danger">Delete</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Premium Feature Promotion -->
        <?php if ($user_id && !$isPremium): ?>
            <div class="card mt-4 border-warning">
                <div class="card-body text-center">
                    <h5 class="card-title">
                        <i class="fas fa-crown text-warning me-2"></i>
                        Upgrade to Premium
                    </h5>
                    <p class="card-text">Get access to premium features like replying to posts, private messaging, and more!
                    </p>
                    <a href="profileSetup.php" class="btn btn-warning">Upgrade Now</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Real-time updates for new posts (fetch every 30 seconds)
        function fetchNewPosts() {
            fetch('get_posts.php')
                .then(response => response.json())
                .then(data => {
                    // Logic to update posts would go here
                    // Not implementing full logic as it requires more complex state tracking
                });
        }

        // Only set interval if user is viewing the page actively
        let fetchInterval;
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') {
                fetchInterval = setInterval(fetchNewPosts, 30000);
            } else {
                clearInterval(fetchInterval);
            }
        });

        // Initial setup
        if (document.visibilityState === 'visible') {
            fetchInterval = setInterval(fetchNewPosts, 30000);
        }
    </script>
</body>

</html>