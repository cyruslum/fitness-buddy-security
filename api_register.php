<?php
// Start session for storing user data after registration
session_start();

// Set headers for API response
header('Content-Type: application/json');

// Include database connection
require_once 'db.php';

// Check if the request method is POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request method."
    ]);
    exit();
}

// Get the raw POST data and decode it as JSON
$data = json_decode(file_get_contents("php://input"), true);

// Check if JSON is valid
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid JSON input."
    ]);
    exit();
}

// Extract values from the JSON data
$username = trim($data['username'] ?? '');
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';
$membershipTier = $data['membershipTier'] ?? 'free'; // We'll save this in the session even if not in DB

// Basic validation
if (empty($username) || empty($email) || empty($password)) {
    echo json_encode([
        "status" => "error",
        "message" => "All fields are required."
    ]);
    exit();
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid email format."
    ]);
    exit();
}

// Validate password strength
if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$/', $password)) {
    echo json_encode([
        "status" => "error",
        "message" => "Password must be at least 8 characters long, contain a letter, a number, and a special character."
    ]);
    exit();
}

try {
    // Check if username already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            "status" => "error",
            "message" => "Username already taken. Please choose a different username."
        ]);
        exit();
    }

    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            "status" => "error",
            "message" => "This email is already registered. Please try logging in or use a different email."
        ]);
        exit();
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Begin transaction
    $conn->beginTransaction();

    // Insert user into the database based on your existing schema
    $stmt = $conn->prepare(
        "INSERT INTO users (username, email, password_hash, profile_completed) 
         VALUES (:username, :email, :password_hash, 0)"
    );

    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->bindParam(':password_hash', $hashedPassword, PDO::PARAM_STR);
    $stmt->execute();

    // Get the new user ID
    $userId = $conn->lastInsertId();

    // Commit transaction
    $conn->commit();

    // Store user ID in session along with membership_tier (even though it's not in DB)
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['email'] = $email;
    $_SESSION['membership_tier'] = $membershipTier; // Store in session for use in app

    // Return success response
    echo json_encode([
        "status" => "success",
        "message" => "Your account has been created successfully! Redirecting to complete your profile...",
        "redirect" => "profileSetup.php",
        "user_id" => $userId
    ]);

} catch (PDOException $e) {
    // Roll back transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    // Log the error (to a file ideally)
    error_log("Registration error: " . $e->getMessage());

    // Return generic error message to user
    echo json_encode([
        "status" => "error",
        "message" => "An error occurred during registration. Please try again later."
    ]);
}

// Close connection
$conn = null;
?>