<?php
session_start();
require 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$errorMessage = '';
$successMessage = '';

// Get all accepted matches for the current user (excluding blocked users)
$matchesStmt = $conn->prepare("
    SELECT 
        CASE 
            WHEN mr.sender_id = :user_id THEN mr.receiver_id
            ELSE mr.sender_id
        END as match_user_id,
        u.username,
        p.profile_picture
    FROM 
        match_requests mr
    JOIN 
        users u ON (CASE WHEN mr.sender_id = :user_id THEN mr.receiver_id ELSE mr.sender_id END) = u.id
    LEFT JOIN
        profile p ON u.id = p.user_id
    LEFT JOIN
        blocked_users bu ON (bu.blocker_id = :user_id AND bu.blocked_id = (CASE WHEN mr.sender_id = :user_id THEN mr.receiver_id ELSE mr.sender_id END))
    WHERE 
        (mr.sender_id = :user_id OR mr.receiver_id = :user_id)
        AND mr.status = 'accepted'
        AND bu.blocker_id IS NULL
    ORDER BY
        u.username
");
$matchesStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
$matchesStmt->execute();
$matches = $matchesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get list of blocked users
$blockedUsersStmt = $conn->prepare("
    SELECT bu.blocked_id, u.username
    FROM blocked_users bu
    JOIN users u ON bu.blocked_id = u.id
    WHERE bu.blocker_id = :user_id
    ORDER BY u.username
");
$blockedUsersStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
$blockedUsersStmt->execute();
$blockedUsers = $blockedUsersStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle unblock user action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unblock_user'])) {
    $unblockedUserId = (int) $_POST['user_id'];

    $unblockStmt = $conn->prepare("
        DELETE FROM blocked_users 
        WHERE blocker_id = :blocker_id AND blocked_id = :blocked_id
    ");
    $unblockStmt->bindParam(':blocker_id', $userId, PDO::PARAM_INT);
    $unblockStmt->bindParam(':blocked_id', $unblockedUserId, PDO::PARAM_INT);

    if ($unblockStmt->execute()) {
        $successMessage = "User has been unblocked successfully.";
        // Redirect to refresh the page
        header("Location: send_message.php?unblocked=1");
        exit();
    } else {
        $errorMessage = "Failed to unblock user. Please try again.";
    }
}

// Handle block user action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['block_user'])) {
    $blockedUserId = (int) $_POST['user_id'];

    // Check if already blocked
    $checkBlockStmt = $conn->prepare("
        SELECT blocker_id FROM blocked_users 
        WHERE blocker_id = :blocker_id AND blocked_id = :blocked_id
    ");
    $checkBlockStmt->bindParam(':blocker_id', $userId, PDO::PARAM_INT);
    $checkBlockStmt->bindParam(':blocked_id', $blockedUserId, PDO::PARAM_INT);
    $checkBlockStmt->execute();

    if ($checkBlockStmt->rowCount() === 0) {
        // Insert new block
        $blockStmt = $conn->prepare("
            INSERT INTO blocked_users (blocker_id, blocked_id)
            VALUES (:blocker_id, :blocked_id)
        ");
        $blockStmt->bindParam(':blocker_id', $userId, PDO::PARAM_INT);
        $blockStmt->bindParam(':blocked_id', $blockedUserId, PDO::PARAM_INT);

        if ($blockStmt->execute()) {
            $successMessage = "User has been blocked. They will no longer appear in your matches or messages.";
            // Redirect to refresh the page without the blocked user
            header("Location: send_message.php?blocked=1");
            exit();
        } else {
            $errorMessage = "Failed to block user. Please try again.";
        }
    }
}

// Handle report user action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_message'])) {
    $reportedMessageId = (int) $_POST['message_id'];
    $reportReason = $_POST['report_reason'];

    // Insert the report
    $reportStmt = $conn->prepare("
        INSERT INTO reports (message_id, reporter_id, reason, reported_at)
        VALUES (:message_id, :reporter_id, :reason, NOW())
    ");
    $reportStmt->bindParam(':message_id', $reportedMessageId, PDO::PARAM_INT);
    $reportStmt->bindParam(':reporter_id', $userId, PDO::PARAM_INT);
    $reportStmt->bindParam(':reason', $reportReason, PDO::PARAM_STR);

    if ($reportStmt->execute()) {
        $successMessage = "Thank you for your report. Our team will review it shortly.";
    } else {
        $errorMessage = "Failed to submit report. Please try again.";
    }
}

// Handle selected conversation
$selectedUserId = null;
$selectedUsername = '';
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['user'])) {
    $selectedUserId = (int) $_GET['user'];

    // Verify this is an actual match and not blocked
    $validMatchStmt = $conn->prepare("
        SELECT 
            u.username 
        FROM 
            match_requests mr
        JOIN 
            users u ON u.id = :selected_user_id
        LEFT JOIN
            blocked_users bu ON (bu.blocker_id = :user_id AND bu.blocked_id = :selected_user_id)
        WHERE 
            ((mr.sender_id = :user_id AND mr.receiver_id = :selected_user_id) OR 
            (mr.sender_id = :selected_user_id AND mr.receiver_id = :user_id))
            AND mr.status = 'accepted'
            AND bu.blocker_id IS NULL
    ");
    $validMatchStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $validMatchStmt->bindParam(':selected_user_id', $selectedUserId, PDO::PARAM_INT);
    $validMatchStmt->execute();
    $matchResult = $validMatchStmt->fetch(PDO::FETCH_ASSOC);

    if ($matchResult) {
        $selectedUsername = $matchResult['username'];

        // Get conversation history
        $messagesStmt = $conn->prepare("
            SELECT 
                m.*, 
                u.username as sender_name
            FROM 
                messages m
            JOIN 
                users u ON m.sender_id = u.id
            WHERE 
                (m.sender_id = :user_id AND m.receiver_id = :selected_user_id) OR
                (m.sender_id = :selected_user_id AND m.receiver_id = :user_id)
            ORDER BY 
                m.sent_at ASC
        ");
        $messagesStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $messagesStmt->bindParam(':selected_user_id', $selectedUserId, PDO::PARAM_INT);
        $messagesStmt->execute();
        $messages = $messagesStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $errorMessage = "You can only message users you've matched with.";
        $selectedUserId = null;
    }
}

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $receiverId = (int) $_POST['receiver_id'];
    $messageText = trim($_POST['message_text']);

    if (empty($messageText)) {
        $errorMessage = "Message cannot be empty.";
    } else {
        // Verify this is an actual match and not blocked
        $validMatchStmt = $conn->prepare("
            SELECT id FROM match_requests 
            WHERE ((sender_id = :user_id AND receiver_id = :receiver_id) OR 
                  (sender_id = :receiver_id AND receiver_id = :user_id))
                  AND status = 'accepted'
        ");
        $validMatchStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $validMatchStmt->bindParam(':receiver_id', $receiverId, PDO::PARAM_INT);
        $validMatchStmt->execute();

        // Check if blocked
        $checkBlockStmt = $conn->prepare("
            SELECT blocker_id FROM blocked_users 
            WHERE (blocker_id = :user_id AND blocked_id = :receiver_id)
               OR (blocker_id = :receiver_id AND blocked_id = :user_id)
        ");
        $checkBlockStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $checkBlockStmt->bindParam(':receiver_id', $receiverId, PDO::PARAM_INT);
        $checkBlockStmt->execute();

        if ($validMatchStmt->rowCount() > 0 && $checkBlockStmt->rowCount() === 0) {
            // Insert message
            $insertStmt = $conn->prepare("
                INSERT INTO messages (sender_id, receiver_id, message, sent_at)
                VALUES (:sender_id, :receiver_id, :message, NOW())
            ");
            $insertStmt->bindParam(':sender_id', $userId, PDO::PARAM_INT);
            $insertStmt->bindParam(':receiver_id', $receiverId, PDO::PARAM_INT);
            $insertStmt->bindParam(':message', $messageText, PDO::PARAM_STR);

            if ($insertStmt->execute()) {
                $successMessage = "Message sent successfully.";

                // Refresh messages
                $messagesStmt = $conn->prepare("
                    SELECT 
                        m.*, 
                        u.username as sender_name
                    FROM 
                        messages m
                    JOIN 
                        users u ON m.sender_id = u.id
                    WHERE 
                        (m.sender_id = :user_id AND m.receiver_id = :selected_user_id) OR
                        (m.sender_id = :selected_user_id AND m.receiver_id = :user_id)
                    ORDER BY 
                        m.sent_at ASC
                ");
                $messagesStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $messagesStmt->bindParam(':selected_user_id', $receiverId, PDO::PARAM_INT);
                $messagesStmt->execute();
                $messages = $messagesStmt->fetchAll(PDO::FETCH_ASSOC);

                $selectedUserId = $receiverId;

                // Get selected user's name
                $userStmt = $conn->prepare("SELECT username FROM users WHERE id = :user_id");
                $userStmt->bindParam(':user_id', $receiverId, PDO::PARAM_INT);
                $userStmt->execute();
                $userResult = $userStmt->fetch(PDO::FETCH_ASSOC);
                $selectedUsername = $userResult['username'];
            } else {
                $errorMessage = "Failed to send message. Please try again.";
            }
        } else {
            if ($checkBlockStmt->rowCount() > 0) {
                $errorMessage = "You cannot message this user due to blocking.";
            } else {
                $errorMessage = "You can only message users you've matched with.";
            }
        }
    }
}

// Function to format date
function formatMessageDate($dateString)
{
    $date = new DateTime($dateString);
    $now = new DateTime();
    $diff = $now->diff($date);

    if ($diff->d == 0) {
        // Today
        return "Today at " . $date->format('g:i A');
    } elseif ($diff->d == 1) {
        // Yesterday
        return "Yesterday at " . $date->format('g:i A');
    } else {
        // Earlier
        return $date->format('M j, Y g:i A');
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Fitness Buddy</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="./css/navbar.css">
    <link rel="stylesheet" href="./css/send_message.css">
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

    <div class="container mt-4 mb-4">
        <h2 class="mb-4">Messages</h2>

        <?php if (isset($errorMessage) && !empty($errorMessage)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($errorMessage) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($successMessage) && !empty($successMessage)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($successMessage) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <!-- After the error and success messages, before the if(empty($matches)) check -->
        <?php if (isset($_GET['unblocked']) && $_GET['unblocked'] == 1): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                User has been unblocked successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Add a button to access blocked users management even when there are no matches -->
        <div class="d-flex justify-content-end mb-3">
            <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#manageBlockedModal">
                <i class="fas fa-user-slash me-2"></i> Manage Blocked Users
            </button>
        </div>

        <!-- Move the Manage Blocked Users modal here, outside of any conditionals -->
        <div class="modal fade" id="manageBlockedModal" tabindex="-1" aria-labelledby="manageBlockedModalLabel"
            aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="manageBlockedModalLabel">Manage Blocked Users
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php if (empty($blockedUsers)): ?>
                            <p class="text-center">You haven't blocked any users.</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($blockedUsers as $blockedUser): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><?= htmlspecialchars($blockedUser['username']) ?></span>
                                        <form method="POST">
                                            <input type="hidden" name="user_id" value="<?= $blockedUser['blocked_id'] ?>">
                                            <button type="submit" name="unblock_user" class="btn btn-sm btn-outline-success">
                                                <i class="fas fa-user-check me-1"></i> Unblock
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($_GET['blocked']) && $_GET['blocked'] == 1): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                User has been blocked successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($matches)): ?>
            <div class="alert no-matches-message" role="alert">
                <h4 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>No matches yet!</h4>
                <p>You need to match with other users before you can start messaging them. Head over to the <a
                        href="matches.php" class="alert-link">Matches</a> page to find potential workout buddies!</p>
            </div>
        <?php else: ?>
            <div class="row">
                <div class="col-12">
                    <div class="messaging-container shadow">
                        <div class="row g-0 h-100">
                            <!-- Contacts sidebar -->
                            <div class="col-md-4 col-lg-3 contacts-sidebar">
                                <div class="p-3 border-bottom bg-white">
                                    <h5 class="mb-0">Your Matches</h5>
                                </div>
                                <div class="contacts-list">
                                    <?php foreach ($matches as $match): ?>
                                        <a href="?user=<?= $match['match_user_id'] ?>" class="text-decoration-none text-dark">
                                            <div
                                                class="contact-item p-3 d-flex align-items-center <?= ($selectedUserId == $match['match_user_id']) ? 'active' : '' ?>">
                                                <?php if (!empty($match['profile_picture'])): ?>
                                                    <img src="<?= htmlspecialchars($match['profile_picture']) ?>" alt="Profile"
                                                        class="profile-pic me-3">
                                                <?php else: ?>
                                                    <div class="profile-pic-placeholder me-3">
                                                        <?= strtoupper(substr($match['username'], 0, 1)) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <h6 class="mb-0"><?= htmlspecialchars($match['username']) ?></h6>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Chat area -->
                            <div class="col-md-8 col-lg-9 chat-area">
                                <?php if ($selectedUserId): ?>
                                    <!-- Chat header -->
                                    <div class="chat-header d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">Chat with <?= htmlspecialchars($selectedUsername) ?></h5>

                                        <!-- Action dropdown (Block) -->
                                        <div class="dropdown action-dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" type="button"
                                                data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <button class="dropdown-item" type="button" data-bs-toggle="modal"
                                                        data-bs-target="#blockUserModal">
                                                        <i class="fas fa-ban me-2"></i> Block User
                                                    </button>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- Messages -->
                                    <div class="messages-container" id="messagesContainer">
                                        <?php if (empty($messages)): ?>
                                            <div class="text-center text-muted my-5">
                                                <p>No messages yet. Start the conversation!</p>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($messages as $message): ?>
                                                <div
                                                    class="message <?= ($message['sender_id'] == $userId) ? 'message-sent' : 'message-received' ?>">
                                                    <?= nl2br(htmlspecialchars($message['message'])) ?>
                                                    <span class="message-time">
                                                        <?= formatMessageDate($message['sent_at']) ?>
                                                    </span>
                                                    <?php if ($message['sender_id'] != $userId): ?>
                                                        <div class="message-actions mt-1 text-end">
                                                            <a href="#" class="text-muted small" data-bs-toggle="modal"
                                                                data-bs-target="#reportMessageModal" data-message-id="<?= $message['id'] ?>"
                                                                data-message-text="<?= htmlspecialchars(substr($message['message'], 0, 30)) ?>...">
                                                                <i class="fas fa-flag"></i> Report
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Message input -->
                                    <div class="message-input">
                                        <form method="POST" class="d-flex">
                                            <input type="hidden" name="receiver_id" value="<?= $selectedUserId ?>">
                                            <textarea class="form-control me-2" name="message_text"
                                                placeholder="Type your message..." rows="2" required></textarea>
                                            <button type="submit" name="send_message" class="btn btn-primary"
                                                style="background-color: salmon; border-color: salmon;">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Block User Modal -->
                                    <div class="modal fade" id="blockUserModal" tabindex="-1"
                                        aria-labelledby="blockUserModalLabel" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="blockUserModalLabel">Block User</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                        aria-label="Close"></button>
                                                </div>
                                                <form method="POST" id="blockUserForm">
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to block
                                                            <?= htmlspecialchars($selectedUsername) ?>? They will no longer be
                                                            able to message you, and you won't see them in your matches.
                                                        </p>
                                                        <input type="hidden" name="user_id" value="<?= $selectedUserId ?>">
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary"
                                                            data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="block_user" class="btn btn-danger">Block
                                                            User</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Report Message Modal -->
                                    <div class="modal fade" id="reportMessageModal" tabindex="-1"
                                        aria-labelledby="reportMessageModalLabel" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="reportMessageModalLabel">Report Message</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                        aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Please let us know why you're reporting this message:</p>
                                                    <p class="text-muted" id="reportedMessagePreview"></p>
                                                    <form method="POST" id="reportMessageForm">
                                                        <input type="hidden" name="message_id" id="reportedMessageId">
                                                        <div class="mb-3">
                                                            <select class="form-select" name="report_reason" required>
                                                                <option value="">Select a reason</option>
                                                                <option value="inappropriate_content">Inappropriate content
                                                                </option>
                                                                <option value="harassment">Harassment</option>
                                                                <option value="spam">Spam message</option>
                                                                <option value="threatening">Threatening language</option>
                                                                <option value="other">Other</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="report_details" class="form-label">Additional
                                                                details:</label>
                                                            <textarea class="form-control" id="report_details"
                                                                name="report_details" rows="3"
                                                                placeholder="Please provide any additional information that will help us investigate..."></textarea>
                                                        </div>
                                                    </form>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary"
                                                        data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" form="reportMessageForm" name="report_message"
                                                        class="btn btn-danger">Submit Report</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- Empty state -->
                                    <div class="empty-state">
                                        <i class="fas fa-comments"></i>
                                        <h4>Select a match to start messaging</h4>
                                        <p>Choose one of your fitness buddies from the list to start a conversation.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-scroll to bottom of messages
        function scrollToBottom() {
            const messagesContainer = document.getElementById('messagesContainer');
            if (messagesContainer) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        }

        // Scroll to bottom on page load
        window.onload = scrollToBottom;

        // Set up report message modal
        document.addEventListener('DOMContentLoaded', function () {
            const reportMessageModal = document.getElementById('reportMessageModal');
            if (reportMessageModal) {
                reportMessageModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    const messageId = button.getAttribute('data-message-id');
                    const messageText = button.getAttribute('data-message-text');

                    document.getElementById('reportedMessageId').value = messageId;
                    document.getElementById('reportedMessagePreview').textContent = messageText;
                });
            }
        });
    </script>
</body>

</html>