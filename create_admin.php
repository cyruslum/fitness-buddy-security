<!-- Jag -->
<?php
// creates an admin account with php hashing + salting
require 'db.php';

$adminUsername = 'admin1';
$adminEmail = 'admin1@fitnessbuddy.com';
$plainTextPassword = 'adminPassword@1';
$hashedPassword = password_hash($plainTextPassword, PASSWORD_DEFAULT);

// Check if an admin user exists
$checkStmt = $conn->prepare("SELECT id FROM users WHERE email = :email OR username = :username");
$checkStmt->bindParam(':email', $adminEmail);
$checkStmt->bindParam(':username', $adminUsername);
$checkStmt->execute();

if($checkStmt->rowCount() === 0) {
    // Insert admin sql statement
    $stmt = $conn->prepare("
        INSERT INTO users (username, email, password_hash, is_admin, profile_completed)
        VALUES (:username, :email, :password_hash, 1, 1)
    ");

    $stmt->bindParam(':username', $adminUsername);
    $stmt->bindParam(':email', $adminEmail);
    $stmt->bindParam(':password_hash', $hashedPassword);

    if ($stmt->execute()) {
        echo "Admin user created successfully!";
    } else {
        echo "Errorc reating admin user.";
    }
} else {
    echo "Admin user already exists.";
}
?>