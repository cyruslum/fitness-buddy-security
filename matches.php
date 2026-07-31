<?php
session_start();
require 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];

// Get current user's profile information
$profileStmt = $conn->prepare("SELECT * FROM profile WHERE user_id = :user_id");
$profileStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
$profileStmt->execute();
$userProfile = $profileStmt->fetch(PDO::FETCH_ASSOC);

// Find potential matches based on percent amount matched from the users table
// L23-L36: Collects user information and profile details (excludes gym location if not shared)
// L37-L43: Percent amount is 100 split amongst all profile details. When both users match (within the same set) the CASE stmt saves the score to a variable
// Join the users table with the user_profiles table
// Left Join used to obtain more information on match requests 
// L52: Exclude the current user and a user who's already matched or has been sent a match
// Order by desc match score calculated above
// u = users table
// up = user_profiles table
// note: 'remove AND (mr.status = 'pending' OR mr.status = 'accepted')' to prevent rejected users from being listed. This exists for testing purposes 
$matchesStmt = $conn->prepare("
    SELECT 
        u.id, 
        u.username, 
        up.fitness_goals,
        up.experience_level,
        up.workout_types,
        up.availability,
        up.gym_location,
        up.bio,
        up.profile_picture,
        CASE 
            WHEN up.share_location = 1 THEN up.gym_location
            ELSE 'Location not shared'
        END as displayed_location,
        (
            (CASE WHEN FIND_IN_SET(:fitness_goal1, up.fitness_goals) > 0 THEN 25 ELSE 0 END) +
            (CASE WHEN up.experience_level = :experience_level THEN 15 ELSE 0 END) +
            (CASE WHEN FIND_IN_SET(:workout_type, up.workout_types) > 0 THEN 20 ELSE 0 END) +
            (CASE WHEN FIND_IN_SET(:availability, up.availability) > 0 THEN 15 ELSE 0 END) +
            (CASE WHEN up.gym_location = :gym_location THEN 25 ELSE 0 END)
        ) as match_score
    FROM users u
    JOIN 
        profile up ON u.id = up.user_id
    LEFT JOIN 
        match_requests mr ON ((mr.sender_id = :user_id AND mr.receiver_id = u.id) OR (mr.sender_id = u.id AND mr.receiver_id = :user_id))
        AND (mr.status = 'pending' OR mr.status = 'accepted')
    WHERE 
        u.id != :user_id
        AND mr.id IS NULL
    ORDER BY match_score DESC
    LIMIT 10
");

// This secion extracts the profile data for matching
// Extract primary fitness goal for matching
$fitnessGoals = explode(',', $userProfile['fitness_goals']);
$primaryFitnessGoal = $fitnessGoals[0] ?? '';

// Extract primary workout type for matching
$workoutTypes = explode(',', $userProfile['workout_types']);
$primaryWorkoutType = $workoutTypes[0] ?? '';

// Extract primary availability for matching
$availabilityTimes = explode(',', $userProfile['availability']);
$primaryAvailability = $availabilityTimes[0] ?? '';

$matchesStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
$matchesStmt->bindParam(':fitness_goal1', $primaryFitnessGoal, PDO::PARAM_STR);
$matchesStmt->bindParam(':experience_level', $userProfile['experience_level'], PDO::PARAM_STR);
$matchesStmt->bindParam(':workout_type', $primaryWorkoutType, PDO::PARAM_STR);
$matchesStmt->bindParam(':availability', $primaryAvailability, PDO::PARAM_STR);
$matchesStmt->bindParam(':gym_location', $userProfile['gym_location'], PDO::PARAM_STR);
$matchesStmt->execute();

$potentialMatches = $matchesStmt->fetchAll(PDO::FETCH_ASSOC);

// Retreives the requests the user has sent
$sentRequestsStmt = $conn->prepare("
        SELECT 
            mr.id, mr.receiver_id, mr.status, u.username
        FROM 
            match_requests mr
        JOIN 
            users u ON mr.receiver_id = u.id
        WHERE 
            mr.sender_id = :user_id
    ");
$sentRequestsStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
$sentRequestsStmt->execute();
$sentRequests = $sentRequestsStmt->fetchAll(PDO::FETCH_ASSOC);

// Retreives the requestse the user has received
$receivedRequestsStmt = $conn->prepare("
        SELECT 
            mr.id, mr.sender_id, mr.status, u.username
        FROM 
            match_requests mr
        JOIN 
            users u ON mr.sender_id = u.id
        WHERE 
            mr.receiver_id = :user_id AND mr.status = 'pending'
    ");
$receivedRequestsStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
$receivedRequestsStmt->execute();
$receivedRequests = $receivedRequestsStmt->fetchAll(PDO::FETCH_ASSOC);

// Formatting
// Function to format fitness goals
function formatFitnessGoals($goals)
{
    $goalsArray = explode(',', $goals);
    $formattedGoals = array_map('ucfirst', $goalsArray);
    return implode(', ', $formattedGoals);
}

// Function to format workout types
function formatWorkoutTypes($workoutTypes)
{
    $workoutTypesArray = explode(',', $workoutTypes);
    $formattedWorkoutTypes = array_map('ucfirst', $workoutTypesArray);
    return implode(', ', $formattedWorkoutTypes);
}

// Function to format availability
function formatAvailability($availability)
{
    $availabilityArray = explode(',', $availability);
    $formattedAvailability = array_map(function ($item) {
        return ucfirst(str_replace('_', ' ', $item));
    }, $availabilityArray);
    return implode(', ', $formattedAvailability);
}

// Processes match request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_request'])) {
    $receiverId = $_POST['receiver_id'];

    // Check if a request already exists
    $checkStmt = $conn->prepare("
        SELECT id FROM match_requests 
        WHERE (sender_id = :sender_id AND receiver_id = :receiver_id)
        OR (sender_id = :receiver_id AND receiver_id = :sender_id)
    ");
    $checkStmt->bindParam(':sender_id', $userId, PDO::PARAM_INT);
    $checkStmt->bindParam(':receiver_id', $receiverId, PDO::PARAM_INT);
    $checkStmt->execute();

    if ($checkStmt->rowCount() === 0) {
        // Insert new request (pending) if it doesn't exist
        $insertStmt = $conn->prepare("
            INSERT INTO match_requests (sender_id, receiver_id, status)
            VALUES (:sender_id, :receiver_id, 'pending')
        ");
        $insertStmt->bindParam(':sender_id', $userId, PDO::PARAM_INT);
        $insertStmt->bindParam(':receiver_id', $receiverId, PDO::PARAM_INT);

        if ($insertStmt->execute()) {
            header("Location: matches.php?request_sent=1");
            exit();
        } else {
            $errorMessage = "Failed to send request. Please try again.";
        }
    } else {
        $errorMessage = "A match request already exists with this user.";
    }
}

// Process request acceptnce/denial - changes status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_action'])) {
    $requestId = $_POST['request_id'];
    $action = $_POST['request_action'];

    if ($action === 'accept') {
        // For accepted requests, update the status
        $updateStmt = $conn->prepare("
            UPDATE match_requests 
            SET status = 'accepted' 
            WHERE id = :request_id AND receiver_id = :user_id
        ");
        $updateStmt->bindParam(':request_id', $requestId, PDO::PARAM_INT);
        $updateStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);

        if ($updateStmt->execute()) {
            header("Location: matches.php?request_updated=1");
            exit();
        }
    } elseif ($action === 'decline') {
        // For declined requests, delete the record completely - purpose 
        $deleteStmt = $conn->prepare("
            DELETE FROM match_requests 
            WHERE id = :request_id AND receiver_id = :user_id
        ");
        $deleteStmt->bindParam(':request_id', $requestId, PDO::PARAM_INT);
        $deleteStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);

        if ($deleteStmt->execute()) {
            header("Location: matches.php?request_deleted=1");
            exit();
        }
    }

    /* this stops a user from being seen in the list if they're declined must replace lines 180-208
    // original code I wrote but decided to remove because it prevents testing
    if ($action === 'accept' || $action === 'decline') {
        $updateStmt = $conn->prepare("
            UPDATE match_requests 
            SET status = :status 
            WHERE id = :request_id AND receiver_id = :user_id
        ");
        $status = ($action === 'accept') ? 'accepted' : 'declined';
        $updateStmt->bindParam(':status', $status, PDO::PARAM_STR);
        $updateStmt->bindParam(':request_id', $requestId, PDO::PARAM_INT);
        $updateStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        
        if ($updateStmt->execute()) {
            header("Location: matches.php?request_updated=1");
            exit();
        }
    }
    */
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Matches - Fitness Buddy</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="./css/navbar.css">
    <link rel="stylesheet" href="./css/matches.css">
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
                Match request sent successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['request_updated']) && $_GET['request_updated'] == 1): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Match request updated successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($errorMessage) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <h2 class="mb-4">Find Your Fitness Buddy</h2>

        <!-- Tabs navigation -->
        <ul class="nav nav-tabs mb-4" id="matchTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="potential-matches-tab" data-bs-toggle="tab"
                    data-bs-target="#potential-matches" type="button" role="tab" aria-controls="potential-matches"
                    aria-selected="true">Potential Matches</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="requests-tab" data-bs-toggle="tab" data-bs-target="#requests" type="button"
                    role="tab" aria-controls="requests" aria-selected="false">
                    Requests
                    <?php if (count($receivedRequests) > 0): ?>
                        <span class="badge bg-danger"><?= count($receivedRequests) ?></span>
                    <?php endif; ?>
                </button>
            </li>
        </ul>

        <!-- Tab content -->
        <div class="tab-content" id="matchTabsContent">
            <!-- Potential Matches Tab -->
            <div class="tab-pane fade show active" id="potential-matches" role="tabpanel"
                aria-labelledby="potential-matches-tab">
                <?php if (empty($potentialMatches)): ?>
                    <div class="alert alert-info">
                        No potential matches found at the moment. Check back later!
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($potentialMatches as $match): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($match['profile_picture'])): ?>
                                                <img src="<?= htmlspecialchars($match['profile_picture']) ?>" alt="Profile"
                                                    class="profile-pic me-3">
                                            <?php else: ?>
                                                <div
                                                    class="profile-pic bg-secondary text-white d-flex align-items-center justify-content-center me-3">
                                                    <span><?= strtoupper(substr($match['username'], 0, 1)) ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <h5 class="mb-0"><?= htmlspecialchars($match['username']) ?></h5>
                                        </div>
                                        <span class="match-score"><?= $match['match_score'] ?>%</span>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Goals:</strong>
                                            <?= htmlspecialchars(formatFitnessGoals($match['fitness_goals'])) ?></p>
                                        <p><strong>Experience:</strong>
                                            <?= htmlspecialchars(ucfirst($match['experience_level'])) ?></p>
                                        <p><strong>Workout Types:</strong>
                                            <?= htmlspecialchars(formatWorkoutTypes($match['workout_types'])) ?></p>
                                        <p><strong>Availability:</strong>
                                            <?= htmlspecialchars(formatAvailability($match['availability'])) ?></p>
                                        <p><strong>Gym:</strong> <?= htmlspecialchars(ucfirst($match['displayed_location'])) ?>
                                        </p>
                                        <?php if (!empty($match['bio'])): ?>
                                            <p class="mb-0"><strong>Bio:</strong> <?= htmlspecialchars($match['bio']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-footer bg-white">
                                        <form method="POST">
                                            <input type="hidden" name="receiver_id" value="<?= $match['id'] ?>">
                                            <button type="submit" name="send_request" class="btn">Send Match
                                                Request</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Requests Tab -->
            <div class="tab-pane fade" id="requests" role="tabpanel" aria-labelledby="requests-tab">
                <div class="row">
                    <!-- Received Requests -->
                    <div class="col-md-6 mb-4">
                        <h4 class="mb-3">Received Requests</h4>
                        <?php if (empty($receivedRequests)): ?>
                            <p class="text-muted">No pending requests.</p>
                        <?php else: ?>
                            <?php foreach ($receivedRequests as $request): ?>
                                <div class="card mb-3 request-pending">
                                    <div class="card-body d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="mb-1"><?= htmlspecialchars($request['username']) ?></h5>
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        </div>
                                        <div>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                                <button type="submit" name="request_action" value="accept"
                                                    class="btn btn-sm btn-success">Accept</button>
                                                <button type="submit" name="request_action" value="decline"
                                                    class="btn btn-sm btn-danger">Decline</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Sent Requests -->
                    <div class="col-md-6 mb-4">
                        <h4 class="mb-3">Sent Requests</h4>
                        <?php if (empty($sentRequests)): ?>
                            <p class="text-muted">No sent requests.</p>
                        <?php else: ?>
                            <?php foreach ($sentRequests as $request): ?>
                                <div class="card mb-3 request-<?= $request['status'] ?>">
                                    <div class="card-body">
                                        <h5 class="mb-1"><?= htmlspecialchars($request['username']) ?></h5>
                                        <span
                                            class="badge <?= $request['status'] === 'pending' ? 'bg-warning text-dark' : ($request['status'] === 'accepted' ? 'bg-success' : 'bg-danger') ?>">
                                            <?= ucfirst($request['status']) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>