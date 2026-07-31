<?php
session_start();
require 'db.php';

// check user login session
$userId = $_SESSION['user_id'] ?? null;
$isLoggedIn = isset($userId);

//find exsititing table
function tableExists($conn, $tableName) {
    try {
        $result = $conn->query("SELECT 1 FROM $tableName LIMIT 1");
        return $result !== false;
    } catch (Exception $e) {
        return false;
    }
}

//tables exist?
$workoutsTableExists = tableExists($conn, 'workouts');
$trainerRatingsTableExists = tableExists($conn, 'trainer_ratings');
$postsTableExists = tableExists($conn, 'posts');
$userProfilesTableExists = tableExists($conn, 'user_profiles');

//initialize tables

$workoutDaysComplete = 0;
$totalWorkoutDays = 7;
$streakDays = 0;
$suggestedMatches = [];
$featuredPosts = [];
$trainerInfo = null;
$userProfile = null;

//get user data if the table exists
if ($isLoggedIn && $userProfilesTableExists) {
    try {
        $stmt = $conn->prepare("SELECT * FROM user_profiles WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $userProfile = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error fetching user profile: " . $e->getMessage());
    }
}

//consistency tracker
if ($isLoggedIn && $workoutsTableExists) {
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as completed_workouts
            FROM workouts 
            WHERE user_id = :user_id 
            AND workout_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $workoutData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($workoutData) {
            $workoutDaysComplete = $workoutData['completed_workouts'];
        }
        
        $stmt = $conn->prepare("
            SELECT 
                MAX(consecutive_days) as streak
            FROM (
                SELECT 
                    workout_date,
                    @consecutive := IF(
                        DATEDIFF(workout_date, @prev_date) = 1,
                        @consecutive + 1,
                        1
                    ) as consecutive_days,
                    @prev_date := workout_date
                FROM workouts
                JOIN (SELECT @consecutive := 0, @prev_date := NULL) as vars
                WHERE user_id = :user_id
                ORDER BY workout_date
            ) as streak_data
        ");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $streakData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($streakData && isset($streakData['streak'])) {
            $streakDays = $streakData['streak'];
        }
    } catch (PDOException $e) {
        error_log("Database error fetching workout data: " . $e->getMessage());
    }
}

if (!$workoutsTableExists || !isset($workoutData)) {
    $workoutDaysComplete = 5;
    $streakDays = 2;
}

$progressPercentage = ($workoutDaysComplete / $totalWorkoutDays) * 100;

// suggetsed matches
if ($isLoggedIn && $userProfilesTableExists && isset($userProfile)) {
    try {
        $stmt = $conn->prepare("
            SELECT 
                u.id, 
                u.username, 
                up.profile_picture
            FROM users u
            JOIN user_profiles up ON u.id = up.user_id
            WHERE 
                u.id != :user_id
                AND up.fitness_goals LIKE CONCAT('%', :fitness_goals, '%')
                AND up.experience_level = :experience_level
                AND up.workout_types LIKE CONCAT('%', :workout_types, '%')
                AND up.availability LIKE CONCAT('%', :availability, '%')
            LIMIT 3
        ");
        
        $fitnessGoals = explode(',', $userProfile['fitness_goals'])[0] ?? '';
        $experienceLevel = $userProfile['experience_level'] ?? 'beginner';
        $workoutTypes = explode(',', $userProfile['workout_types'])[0] ?? '';
        $availability = explode(',', $userProfile['availability'])[0] ?? '';
        
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':fitness_goals', $fitnessGoals, PDO::PARAM_STR);
        $stmt->bindParam(':experience_level', $experienceLevel, PDO::PARAM_STR);
        $stmt->bindParam(':workout_types', $workoutTypes, PDO::PARAM_STR);
        $stmt->bindParam(':availability', $availability, PDO::PARAM_STR);
        $stmt->execute();
        
        $suggestedMatches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error fetching matches: " . $e->getMessage());
    }
}

if (empty($suggestedMatches)) {
    $suggestedMatches = [
        ['id' => 1, 'username' => 'FitnessFan1'],
        ['id' => 2, 'username' => 'WorkoutBuddy2'],
        ['id' => 3, 'username' => 'HealthyLifestyle3']
    ];
}

//featured posts
if ($postsTableExists) {
    try {
        $featuredColumnExists = false;
        $columnQuery = $conn->query("SHOW COLUMNS FROM posts LIKE 'featured'");
        if ($columnQuery->rowCount() > 0) {
            $featuredColumnExists = true;
        }
        
        if ($featuredColumnExists) {
            $stmt = $conn->query("
                SELECT 
                    p.id, 
                    p.content, 
                    u.username
                FROM posts p
                JOIN users u ON p.user_id = u.id
                WHERE p.featured = 1
                ORDER BY p.created_at DESC
                LIMIT 2
            ");
        } else {
            $stmt = $conn->query("
                SELECT 
                    p.id, 
                    p.content, 
                    u.username
                FROM posts p
                JOIN users u ON p.user_id = u.id
                ORDER BY p.created_at DESC
                LIMIT 2
            ");
        }
        
        $featuredPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error fetching posts: " . $e->getMessage());
    }
}

if (empty($featuredPosts)) {
    $featuredPosts = [
        ['id' => 1, 'content' => 'Beginner Tips for New Gym-Goers'],
        ['id' => 2, 'content' => 'Nutrition Advice: Pre and Post Workout']
    ];
}

//trainer spotlight
if ($trainerRatingsTableExists && $userProfilesTableExists) {
    try {
        $stmt = $conn->query("
            SELECT 
                u.id,
                u.username,
                up.profile_picture,
                COALESCE(AVG(tr.rating), 5) as average_rating
            FROM users u
            JOIN user_profiles up ON u.id = up.user_id
            LEFT JOIN trainer_ratings tr ON u.id = tr.trainer_id
            WHERE up.membership_tier = 'premium'
            GROUP BY u.id
            ORDER BY average_rating DESC
            LIMIT 1
        ");
        
        $trainerInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error fetching trainer: " . $e->getMessage());
    }
}
if (!$trainerInfo) {
    $trainerInfo = [
        'id' => 99,
        'username' => 'Jon Doe',
        'average_rating' => 5
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fitness Buddy - Home</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="./css/navbar.css">
    <link rel="stylesheet" href="./css/profileSetup.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap">
    <style>
        .bg-salmon {
            background-color: salmon !important;
        }

        .user-match-card {
            position: relative;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 30px 15px;
            background-color: #f8f9fa;
            overflow: hidden;
        }

        .user-match-card p {
            position: relative;
            z-index: 2;
            color: #666;
        }

        .x-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }

        .x-overlay::before,
        .x-overlay::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-position: center;
            background-repeat: no-repeat;
        }

        .x-overlay::before {
            background-image: linear-gradient(45deg, transparent 45%, #ddd 45%, #ddd 55%, transparent 55%);
        }

        .x-overlay::after {
            background-image: linear-gradient(-45deg, transparent 45%, #ddd 45%, #ddd 55%, transparent 55%);
        }

        .form-container {
            margin: 15px auto;
        }

        .profile-section h5 {
            margin-bottom: 10px;
        }

        .progress {
            height: 20px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 0;
        }

        .progress-bar {
            background-color: #444;
        }

        .btn-dark {
            background-color: #444;
            border-color: #444;
            border-radius: 0;
        }

        .btn-dark:hover {
            background-color: #555;
            border-color: #555;
        }

        .text-muted {
            color: #999 !important;
        }

        .container {
            max-width: 800px;
        }

        .header-simple {
            background-color: #b0b0b0;
            padding: 8px 15px;
            margin-bottom: 20px;
        }

        .header-simple h1 {
            font-size: 18px;
            margin: 0;
            color: #333;
        }

        body {
            padding-top: 0;
        }
    </style>
</head>
<body>
    <header class="header-simple">
        <h1>Fitness Buddy</h1>
    </header>

    <div class="container">
        <div class