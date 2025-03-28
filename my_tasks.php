<?php
session_start();
require_once "config.php";

// Check if user is logged in and is a faculty member
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "faculty"){
    header("location: index.php");
    exit;
}

// Build the base query
$sql = "SELECT t.*, ta.assigned_date, ta.completion_date 
        FROM tasks t 
        JOIN task_assignments ta ON t.id = ta.task_id 
        WHERE ta.user_id = ?";

$params = array($_SESSION["id"]);
$types = "i";

// Apply filters if provided
if(!empty($_GET["priority"])) {
    $sql .= " AND t.priority = ?";
    $params[] = $_GET["priority"];
    $types .= "s";
}

if(!empty($_GET["status"])) {
    $sql .= " AND t.status = ?";
    $params[] = $_GET["status"];
    $types .= "s";
}

if(!empty($_GET["due_date"])) {
    switch($_GET["due_date"]) {
        case "today":
            $sql .= " AND DATE(t.due_date) = CURDATE()";
            break;
        case "week":
            $sql .= " AND t.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
            break;
        case "month":
            $sql .= " AND t.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 MONTH)";
            break;
        case "overdue":
            $sql .= " AND t.due_date < CURDATE() AND t.status != 'Completed'";
            break;
    }
}

$sql .= " ORDER BY t.due_date ASC";

// Execute the query
$tasks = array();
if($stmt = mysqli_prepare($conn, $sql)){
    if(!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while($row = mysqli_fetch_assoc($result)){
        $tasks[] = $row;
    }
}

// Get task statistics
$stats = array(
    'total' => count($tasks),
    'pending' => count(array_filter($tasks, function($task) { return $task['status'] == 'Pending'; })),
    'in_progress' => count(array_filter($tasks, function($task) { return $task['status'] == 'In Progress'; })),
    'completed' => count(array_filter($tasks, function($task) { return $task['status'] == 'Completed'; })),
    'overdue' => count(array_filter($tasks, function($task) { 
        return $task['status'] != 'Completed' && strtotime($task['due_date']) < time(); 
    }))
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Tasks - Task Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #343a40;
            color: white;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            display: block;
        }
        .sidebar a:hover {
            background: #495057;
        }
        .main-content {
            padding: 20px;
        }
        .task-card {
            margin-bottom: 20px;
            transition: transform 0.2s;
        }
        .task-card:hover {
            transform: translateY(-5px);
        }
        .priority-high { border-left: 4px solid #dc3545; }
        .priority-medium { border-left: 4px solid #ffc107; }
        .priority-low { border-left: 4px solid #28a745; }
        .stats-card {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar">
                <h3 class="text-center py-4">Task Manager</h3>
                <nav>
                    <a href="faculty_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="my_tasks.php" class="active"><i class="fas fa-tasks"></i> My Tasks</a>
                    <a href="completed_tasks.php"><i class="fas fa-check-circle"></i> Completed Tasks</a>
                    <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>My Tasks</h2>
                    <div>
                        <span class="badge badge-info mr-2"><?php echo htmlspecialchars($_SESSION["department"]); ?></span>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h5>Total Tasks</h5>
                            <h2><?php echo $stats['total']; ?></h2>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h5>Pending Tasks</h5>
                            <h2><?php echo $stats['pending']; ?></h2>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h5>In Progress</h5>
                            <h2><?php echo $stats['in_progress']; ?></h2>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h5>Completed Tasks</h5>
                            <h2><?php echo $stats['completed']; ?></h2>
                        </div>
                    </div>
                </div>

                <!-- Task Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Priority</label>
                                    <select name="priority" class="form-control">
                                        <option value="">All</option>
                                        <option value="High" <?php echo isset($_GET['priority']) && $_GET['priority'] == 'High' ? 'selected' : ''; ?>>High</option>
                                        <option value="Medium" <?php echo isset($_GET['priority']) && $_GET['priority'] == 'Medium' ? 'selected' : ''; ?>>Medium</option>
                                        <option value="Low" <?php echo isset($_GET['priority']) && $_GET['priority'] == 'Low' ? 'selected' : ''; ?>>Low</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Status</label>
                                    <select name="status" class="form-control">
                                        <option value="">All</option>
                                        <option value="Pending" <?php echo isset($_GET['status']) && $_GET['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="In Progress" <?php echo isset($_GET['status']) && $_GET['status'] == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="Completed" <?php echo isset($_GET['status']) && $_GET['status'] == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Due Date</label>
                                    <select name="due_date" class="form-control">
                                        <option value="">All</option>
                                        <option value="today" <?php echo isset($_GET['due_date']) && $_GET['due_date'] == 'today' ? 'selected' : ''; ?>>Today</option>
                                        <option value="week" <?php echo isset($_GET['due_date']) && $_GET['due_date'] == 'week' ? 'selected' : ''; ?>>This Week</option>
                                        <option value="month" <?php echo isset($_GET['due_date']) && $_GET['due_date'] == 'month' ? 'selected' : ''; ?>>This Month</option>
                                        <option value="overdue" <?php echo isset($_GET['due_date']) && $_GET['due_date'] == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-primary btn-block">Filter Tasks</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tasks List -->
                <div class="row">
                    <?php foreach($tasks as $task): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card task-card priority-<?php echo strtolower($task['priority']); ?>">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($task['title']); ?></h5>
                                    <p class="card-text"><?php echo htmlspecialchars(substr($task['description'], 0, 100)) . '...'; ?></p>
                                    
                                    <div class="mb-3">
                                        <span class="badge badge-<?php echo $task['priority'] == 'High' ? 'danger' : ($task['priority'] == 'Medium' ? 'warning' : 'success'); ?>">
                                            <?php echo htmlspecialchars($task['priority']); ?>
                                        </span>
                                        <span class="badge badge-<?php echo $task['status'] == 'Completed' ? 'success' : ($task['status'] == 'In Progress' ? 'info' : 'warning'); ?>">
                                            <?php echo htmlspecialchars($task['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            Due: <?php echo date('M d, Y', strtotime($task['due_date'])); ?>
                                        </small>
                                        <a href="view_task.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html> 