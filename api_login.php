<?php
session_start();
require_once 'db.php'; // Ensure the database connection file is set up with PDO

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Get the raw POST data and decode it as JSON
    $data = json_decode(file_get_contents("php://input"), true);

    // Check if the data is valid
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(["success" => false, "message" => "Invalid JSON input."]);
        exit();
    }

    // Extract values from the JSON data
    $email = trim($data['email']);
    $password = $data['password'];

    // Validate fields
    if (empty($email) || empty($password)) {
        echo json_encode(["success" => false, "message" => "Both email and password are required."]);
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["success" => false, "message" => "Invalid email format."]);
        exit();
    }

    // Check if the user exists in the database
    $stmt = $conn->prepare("SELECT id, password_hash, is_admin FROM users WHERE email = :email LIMIT 1");
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        echo json_encode(["success" => false, "message" => "No user found with that email address."]);
        exit();
    }

    // Fetch the user record
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify the password
    if (!password_verify($password, $user['password_hash'])) {
        echo json_encode(["success" => false, "message" => "Incorrect password."]);
        exit();
    }

    // Start the session and store the user ID
    $_SESSION["user_id"] = $user['id'];

    // Check if the user is an admin ~Jag
    if (isset($user['is_admin']) && $user['is_admin'] == 1) {
        $_SESSION["is_admin"] = true;
        echo json_encode([
            "success" => true,
            "message" => "Admin login successful. Redirecting to admin dashboard...",
            "redirect" => "admin_dashboard.php"
        ]);
    } else {
        // Regular user login
        echo json_encode([
            "success" => true,
            "message" => "Login successful. Redirecting...",
            "redirect" => "index.php"
        ]);
    }
/*
    // Respond with success message
    echo json_encode(["success" => true, "message" => "Login successful. Redirecting..."]);*/
} else {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
}
?>
