<?php
    session_start();
    // Unset all session variables
    $_SESSION = array();

    // Destroy the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 42000, '/');
    }

    // Destroy the session
    session_destroy();

    // Redirect to login page
    header("Location: login.php");
    exit();
?>

<?php
    // admin_logout.php
    session_start();

    // Clear admin session variables
    unset($_SESSION['admin_id']);
    unset($_SESSION['is_admin']);

    // Redirect to login
    header("Location: login.php");
    exit();
?>

<!DOCTYPE html>

<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <title></title>
</head>
<body>

</body>
</html>