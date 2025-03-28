<?php
session_start();
require_once "config.php";

// Check if user is logged in and is an admin
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin"){
    header("location: index.php");
    exit;
}

// Check if task ID is provided
if(!isset($_GET["id"]) || empty($_GET["id"])){
    header("location: admin_dashboard.php");
    exit;
}

$task_id = $_GET["id"];

// Fetch task details
$sql = "SELECT * FROM tasks WHERE id = ?";
if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $task_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $task = mysqli_fetch_assoc($result);
    
    if(!$task){
        header("location: admin_dashboard.php");
        exit;
    }
}

// Handle form submission
if($_SERVER["REQUEST_METHOD"] == "POST"){
    $title = trim($_POST["title"]);
    $description = trim($_POST["description"]);
    $priority = $_POST["priority"];
    $due_date = $_POST["due_date"];
    $status = $_POST["status"];
    
    // Update task
    $update_sql = "UPDATE tasks SET title = ?, description = ?, priority = ?, due_date = ?, status = ? WHERE id = ?";
    if($update_stmt = mysqli_prepare($conn, $update_sql)){
        mysqli_stmt_bind_param($update_stmt, "sssssi", $title, $description, $priority, $due_date, $status, $task_id);
        
        if(mysqli_stmt_execute($update_stmt)){
            header("location: admin_dashboard.php");
            exit;
        } else {
            $error = "Something went wrong. Please try again later.";
        }
        mysqli_stmt_close($update_stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Task - Task Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Header with College Logo and Name -->
    <div class="header-section">
        <div class="container">
            <div class="d-flex align-items-center">
                <img src="bharati.jpg" alt="Bharati Vidyapeeth Logo" class="college-logo" style="height: 80px; width: auto;">
                <div class="college-name">
                    <h1>Bharati Vidyapeeth (Deemed To Be University)</h1>
                    <p>Department of Management Studies(Off Campus), Navi Mumbai</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <img src="DMS.jpg" alt="College Logo" class="college-logo">
                        <h5 class="text-white">Task Management System</h5>
                    </div>
                    <nav>
                        <a href="admin_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                        <a href="create_task.php"><i class="fas fa-plus"></i> Create Task</a>
                        <a href="manage_users.php"><i class="fas fa-users"></i> Manage Users</a>
                        <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Edit Task</h1>
                </div>

                <?php if(isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="title" class="form-label">Task Title</label>
                                <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($task['title']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($task['description']); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="priority" class="form-label">Priority</label>
                                <select class="form-control" id="priority" name="priority" required>
                                    <option value="Low" <?php echo $task['priority'] == 'Low' ? 'selected' : ''; ?>>Low</option>
                                    <option value="Medium" <?php echo $task['priority'] == 'Medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="High" <?php echo $task['priority'] == 'High' ? 'selected' : ''; ?>>High</option>
                                    <option value="Urgent" <?php echo $task['priority'] == 'Urgent' ? 'selected' : ''; ?>>Urgent</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="due_date" class="form-label">Due Date</label>
                                <input type="date" class="form-control" id="due_date" name="due_date" value="<?php echo $task['due_date']; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-control" id="status" name="status" required>
                                    <option value="Pending" <?php echo $task['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="In Progress" <?php echo $task['status'] == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="Completed" <?php echo $task['status'] == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="Overdue" <?php echo $task['status'] == 'Overdue' ? 'selected' : ''; ?>>Overdue</option>
                                </select>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="admin_dashboard.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">Update Task</button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 