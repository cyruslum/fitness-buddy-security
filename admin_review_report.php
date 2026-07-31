<?php
// admin_review_report.php
session_start();
require 'db.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: admin_login.php");
    exit();
}

$adminId = $_SESSION['user_id'];

// Check if report ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin_dashboard.php");
    exit();
}

$reportId = $_GET['id'];

// Get report details
$reportStmt = $conn->prepare("
    SELECT 
        r.*,
        u.username as reporter_username,
        m.message,
        m.sender_id,
        m.receiver_id,
        s.username as sender_username,
        rc.username as receiver_username
    FROM 
        reports r
    JOIN 
        users u ON r.reporter_id = u.id
    JOIN 
        messages m ON r.message_id = m.id
    JOIN 
        users s ON m.sender_id = s.id
    JOIN 
        users rc ON m.receiver_id = rc.id
    WHERE 
        r.id = :report_id
");
$reportStmt->bindParam(':report_id', $reportId, PDO::PARAM_INT);
$reportStmt->execute();

if ($reportStmt->rowCount() === 0) {
    header("Location: admin_dashboard.php");
    exit();
}

$report = $reportStmt->fetch(PDO::FETCH_ASSOC);

// Check if the reported user is already banned
$bannedStmt = $conn->prepare("
    SELECT * FROM banned_users 
    WHERE user_id = :user_id AND unbanned_at IS NULL
");
$bannedStmt->bindParam(':user_id', $report['sender_id'], PDO::PARAM_INT);
$bannedStmt->execute();
$isAlreadyBanned = ($bannedStmt->rowCount() > 0);

// Get conversation context (a few messages before and after)
$contextStmt = $conn->prepare("
    SELECT 
        m.*,
        s.username as sender_username,
        r.username as receiver_username
    FROM 
        messages m
    JOIN 
        users s ON m.sender_id = s.id
    JOIN 
        users r ON m.receiver_id = r.id
    WHERE 
        (m.sender_id = :sender_id AND m.receiver_id = :receiver_id) OR
        (m.sender_id = :receiver_id AND m.receiver_id = :sender_id)
    ORDER BY 
        m.sent_at
    LIMIT 5
");
$contextStmt->bindParam(':sender_id', $report['sender_id'], PDO::PARAM_INT);
$contextStmt->bindParam(':receiver_id', $report['receiver_id'], PDO::PARAM_INT);
$contextStmt->execute();
$conversation = $contextStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $notes = $_POST['notes'] ?? '';
        
        if ($action === 'dismiss') {
            // Dismiss the report
            $updateStmt = $conn->prepare("
                UPDATE reports
                SET status = 'dismissed', 
                    reviewed_by = :admin_id,
                    reviewed_at = NOW(),
                    notes = :notes
                WHERE id = :report_id
            ");
            $updateStmt->bindParam(':admin_id', $adminId, PDO::PARAM_INT);
            $updateStmt->bindParam(':notes', $notes, PDO::PARAM_STR);
            $updateStmt->bindParam(':report_id', $reportId, PDO::PARAM_INT);
            
            if ($updateStmt->execute()) {
                $message = "Report has been dismissed successfully.";
            } else {
                $error = "Failed to dismiss report.";
            }
            
        } elseif ($action === 'ban') {
            // Ban the user
            $banReason = $_POST['ban_reason'] ?? '';
            $banDuration = $_POST['ban_duration'] ?? 'permanent';
            
            if (empty($banReason)) {
                $error = "Ban reason is required.";
            } else {
                // Start transaction
                $conn->beginTransaction();
                
                try {
                    // Mark report as reviewed
                    $updateStmt = $conn->prepare("
                        UPDATE reports
                        SET status = 'reviewed', 
                            reviewed_by = :admin_id,
                            reviewed_at = NOW(),
                            notes = :notes,
                            action_taken = 'user_banned'
                        WHERE id = :report_id
                    ");
                    $updateStmt->bindParam(':admin_id', $adminId, PDO::PARAM_INT);
                    $updateStmt->bindParam(':notes', $notes, PDO::PARAM_STR);
                    $updateStmt->bindParam(':report_id', $reportId, PDO::PARAM_INT);
                    $updateStmt->execute();
                    
                    // Add user to banned list
                    $banStmt = $conn->prepare("
                        INSERT INTO banned_users (user_id, banned_by, reason, ban_duration)
                        VALUES (:user_id, :admin_id, :reason, :duration)
                    ");
                    $banStmt->bindParam(':user_id', $report['sender_id'], PDO::PARAM_INT);
                    $banStmt->bindParam(':admin_id', $adminId, PDO::PARAM_INT);
                    $banStmt->bindParam(':reason', $banReason, PDO::PARAM_STR);
                    $banStmt->bindParam(':duration', $banDuration, PDO::PARAM_STR);
                    $banStmt->execute();
                    
                    $conn->commit();
                    $message = "User has been banned and report marked as reviewed.";
                } catch (Exception $e) {
                    $conn->rollBack();
                    $error = "Error: " . $e->getMessage();
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Report - Fitness Buddy Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="./css/admin.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: salmon;">
        <div class="container-fluid">
            <a class="navbar-brand" href="admin_dashboard.php">
                <i class="bi bi-shield-lock me-2"></i>Fitness Buddy Admin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php">Dashboard</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php" target="_blank">View Site</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Review Report #<?= $report['id'] ?></h1>
                    <a href="admin_dashboard.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
                    </a>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Report Overview -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Report Overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="border-bottom pb-2 mb-3">Report Details</h6>
                                <div class="mb-3">
                                    <strong>Report ID:</strong> <?= $report['id'] ?>
                                </div>
                                <div class="mb-3">
                                    <strong>Reported by:</strong> <?= htmlspecialchars($report['reporter_username']) ?>
                                </div>
                                <div class="mb-3">
                                    <strong>Report Reason:</strong> <?= htmlspecialchars($report['reason']) ?>
                                </div>
                                <div class="mb-3">
                                    <strong>Reported At:</strong> <?= date('F j, Y, g:i a', strtotime($report['reported_at'])) ?>
                                </div>
                                <div class="mb-3">
                                    <strong>Status:</strong>
                                    <?php if ($report['status'] === 'pending'): ?>
                                        <span class="badge bg-warning">Pending Review</span>
                                    <?php elseif ($report['status'] === 'reviewed'): ?>
                                        <span class="badge bg-success">Reviewed</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Dismissed</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6 class="border-bottom pb-2 mb-3">Reported User Details</h6>
                                <div class="mb-3">
                                    <strong>Username:</strong> <?= htmlspecialchars($report['sender_username']) ?>
                                </div>
                                <div class="mb-3">
                                    <strong>User ID:</strong> <?= $report['sender_id'] ?>
                                </div>
                                <?php if ($isAlreadyBanned): ?>
                                    <div class="alert alert-danger">
                                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                        This user is currently banned.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reported Message -->
                <div class="card mb-4">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">Reported Message</h5>
                    </div>
                    <div class="card-body">
                        <div class="message-bubble message-sender reported-message">
                            <?= nl2br(htmlspecialchars($report['message'])) ?>
                            <div class="text-muted mt-2 small">
                                Sent to: <?= htmlspecialchars($report['receiver_username']) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Conversation Context -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Conversation Context</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($conversation)): ?>
                            <p class="text-muted text-center">No conversation history available.</p>
                        <?php else: ?>
                            <div class="d-flex flex-column">
                                <?php foreach ($conversation as $msg): ?>
                                    <?php 
                                    $isSender = ($msg['sender_id'] == $report['sender_id']);
                                    $isReportedMessage = ($msg['id'] == $report['message_id']);
                                    $bubbleClass = $isSender ? 'message-sender' : 'message-receiver';
                                    $bubbleClass .= $isReportedMessage ? ' reported-message' : '';
                                    ?>
                                    <div class="message-bubble <?= $bubbleClass ?>">
                                        <div class="fw-bold mb-1">
                                            <?= htmlspecialchars($msg['sender_username']) ?>:
                                        </div>
                                        <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                        <div class="text-muted mt-2 small">
                                            <?= date('F j, Y, g:i a', strtotime($msg['sent_at'])) ?>
                                            <?php if ($isReportedMessage): ?>
                                                <span class="badge bg-danger ms-2">Reported Message</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Admin Actions -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Administrative Actions</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($report['status'] !== 'pending'): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                This report has already been processed. Current status: 
                                <strong>
                                    <?= ucfirst($report['status']) ?>
                                </strong>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-header bg-secondary text-white">
                                            Dismiss Report
                                        </div>
                                        <div class="card-body">
                                            <p>If you determine this report does not violate our community guidelines, you can dismiss it.</p>
                                            <form method="POST" id="dismissForm">
                                                <div class="mb-3">
                                                    <label for="dismiss_notes" class="form-label">Notes (Optional)</label>
                                                    <textarea class="form-control" id="dismiss_notes" name="notes" rows="3" placeholder="Add any notes about why this report was dismissed..."></textarea>
                                                </div>
                                                <input type="hidden" name="action" value="dismiss">
                                                <button type="submit" class="btn btn-secondary">
                                                    <i class="bi bi-x-circle me-1"></i> Dismiss Report
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-header bg-danger text-white">
                                            Ban User
                                        </div>
                                        <div class="card-body">
                                            <?php if ($isAlreadyBanned): ?>
                                                <div class="alert alert-warning">
                                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                                    This user is already banned.
                                                </div>
                                            <?php else: ?>
                                                <p>If you determine this message violates our community guidelines, you can ban the user.</p>
                                                <form method="POST" id="banForm">
                                                    <div class="mb-3">
                                                        <label for="ban_reason" class="form-label">Ban Reason</label>
                                                        <textarea class="form-control" id="ban_reason" name="ban_reason" rows="3" required placeholder="Explain why this user is being banned..."></textarea>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="ban_duration" class="form-label">Ban Duration</label>
                                                        <select class="form-select" id="ban_duration" name="ban_duration" required>
                                                            <option value="24_hours">24 Hours</option>
                                                            <option value="7_days">7 Days</option>
                                                            <option value="30_days">30 Days</option>
                                                            <option value="permanent" selected>Permanent</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="ban_notes" class="form-label">Additional Notes (Optional)</label>
                                                        <textarea class="form-control" id="ban_notes" name="notes" rows="2" placeholder="Any additional notes for the admin team..."></textarea>
                                                    </div>
                                                    <input type="hidden" name="action" value="ban">
                                                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#confirmBanModal">
                                                        <i class="bi bi-slash-circle me-1"></i> Ban User
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Confirm Ban Modal -->
    <div class="modal fade" id="confirmBanModal" tabindex="-1" aria-labelledby="confirmBanModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="confirmBanModalLabel">Confirm User Ban</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to ban user <strong><?= htmlspecialchars($report['sender_username']) ?></strong>?</p>
                    <p>This action will:</p>
                    <ul>
                        <li>Prevent the user from logging in</li>
                        <li>Hide their content from other users</li>
                        <li>Send them a notification about the ban</li>
                    </ul>
                    <p class="mb-0"><strong>Please confirm this action.</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmBanButton">
                        <i class="bi bi-slash-circle me-1"></i> Confirm Ban
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle ban confirmation
        document.getElementById('confirmBanButton').addEventListener('click', function() {
            document.getElementById('banForm').submit();
        });
    </script>
</body>
</html>