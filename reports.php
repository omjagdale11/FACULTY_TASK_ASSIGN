<?php
session_start();
require_once "config.php";

// Check if user is logged in and is an admin
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin"){
    header("location: index.php");
    exit;
}

// Function to create pie chart
function createPieChart($data, $title, $filename) {
    $width = 400;
    $height = 300;
    $image = imagecreatetruecolor($width, $height);
    
    // Colors
    $colors = [
        imagecolorallocate($image, 255, 99, 132),  // Red
        imagecolorallocate($image, 54, 162, 235),  // Blue
        imagecolorallocate($image, 255, 206, 86),  // Yellow
        imagecolorallocate($image, 75, 192, 192),  // Green
        imagecolorallocate($image, 153, 102, 255), // Purple
        imagecolorallocate($image, 255, 159, 64),  // Orange
        imagecolorallocate($image, 201, 203, 207), // Gray
        imagecolorallocate($image, 255, 99, 71),   // Tomato
        imagecolorallocate($image, 50, 205, 50),   // Lime Green
        imagecolorallocate($image, 147, 112, 219)  // Medium Purple
    ];
    
    // Background
    $white = imagecolorallocate($image, 255, 255, 255);
    imagefill($image, 0, 0, $white);
    
    // Calculate total
    $total = array_sum($data);
    
    // Draw pie chart
    $start = 0;
    $i = 0;
    foreach($data as $label => $value) {
        $percentage = ($value / $total) * 360;
        $end = $start + $percentage;
        
        // Draw pie slice
        imagefilledarc($image, $width/2, $height/2, $width/2-20, $height/2-20, $start, $end, $colors[$i], IMG_ARC_PIE);
        
        // Draw legend
        $legendX = 20;
        $legendY = 20 + ($i * 20);
        imagefilledrectangle($image, $legendX, $legendY, $legendX + 15, $legendY + 15, $colors[$i]);
        imagestring($image, 2, $legendX + 20, $legendY, "$label: $value", imagecolorallocate($image, 0, 0, 0));
        
        $start = $end;
        $i++;
    }
    
    // Add title
    $titleColor = imagecolorallocate($image, 0, 0, 0);
    imagestring($image, 3, $width/2 - strlen($title)*4, 10, $title, $titleColor);
    
    // Save image
    imagepng($image, $filename);
    imagedestroy($image);
}

// Get task statistics
$stats_sql = "SELECT 
    COUNT(*) as total_tasks,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_tasks,
    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress_tasks,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks,
    SUM(CASE WHEN status = 'Overdue' THEN 1 ELSE 0 END) as overdue_tasks
    FROM tasks";
$stats = mysqli_fetch_assoc(mysqli_query($conn, $stats_sql));

// Get priority statistics
$priority_sql = "SELECT priority, COUNT(*) as count FROM tasks GROUP BY priority";
$priority_result = mysqli_query($conn, $priority_sql);
$priority_stats = array();
while($row = mysqli_fetch_assoc($priority_result)) {
    $priority_stats[$row['priority']] = $row['count'];
}

// Get department statistics
$dept_sql = "SELECT u.department, COUNT(*) as count 
             FROM tasks t 
             JOIN task_assignments ta ON t.id = ta.task_id 
             JOIN users u ON ta.user_id = u.id 
             GROUP BY u.department";
$dept_result = mysqli_query($conn, $dept_sql);
$dept_stats = array();
while($row = mysqli_fetch_assoc($dept_result)) {
    $dept_stats[$row['department']] = $row['count'];
}

// Create charts
$charts_dir = "charts/";
if (!file_exists($charts_dir)) {
    mkdir($charts_dir, 0777, true);
}

createPieChart(
    array(
        'Pending' => $stats['pending_tasks'],
        'In Progress' => $stats['in_progress_tasks'],
        'Completed' => $stats['completed_tasks'],
        'Overdue' => $stats['overdue_tasks']
    ),
    'Task Status Distribution',
    $charts_dir . 'status_chart.png'
);

createPieChart(
    $priority_stats,
    'Task Priority Distribution',
    $charts_dir . 'priority_chart.png'
);

createPieChart(
    $dept_stats,
    'Tasks by Department',
    $charts_dir . 'department_chart.png'
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports - Task Management System</title>
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
                        <img src="images/logo.png" alt="College Logo" class="college-logo">
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
                    <h1 class="h2">Reports</h1>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Task Status Distribution</h5>
                                <img src="charts/status_chart.png" alt="Status Chart" class="img-fluid">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Task Priority Distribution</h5>
                                <img src="charts/priority_chart.png" alt="Priority Chart" class="img-fluid">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Tasks by Department</h5>
                                <img src="charts/department_chart.png" alt="Department Chart" class="img-fluid">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Table -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Task Statistics</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Total Tasks</th>
                                        <th>Pending</th>
                                        <th>In Progress</th>
                                        <th>Completed</th>
                                        <th>Overdue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><?php echo $stats['total_tasks']; ?></td>
                                        <td><?php echo $stats['pending_tasks']; ?></td>
                                        <td><?php echo $stats['in_progress_tasks']; ?></td>
                                        <td><?php echo $stats['completed_tasks']; ?></td>
                                        <td><?php echo $stats['overdue_tasks']; ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 