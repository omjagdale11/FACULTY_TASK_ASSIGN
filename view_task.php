<?php
session_start();
require_once "config.php";

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.php");
    exit;
}

// Check if task ID is provided
if(!isset($_GET["id"]) || empty($_GET["id"])){
    header("location: " . ($_SESSION["role"] == "admin" ? "admin_dashboard.php" : "faculty_dashboard.php"));
    exit;
}

$task_id = $_GET["id"];

// Fetch task details
$sql = "SELECT t.*, u.username as created_by_name, 
        ta.assigned_date, ta.completion_date,
        a.username as assigned_to_name, a.faculty_name, a.department,
        ta.user_id as assigned_user_id
        FROM tasks t 
        LEFT JOIN users u ON t.created_by = u.id
        LEFT JOIN task_assignments ta ON t.id = ta.task_id
        LEFT JOIN users a ON ta.user_id = a.id
        WHERE t.id = ?";

if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $task_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if($task = mysqli_fetch_assoc($result)){
        // Check if user has permission to view this task
        if($_SESSION["role"] == "faculty" && $task["assigned_user_id"] != $_SESSION["id"]){
            header("location: faculty_dashboard.php");
            exit;
        }
    } else {
        header("location: " . ($_SESSION["role"] == "admin" ? "admin_dashboard.php" : "faculty_dashboard.php"));
        exit;
    }
}

// Handle status update
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["status"])){
    $new_status = $_POST["status"];
    
    // Update task status
    $update_sql = "UPDATE tasks SET status = ? WHERE id = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "si", $new_status, $task_id);
    
    if(mysqli_stmt_execute($update_stmt)){
        // Update completion date if status is Completed
        if($new_status == "Completed"){
            $completion_sql = "UPDATE task_assignments SET completion_date = NOW() WHERE task_id = ? AND user_id = ?";
            $completion_stmt = mysqli_prepare($conn, $completion_sql);
            mysqli_stmt_bind_param($completion_stmt, "ii", $task_id, $_SESSION["id"]);
            mysqli_stmt_execute($completion_stmt);
            mysqli_stmt_close($completion_stmt);
        }
        
        // Store notification in session for admin
        if(!isset($_SESSION['notifications']['admin'])){
            $_SESSION['notifications']['admin'] = array();
        }
        $_SESSION['notifications']['admin'][] = array(
            'type' => 'task_completed',
            'message' => 'Task "' . $task["title"] . '" has been completed by ' . $_SESSION["faculty_name"],
            'task_id' => $task_id,
            'timestamp' => date('Y-m-d H:i:s')
        );
        
        $success_msg = "Task marked as completed successfully.";
    } else {
        $error_msg = "Something went wrong. Please try again later.";
    }
    
    mysqli_stmt_close($update_stmt);
}

// Fetch comments
$comments_sql = "SELECT c.*, u.username, u.faculty_name 
                 FROM comments c 
                 JOIN users u ON c.user_id = u.id 
                 WHERE c.task_id = ? 
                 ORDER BY c.created_at DESC";
$comments = array();
if($stmt = mysqli_prepare($conn, $comments_sql)){
    mysqli_stmt_bind_param($stmt, "i", $task_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while($row = mysqli_fetch_assoc($result)){
        $comments[] = $row;
    }
}

// Handle new comment submission
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["comment"])){
    $comment = trim($_POST["comment"]);
    
    if(!empty($comment)){
        $comment_sql = "INSERT INTO comments (task_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())";
        $comment_stmt = mysqli_prepare($conn, $comment_sql);
        mysqli_stmt_bind_param($comment_stmt, "iis", $task_id, $_SESSION["id"], $comment);
        
        if(mysqli_stmt_execute($comment_stmt)){
            header("location: view_task.php?id=" . $task_id);
            exit;
        }
        
        mysqli_stmt_close($comment_stmt);
    }
}

// Get task assignments
$assign_sql = "SELECT ta.*, u.faculty_name, u.department 
               FROM task_assignments ta 
               JOIN users u ON ta.user_id = u.id 
               WHERE ta.task_id = ?";
$assign_stmt = mysqli_prepare($conn, $assign_sql);
mysqli_stmt_bind_param($assign_stmt, "i", $task_id);
mysqli_stmt_execute($assign_stmt);
$assignments = mysqli_stmt_get_result($assign_stmt);

// Get task attachments
$attach_sql = "SELECT fa.*, u.faculty_name 
               FROM file_attachments fa 
               JOIN users u ON fa.user_id = u.id 
               WHERE fa.task_id = ? 
               ORDER BY fa.upload_date DESC";
$attach_stmt = mysqli_prepare($conn, $attach_sql);
mysqli_stmt_bind_param($attach_stmt, "i", $task_id);
mysqli_stmt_execute($attach_stmt);
$attachments = mysqli_stmt_get_result($attach_stmt);

// Handle file upload
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['submission'])) {
    $upload_dir = "uploads/tasks/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    foreach($_FILES['submission']['tmp_name'] as $key => $tmp_name) {
        $file_name = $_FILES['submission']['name'][$key];
        $file_type = $_FILES['submission']['type'][$key];
        $file_size = $_FILES['submission']['size'][$key];
        
        // Generate unique filename
        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
        $unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
        $file_path = $upload_dir . $unique_filename;
        
        if(move_uploaded_file($tmp_name, $file_path)) {
            // Insert file attachment record
            $file_sql = "INSERT INTO file_attachments (task_id, user_id, file_name, file_path, file_type, is_submission) VALUES (?, ?, ?, ?, ?, 1)";
            $file_stmt = mysqli_prepare($conn, $file_sql);
            mysqli_stmt_bind_param($file_stmt, "iisss", $task_id, $_SESSION["id"], $file_name, $file_path, $file_type);
            mysqli_stmt_execute($file_stmt);
            mysqli_stmt_close($file_stmt);
        }
    }
    
    // Update task status to In Progress if it's Pending
    $update_sql = "UPDATE tasks SET status = 'In Progress' WHERE id = ? AND status = 'Pending'";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "i", $task_id);
    mysqli_stmt_execute($update_stmt);
    mysqli_stmt_close($update_stmt);
    
    // Refresh the page
    header("location: view_task.php?id=" . $task_id);
    exit();
}

// Handle task completion
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["complete_task"])) {
    $complete_sql = "UPDATE tasks SET status = 'Completed' WHERE id = ?";
    $complete_stmt = mysqli_prepare($conn, $complete_sql);
    mysqli_stmt_bind_param($complete_stmt, "i", $task_id);
    mysqli_stmt_execute($complete_stmt);
    mysqli_stmt_close($complete_stmt);
    
    // Refresh the page
    header("location: view_task.php?id=" . $task_id);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Task - Task Management System</title>
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
        .task-header {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .priority-high { border-left: 4px solid #dc3545; }
        .priority-medium { border-left: 4px solid #ffc107; }
        .priority-low { border-left: 4px solid #28a745; }
        .priority-urgent { border-left: 4px solid #dc3545; }
        .comment-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .attachment-list {
            list-style: none;
            padding: 0;
        }
        .attachment-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border: 1px solid #ddd;
            margin-bottom: 5px;
            border-radius: 5px;
        }
        .attachment-item i {
            margin-right: 10px;
            font-size: 1.2em;
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
                    <?php if($_SESSION["role"] == "admin"): ?>
                        <a href="admin_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                        <a href="create_task.php"><i class="fas fa-plus"></i> Create Task</a>
                        <a href="manage_users.php"><i class="fas fa-users"></i> Manage Users</a>
                        <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
                    <?php else: ?>
                        <a href="faculty_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                        <a href="my_tasks.php"><i class="fas fa-tasks"></i> My Tasks</a>
                        <a href="completed_tasks.php"><i class="fas fa-check-circle"></i> Completed Tasks</a>
                    <?php endif; ?>
                    <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="task-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2><?php echo htmlspecialchars($task["title"]); ?></h2>
                        <span class="badge badge-<?php 
                            echo $task["priority"] == "High" ? "danger" : 
                                ($task["priority"] == "Medium" ? "warning" : 
                                ($task["priority"] == "Low" ? "success" : "danger")); 
                        ?>">
                            <?php echo htmlspecialchars($task["priority"]); ?>
                        </span>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">
                            Created by: <?php echo htmlspecialchars($task["created_by_name"]); ?> | 
                            Created on: <?php echo date("M d, Y", strtotime($task["created_at"])); ?>
                        </small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Description</h5>
                                <p class="card-text"><?php echo nl2br(htmlspecialchars($task["description"])); ?></p>
                                
                                <h5 class="card-title mt-4">Task Details</h5>
                                <table class="table">
                                    <tr>
                                        <th>Status:</th>
                                        <td>
                                            <?php if($task["status"] != "Completed"): ?>
                                                <form method="post" class="d-inline">
                                                    <select name="status" class="form-control form-control-sm d-inline-block w-auto" onchange="this.form.submit()">
                                                        <option value="Pending" <?php echo $task["status"] == "Pending" ? "selected" : ""; ?>>Pending</option>
                                                        <option value="In Progress" <?php echo $task["status"] == "In Progress" ? "selected" : ""; ?>>In Progress</option>
                                                        <option value="Completed" <?php echo $task["status"] == "Completed" ? "selected" : ""; ?>>Completed</option>
                                                    </select>
                                                </form>
                                            <?php else: ?>
                                                <span class="badge badge-success">Completed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Due Date:</th>
                                        <td><?php echo date("M d, Y", strtotime($task["due_date"])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Assigned To:</th>
                                        <td><?php echo htmlspecialchars($task["faculty_name"] . " (" . $task["department"] . ")"); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Assigned Date:</th>
                                        <td><?php echo date("M d, Y", strtotime($task["assigned_date"])); ?></td>
                                    </tr>
                                    <?php if($task["completion_date"]): ?>
                                        <tr>
                                            <th>Completion Date:</th>
                                            <td><?php echo date("M d, Y", strtotime($task["completion_date"])); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Quick Actions</h5>
                                <?php if($_SESSION["role"] == "admin"): ?>
                                    <a href="edit_task.php?id=<?php echo $task_id; ?>" class="btn btn-primary btn-block mb-2">
                                        <i class="fas fa-edit"></i> Edit Task
                                    </a>
                                    <a href="delete_task.php?id=<?php echo $task_id; ?>" class="btn btn-danger btn-block" onclick="return confirm('Are you sure you want to delete this task?')">
                                        <i class="fas fa-trash"></i> Delete Task
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Comments Section -->
                <div class="comment-section">
                    <h4>Comments</h4>
                    
                    <!-- Comment Form -->
                    <form method="post" class="mb-4">
                        <div class="form-group">
                            <textarea name="comment" class="form-control" rows="3" placeholder="Add a comment..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Post Comment</button>
                    </form>

                    <!-- Comments List -->
                    <?php foreach($comments as $comment): ?>
                        <div class="card mb-2">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <h6 class="card-subtitle mb-2 text-muted">
                                        <?php echo htmlspecialchars($comment["faculty_name"]); ?>
                                    </h6>
                                    <small class="text-muted">
                                        <?php echo date("M d, Y H:i", strtotime($comment["created_at"])); ?>
                                    </small>
                                </div>
                                <p class="card-text"><?php echo nl2br(htmlspecialchars($comment["comment"])); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <h5>Assigned To</h5>
                        <ul class="list-group">
                            <?php while($assignment = mysqli_fetch_assoc($assignments)): ?>
                                <li class="list-group-item">
                                    <?php echo htmlspecialchars($assignment['faculty_name'] . ' (' . $assignment['department'] . ')'); ?>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                    
                    <div class="col-md-4">
                        <h5>Attachments</h5>
                        <ul class="attachment-list">
                            <?php while($attachment = mysqli_fetch_assoc($attachments)): ?>
                                <li class="attachment-item">
                                    <?php
                                    $icon = 'fa-file';
                                    if(strpos($attachment['file_type'], 'image') !== false) {
                                        $icon = 'fa-image';
                                    } elseif(strpos($attachment['file_type'], 'pdf') !== false) {
                                        $icon = 'fa-file-pdf';
                                    } elseif(strpos($attachment['file_type'], 'word') !== false) {
                                        $icon = 'fa-file-word';
                                    }
                                    ?>
                                    <i class="fas <?php echo $icon; ?>"></i>
                                    <a href="<?php echo htmlspecialchars($attachment['file_path']); ?>" target="_blank">
                                        <?php echo htmlspecialchars($attachment['file_name']); ?>
                                    </a>
                                    <small class="text-muted ml-2">
                                        (<?php echo $attachment['is_submission'] ? 'Submitted by ' . $attachment['faculty_name'] : 'Original attachment'; ?>)
                                    </small>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                        
                        <?php if($_SESSION['role'] == 'faculty' && $task['status'] != 'Completed'): ?>
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?id=" . $task_id; ?>" method="post" enctype="multipart/form-data" class="mt-4">
                                <div class="form-group">
                                    <label>Submit Files</label>
                                    <input type="file" name="submission[]" class="form-control-file" multiple>
                                    <small class="form-text text-muted">You can select multiple files to submit.</small>
                                </div>
                                <button type="submit" class="btn btn-primary">Submit Files</button>
                            </form>
                            
                            <?php if($task['status'] == 'In Progress'): ?>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?id=" . $task_id; ?>" method="post" class="mt-3">
                                    <button type="submit" name="complete_task" class="btn btn-success">
                                        Mark as Completed
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html> 