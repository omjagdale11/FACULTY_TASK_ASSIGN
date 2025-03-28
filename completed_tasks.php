<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is a faculty member
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    header("Location: index.php");
    exit();
}

// Get completed tasks for the logged-in faculty member
try {
    // First check if the completed_date column exists
    $checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM task_assignments LIKE 'completed_date'");
    if(mysqli_num_rows($checkColumn) == 0) {
        // Column doesn't exist, redirect to add_completed_date.php
        header("Location: add_completed_date.php");
        exit();
    }

    $sql = "SELECT t.*, ta.completed_date 
            FROM tasks t 
            JOIN task_assignments ta ON t.id = ta.task_id 
            WHERE ta.user_id = ? 
            AND ta.completed_date IS NOT NULL 
            ORDER BY ta.completed_date DESC";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $completed_tasks = [];
    while($row = mysqli_fetch_assoc($result)) {
        $completed_tasks[] = $row;
    }
} catch(Exception $e) {
    $error = "Error fetching completed tasks: " . $e->getMessage();
    $completed_tasks = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completed Tasks - Task Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <img src="images/logo.png" alt="College Logo" class="college-logo">
                        <h5 class="text-white">Task Management System</h5>
                    </div>
                    <nav>
                        <a href="faculty_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                        <a href="my_tasks.php"><i class="fas fa-tasks"></i> My Tasks</a>
                        <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Completed Tasks</h1>
                </div>

                <?php if(isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if(empty($completed_tasks)): ?>
                    <div class="alert alert-info">No completed tasks found.</div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach($completed_tasks as $task): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card task-card">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($task['title']); ?></h5>
                                        <p class="card-text"><?php echo htmlspecialchars($task['description']); ?></p>
                                        <div class="task-meta">
                                            <span class="badge bg-success">Completed</span>
                                            <small class="text-muted">
                                                Completed on: <?php echo date('M d, Y', strtotime($task['completed_date'])); ?>
                                            </small>
                                        </div>
                                        <div class="mt-3">
                                            <a href="view_task.php?id=<?php echo $task['id']; ?>" class="btn btn-primary btn-sm">
                                                View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 