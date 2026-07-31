<!-- Jag -->
<?php
session_start();
require 'db.php';

// Check if the user is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit();
}

$adminId = $_SESSION['user_id'];

// Get stats for dashboard
$stats = [
    'total_users' => 0,
    'total_reports' => 0,
    'pending_reports' => 0,
    'banned_users' => 0
];

// Search Queries for dashboard stats
    // Get total users query
    $userStmt = $conn->query("SELECT COUNT(*) FROM users WHERE is_admin = 0");
    $stats['total_users'] = $userStmt->fetchColumn();

    // Get total reports
    $reportStmt = $conn->query("SELECT COUNT(*) FROM reports");
    $stats['total_reports'] = $reportStmt->fetchColumn();

    // Get pending reports
    $pendingStmt = $conn->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'");
    $stats['pending_reports'] = $pendingStmt->fetchColumn();

    // Get banned users
    $bannedStmt = $conn->query("SELECT COUNT(*) FROM banned_users WHERE unbanned_at IS NULL");
    $stats['banned_users'] = $bannedStmt->fetchColumn();

// Searching
$search = $_GET['search'] ?? '';
$searchWhere = '';
$params = [];

if (!empty($search)) {
    $searchWhere = "AND (u.username LIKE :search OR r.username LIKE :search OR m.message LIKE :search)";
    $params[':search'] = "%$search%";
}

// Get reported messages
$page = max(1, $_GET['page'] ?? 1);
$perPage = 10;
$offset = ($page - 1) * $perPage;

$reportsQuery = "
    SELECT 
        r.id, 
        r.message_id, 
        r.reporter_id, 
        r.reason, 
        r.reported_at,
        r.status,
        u.username as reporter_username,
        m.message,
        m.sender_id,
        s.username as sender_username
    FROM 
        reports r
    JOIN 
        users u ON r.reporter_id = u.id
    JOIN 
        messages m ON r.message_id = m.id
    JOIN 
        users s ON m.sender_id = s.id
    WHERE 
        1=1 $searchWhere
    ORDER BY 
        CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END,
        r.reported_at DESC
    LIMIT :offset, :limit
";

$countQuery = "
    SELECT 
        COUNT(*) 
    FROM 
        reports r
    JOIN 
        users u ON r.reporter_id = u.id
    JOIN 
        messages m ON r.message_id = m.id
    JOIN 
        users s ON m.sender_id = s.id
    WHERE 
        1=1 $searchWhere
";

$reportsStmt = $conn->prepare($reportsQuery);
$reportsStmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$reportsStmt->bindParam(':limit', $perPage, PDO::PARAM_INT);

$countStmt = $conn->prepare($countQuery);

if (!empty($search)) {
    $reportsStmt->bindParam(':search', $params[':search'], PDO::PARAM_STR);
    $countStmt->bindParam(':search', $params[':search'], PDO::PARAM_STR);
}

$reportsStmt->execute();
$countStmt->execute();

$reports = $reportsStmt->fetchAll(PDO::FETCH_ASSOC);
$totalReports = $countStmt->fetchColumn();
$totalPages = ceil($totalReports / $perPage);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Fitness Buddy</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="./css/admin.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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
                        <a class="nav-link active" href="admin_dashboard.php">Dashboard</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php" target="_blank">View Site</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Total Users</h6>
                                        <h2 class="mb-0"><?= $stats['total_users'] ?></h2>
                                    </div>
                                    <i class="bi bi-people-fill" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Pending Reports</h6>
                                        <h2 class="mb-0"><?= $stats['pending_reports'] ?></h2>
                                    </div>
                                    <i class="bi bi-flag-fill" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Total Reports</h6>
                                        <h2 class="mb-0"><?= $stats['total_reports'] ?></h2>
                                    </div>
                                    <i class="bi bi-journal-check" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-danger">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Banned Users</h6>
                                        <h2 class="mb-0"><?= $stats['banned_users'] ?></h2>
                                    </div>
                                    <i class="bi bi-slash-circle-fill" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Recent Reports -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Recent Reports</h5>
                    </div>
                    <div class="card-body">
                        <form action="" method="GET" class="mb-4">
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="Search by username or message content" name="search" value="<?= htmlspecialchars($search) ?>">
                                <button class="btn btn-primary" type="submit">Search</button>
                                <?php if (!empty($search)): ?>
                                    <a href="admin_dashboard.php" class="btn btn-outline-secondary">Clear</a>
                                <?php endif; ?>
                            </div>
                        </form>

                        <?php if (empty($reports)): ?>
                            <div class="alert alert-info">No reports found.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Reporter</th>
                                            <th>Reported User</th>
                                            <th>Message</th>
                                            <th>Reason</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reports as $report): ?>
                                            <tr>
                                                <td><?= $report['id'] ?></td>
                                                <td><?= htmlspecialchars($report['reporter_username']) ?></td>
                                                <td><?= htmlspecialchars($report['sender_username']) ?></td>
                                                <td>
                                                    <?= htmlspecialchars(substr($report['message'], 0, 30)) ?>
                                                    <?= strlen($report['message']) > 30 ? '...' : '' ?>
                                                </td>
                                                <td><?= htmlspecialchars($report['reason']) ?></td>
                                                <td><?= date('M j, Y g:i a', strtotime($report['reported_at'])) ?></td>
                                                <td>
                                                    <?php if ($report['status'] === 'pending'): ?>
                                                        <span class="badge bg-warning">Pending</span>
                                                    <?php elseif ($report['status'] === 'reviewed'): ?>
                                                        <span class="badge bg-success">Reviewed</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Dismissed</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="admin_review_report.php?id=<?= $report['id'] ?>" class="btn btn-sm btn-primary">
                                                        Review
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-center">
                                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">Previous</a>
                                        </li>
                                        
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                                <a class="page-link" href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">Next</a>
                                        </li>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>