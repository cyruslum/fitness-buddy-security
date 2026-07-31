<?php
session_start();
require 'db.php';  // Include the database connection

$stmt = $conn->query("
    SELECT posts.id, posts.content, posts.created_at, users.username, profile.profile_picture 
    FROM posts 
    JOIN users ON posts.user_id = users.id 
    LEFT JOIN profile ON users.id = profile.user_id
    ORDER BY posts.created_at DESC
");
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if the user is logged in
$user_id = $_SESSION['user_id'] ?? null;

// Fetch recommended trainers (users who selected 'trainer' as their experience level)
$trainersStmt = null;
if ($user_id) {
    // Get current user's profile information
    $profileStmt = $conn->prepare("SELECT * FROM profile WHERE user_id = :user_id");
    $profileStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $profileStmt->execute();
    $userProfile = $profileStmt->fetch(PDO::FETCH_ASSOC);

    // Find trainers with similar workout types or goals
    // Left join with match_requests to exclude trainers who already have pending/accepted requests
    $trainersStmt = $conn->prepare("
        SELECT 
            u.id, 
            u.username, 
            p.fitness_goals,
            p.workout_types,
            p.bio,
            p.profile_picture
        FROM users u
        JOIN profile p ON u.id = p.user_id
        LEFT JOIN match_requests mr ON 
            ((mr.sender_id = :user_id AND mr.receiver_id = u.id) OR 
            (mr.sender_id = u.id AND mr.receiver_id = :user_id))
            AND (mr.status = 'pending' OR mr.status = 'accepted')
        WHERE 
            p.experience_level = 'trainer'
            AND u.id != :user_id
            AND mr.id IS NULL
        ORDER BY 
            CASE 
                WHEN p.workout_types LIKE :workout_types THEN 1
                WHEN p.fitness_goals LIKE :fitness_goals THEN 2
                ELSE 3
            END
        LIMIT 5
    ");

    // Get workout types and fitness goals for matching
    $workoutTypesParam = $userProfile ? '%' . $userProfile['workout_types'] . '%' : '%%';
    $fitnessGoalsParam = $userProfile ? '%' . $userProfile['fitness_goals'] . '%' : '%%';

    $trainersStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $trainersStmt->bindParam(':workout_types', $workoutTypesParam, PDO::PARAM_STR);
    $trainersStmt->bindParam(':fitness_goals', $fitnessGoalsParam, PDO::PARAM_STR);
    $trainersStmt->execute();

    // Get user's workout consistency data
    $consistencyStmt = $conn->prepare("
        SELECT check_in_date FROM workout_check_ins 
        WHERE user_id = :user_id 
        AND check_in_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY check_in_date ASC
    ");
    $consistencyStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $consistencyStmt->execute();
    $checkIns = $consistencyStmt->fetchAll(PDO::FETCH_COLUMN);

    // Get current streak
    $streakStmt = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM workout_check_ins 
             WHERE user_id = :user_id 
             AND check_in_date >= (
                SELECT MAX(date_gap.check_in_date) FROM (
                    SELECT 
                        c1.check_in_date,
                        DATEDIFF(c1.check_in_date, (
                            SELECT MAX(c2.check_in_date) 
                            FROM workout_check_ins c2 
                            WHERE c2.user_id = :user_id AND c2.check_in_date < c1.check_in_date
                        )) as day_diff
                    FROM workout_check_ins c1
                    WHERE c1.user_id = :user_id
                    HAVING day_diff > 1 OR day_diff IS NULL
                ) as date_gap
             )
            ) as current_streak
    ");
    $streakStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $streakStmt->execute();
    $streakData = $streakStmt->fetch(PDO::FETCH_ASSOC);
    $currentStreak = $streakData['current_streak'] ?? 0;

    // Get longest streak
    $longestStreakStmt = $conn->prepare("
        SELECT MAX(streak_length) as longest_streak
        FROM (
            SELECT 
                COUNT(*) as streak_length
            FROM (
                SELECT 
                    c1.check_in_date,
                    (
                        SELECT COUNT(*) 
                        FROM workout_check_ins c2 
                        WHERE c2.user_id = :user_id 
                        AND c2.check_in_date < c1.check_in_date
                        AND DATEDIFF(c1.check_in_date, c2.check_in_date) > 1
                    ) as streak_group
                FROM workout_check_ins c1
                WHERE c1.user_id = :user_id
            ) as streak_groups
            GROUP BY streak_group
        ) as streaks
    ");
    $longestStreakStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $longestStreakStmt->execute();
    $longestStreakData = $longestStreakStmt->fetch(PDO::FETCH_ASSOC);
    $longestStreak = $longestStreakData['longest_streak'] ?? 0;
}

// Process trainer request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_trainer_request'])) {
    $receiverId = $_POST['trainer_id'];

    // Check if a request already exists
    $checkStmt = $conn->prepare("
        SELECT id FROM match_requests 
        WHERE (sender_id = :sender_id AND receiver_id = :receiver_id)
        OR (sender_id = :receiver_id AND receiver_id = :sender_id)
    ");
    $checkStmt->bindParam(':sender_id', $user_id, PDO::PARAM_INT);
    $checkStmt->bindParam(':receiver_id', $receiverId, PDO::PARAM_INT);
    $checkStmt->execute();

    if ($checkStmt->rowCount() === 0) {
        // Insert new request (pending) if it doesn't exist
        $insertStmt = $conn->prepare("
            INSERT INTO match_requests (sender_id, receiver_id, status)
            VALUES (:sender_id, :receiver_id, 'pending')
        ");
        $insertStmt->bindParam(':sender_id', $user_id, PDO::PARAM_INT);
        $insertStmt->bindParam(':receiver_id', $receiverId, PDO::PARAM_INT);

        if ($insertStmt->execute()) {
            header("Location: index.php?request_sent=1");
            exit();
        } else {
            $errorMessage = "Failed to send request. Please try again.";
        }
    } else {
        $errorMessage = "A request already exists with this trainer.";
    }
}

// Process workout check-in
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_in'])) {
    $checkInDate = $_POST['check_in_date'] ?? date('Y-m-d');

    // Check if already checked in on this date
    $checkExistingStmt = $conn->prepare("
        SELECT id FROM workout_check_ins 
        WHERE user_id = :user_id AND check_in_date = :check_in_date
    ");
    $checkExistingStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $checkExistingStmt->bindParam(':check_in_date', $checkInDate, PDO::PARAM_STR);
    $checkExistingStmt->execute();

    if ($checkExistingStmt->rowCount() === 0) {
        // Insert new check-in
        $insertCheckInStmt = $conn->prepare("
            INSERT INTO workout_check_ins (user_id, check_in_date)
            VALUES (:user_id, :check_in_date)
        ");
        $insertCheckInStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $insertCheckInStmt->bindParam(':check_in_date', $checkInDate, PDO::PARAM_STR);

        if ($insertCheckInStmt->execute()) {
            header("Location: index.php?check_in_success=1");
            exit();
        } else {
            $errorMessage = "Failed to record your workout. Please try again.";
        }
    } else {
        $errorMessage = "You've already checked in for this date.";
    }
}

// Helper functions for formatting
function formatFitnessGoals($goals)
{
    $goalsArray = explode(',', $goals);
    $formattedGoals = array_map('ucfirst', $goalsArray);
    return implode(', ', $formattedGoals);
}

function formatWorkoutTypes($workoutTypes)
{
    $workoutTypesArray = explode(',', $workoutTypes);
    $formattedWorkoutTypes = array_map('ucfirst', $workoutTypesArray);
    return implode(', ', $formattedWorkoutTypes);
}

// Helper function to generate calendar boxes for the last 30 days
function generateCalendarBoxes($checkIns)
{
    $calendar = '';
    $today = new DateTime();
    $checkInDates = array_map(function ($date) {
        return (new DateTime($date))->format('Y-m-d');
    }, $checkIns);

    // Generate boxes for the last 30 days
    for ($i = 29; $i >= 0; $i--) {
        $date = clone $today;
        $date->modify("-$i days");
        $dateStr = $date->format('Y-m-d');
        $dayOfWeek = $date->format('D');
        $isCheckedIn = in_array($dateStr, $checkInDates);

        $boxClass = $isCheckedIn ? 'bg-salmon' : 'bg-light';
        $textClass = $isCheckedIn ? 'text-white' : 'text-dark';

        $calendar .= '<div class="calendar-box ' . $boxClass . ' ' . $textClass . '" 
                        data-bs-toggle="tooltip" title="' . $date->format('M j') . '">
                        <small>' . $dayOfWeek[0] . '</small>
                    </div>';
    }

    return $calendar;
}

// Calculate streak achievement level
function getStreakAchievementLevel($streak)
{
    if ($streak >= 30)
        return ['Workout Legend', 'achievement-gold'];
    if ($streak >= 20)
        return ['Fitness Warrior', 'achievement-silver'];
    if ($streak >= 10)
        return ['Exercise Pro', 'achievement-bronze'];
    if ($streak >= 5)
        return ['Fitness Enthusiast', 'achievement-starter'];
    return ['Getting Started', ''];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fitness Buddy - Home</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script defer src="home.js"></script>
    <link rel="stylesheet" href="./css/navbar.css">
    <link rel="stylesheet" href="./css/index.css">
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

    <div class="container mt-4">
        <!-- Success messages -->
        <?php if (isset($_GET['request_sent']) && $_GET['request_sent'] == 1): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Trainer request sent successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['check_in_success']) && $_GET['check_in_success'] == 1): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> Workout check-in recorded! Keep up the great work!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($errorMessage) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Posts section (3/4 width) -->
            <div class="col-md-9">
                <h2 class="mb-4">Recent Posts</h2>

                <!-- Check if the user is logged in for post creation -->
                <?php if ($user_id): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <form action="create_post.php" method="POST">
                                <div class="mb-3">
                                    <label for="content" class="form-label">Create a Post</label>
                                    <textarea name="content" id="content" class="form-control" rows="3"
                                        placeholder="What's on your fitness mind today?" required></textarea>
                                </div>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-send me-1"></i> Post
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
                            <i class="bi bi-chat-square-text text-muted" style="font-size: 3rem;"></i>
                            <p class="lead mt-3">No posts yet. Be the first to share your fitness journey!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($posts as $post): ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <h5 class="card-title d-flex align-items-center">
                                            <?php if (!empty($post['profile_picture'])): ?>
                                                <img src="<?= htmlspecialchars($post['profile_picture']) ?>" alt="Profile"
                                                    class="me-2"
                                                    style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                            <?php else: ?>
                                                <i class="bi bi-person-circle me-2 text-secondary" style="font-size: 1.5rem;"></i>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($post['username']) ?>
                                        </h5>
                                        <small class="text-muted">
                                            <?= date('F j, Y, g:i a', strtotime($post['created_at'])) ?>
                                        </small>
                                    </div>

                                    <div class="mt-3 post-preview">
                                        <?= nl2br(htmlspecialchars($post['content'])) ?>
                                    </div>

                                    <div class="mt-3">
                                        <a href="post.php?id=<?= $post['id'] ?>" class="btn btn-primary btn-sm">
                                            <i class="bi bi-eye me-1"></i> View Post
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right sidebar (1/4 width) -->
            <div class="col-md-3">
                <?php if ($user_id): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-salmon text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i>
                                Workout Streak
                            </h5>
                            <span class="badge bg-light text-salmon" data-bs-toggle="tooltip" data-bs-placement="left"
                                title="Days you've tracked your workouts in the last 30 days">
                                <?= count($checkIns) ?>/30
                            </span>
                        </div>
                        <div class="card-body p-3">
                            <?php if (isset($currentStreak)): ?>
                                <!-- Current streak display -->
                                <div class="text-center mb-3">
                                    <div class="streak-number"><?= $currentStreak ?></div>
                                    <div class="text-muted">Current Streak</div>

                                    <?php
                                    $achievementLevel = getStreakAchievementLevel($currentStreak);
                                    if (!empty($achievementLevel[0])):
                                        ?>
                                        <div class="mt-2">
                                            <span class="badge bg-warning text-dark <?= $achievementLevel[1] ?>">
                                                <i class="bi bi-trophy-fill me-1"></i><?= $achievementLevel[0] ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Progress bar to next achievement -->
                                <?php
                                $nextMilestone = 5;
                                if ($currentStreak >= 5)
                                    $nextMilestone = 10;
                                if ($currentStreak >= 10)
                                    $nextMilestone = 20;
                                if ($currentStreak >= 20)
                                    $nextMilestone = 30;

                                $progressPercent = min(100, ($currentStreak / $nextMilestone) * 100);
                                ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small>Progress to next level</small>
                                        <small><?= $currentStreak ?>/<?= $nextMilestone ?> days</small>
                                    </div>
                                    <div class="progress" style="height: 10px;">
                                        <div class="progress-bar progress-bar-striped bg-salmon" role="progressbar"
                                            style="width: <?= $progressPercent ?>%;" aria-valuenow="<?= $progressPercent ?>"
                                            aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                </div>

                                <!-- Calendar view of check-ins -->
                                <div class="mb-3">
                                    <small class="text-muted d-block mb-2">Last 30 Days:</small>
                                    <div class="calendar-container">
                                        <?= generateCalendarBoxes($checkIns) ?>
                                    </div>
                                </div>

                                <!-- Check-in button -->
                                <form method="POST" class="mb-2">
                                    <input type="hidden" name="check_in_date" value="<?= date('Y-m-d') ?>">
                                    <button type="submit" name="check_in" class="btn btn-success w-100 check-in-btn">
                                        <i class="bi bi-check-circle me-2"></i>Check In Today's Workout
                                    </button>
                                </form>

                                <!-- Stats row -->
                                <div class="row text-center mt-3">
                                    <div class="col-6">
                                        <div class="border rounded p-2">
                                            <div class="h4 mb-0"><?= $longestStreak ?></div>
                                            <small class="text-muted">Best Streak</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="border rounded p-2">
                                            <div class="h4 mb-0"><?= count($checkIns) ?></div>
                                            <small class="text-muted">This Month</small>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-3">
                                    <i class="bi bi-calendar2-plus text-muted mb-3" style="font-size: 2rem;"></i>
                                    <p>Start tracking your workout consistency!</p>
                                    <form method="POST">
                                        <button type="submit" name="check_in" class="btn btn-success">
                                            <i class="bi bi-check-circle me-2"></i>Check In Today
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Trainers section -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white bg-salmon">
                        <h5 class="mb-0">Recommended Trainers</h5>
                    </div>
                    <div class="card-body p-3">
                        <?php if ($user_id && $trainersStmt && $trainers = $trainersStmt->fetchAll(PDO::FETCH_ASSOC)): ?>
                            <?php if (empty($trainers)): ?>
                                <p class="text-muted">No trainers available at the moment.</p>
                            <?php else: ?>
                                <?php foreach ($trainers as $trainer): ?>
                                    <div class="card trainer-card">
                                        <div class="card-body p-3">
                                            <div class="d-flex align-items-center mb-2">
                                                <?php if (!empty($trainer['profile_picture'])): ?>
                                                    <img src="<?= htmlspecialchars($trainer['profile_picture']) ?>" alt="Profile"
                                                        class="trainer-profile-pic me-2">
                                                <?php else: ?>
                                                    <div
                                                        class="trainer-profile-pic bg-secondary text-white d-flex align-items-center justify-content-center me-2">
                                                        <span><?= strtoupper(substr($trainer['username'], 0, 1)) ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <h6 class="mb-0"><?= htmlspecialchars($trainer['username']) ?></h6>
                                                    <small class="text-primary text-salmon">Certified Trainer</small>
                                                </div>
                                            </div>
                                            <?php if (!empty($trainer['workout_types'])): ?>
                                                <p class="mb-1"><small><strong>Specialties:</strong>
                                                        <?= htmlspecialchars(formatWorkoutTypes($trainer['workout_types'])) ?>
                                                    </small></p>
                                            <?php endif; ?>
                                            <?php if (!empty($trainer['bio'])): ?>
                                                <p class="trainer-bio mb-2">
                                                    <small><?= htmlspecialchars(substr($trainer['bio'], 0, 100)) ?>
                                                        <?= strlen($trainer['bio']) > 100 ? '...' : '' ?>
                                                    </small>
                                                </p>
                                            <?php endif; ?>
                                            <form method="POST">
                                                <input type="hidden" name="trainer_id" value="<?= $trainer['id'] ?>">
                                                <button type="submit" name="send_trainer_request"
                                                    class="btn btn-sm btn-outline-primary w-100">
                                                    Connect with Trainer
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php elseif (!$user_id): ?>
                            <p class="text-muted">Login to see recommended trainers.</p>
                        <?php else: ?>
                            <p class="text-muted">Complete your profile to see recommended trainers.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function () {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
        });
    </script>
</body>

</html>