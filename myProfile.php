<!-- Jag -->
<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users JOIN profile ON users.id = profile.user_id WHERE users.id = :user_id");
$stmt->bindParam(':user_id', $userId);
$stmt->execute();
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

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

// Function to format gym location
function capFirstLetter($string)
{
    return ucwords(strtolower($string));
}
?>

<!DOCTYPE html>

<html lang="en" xmlns="http://www.w3.org/1999/xhtml">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Fitness Buddy</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="./css/profileSetup.css">
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
        <h2>My Profile</h2>

        <div class="profile-section">
            <h5>Profile Picture</h5>
            <div class="profile-picture-placeholder">
                <?php if (!empty($profile['profile_picture'])): ?>
                    <img src="<?= htmlspecialchars($profile['profile_picture']) ?>" alt="Profile Picture">
                <?php else: ?>
                    <i class="bi bi-camera"></i> Add Photo
                <?php endif; ?>
            </div>
        </div>

        <div class="profile-section">
            <h5>Fitness Goals</h5>
            <p><?= htmlspecialchars(formatFitnessGoals($profile['fitness_goals'])) ?></p>
        </div>

        <div class="profile-section">
            <h5>Experience Level</h5>
            <p><?= htmlspecialchars(capFirstLetter($profile['experience_level'])) ?></p>
        </div>

        <div class="profile-section">
            <h5>Workout Types</h5>
            <p><?= htmlspecialchars(formatWorkoutTypes($profile['workout_types'])) ?></p>
        </div>

        <div class="profile-section">
            <h5>Availability</h5>
            <p><?= htmlspecialchars(formatAvailability($profile['availability'])) ?></p>
        </div>

        <div class="profile-section">
            <h5>Gym Location</h5>
            <p><?= htmlspecialchars(capFirstLetter($profile['gym_location'])) ?></p>
            <p class="share-location-message">
                <?php if ($profile['share_location'] == 1): ?>
                    Gym location is being shared.
                <?php else: ?>
                    Gym location is not being shared.
                <?php endif; ?>
            </p>
        </div>

        <div class="profile-section">
            <h5>Bio</h5>
            <p><?= htmlspecialchars(capFirstLetter($profile['bio'])) ?></p>
        </div>

        <div class="profile-section">
            <h5>Account Type</h5>
            <p><?= htmlspecialchars(capFirstLetter($profile['membership_tier'])) ?></p>
        </div>

        <div class="action-buttons">
            <a href="profileSetup.php" class="btn btn-save">Edit Profile</a>
            <a href="logout.php" class="btn btn-danger ms-2">Logout</a>
        </div>
    </div>
</body>

</html>