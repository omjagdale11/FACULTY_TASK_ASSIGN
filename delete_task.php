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

// First, get the file attachments to delete them
$files_sql = "SELECT file_path FROM file_attachments WHERE task_id = ?";
$files_stmt = mysqli_prepare($conn, $files_sql);
mysqli_stmt_bind_param($files_stmt, "i", $task_id);
mysqli_stmt_execute($files_stmt);
$files_result = mysqli_stmt_get_result($files_stmt);

// Delete physical files
while($file = mysqli_fetch_assoc($files_result)){
    if(file_exists($file['file_path'])){
        unlink($file['file_path']);
    }
}
mysqli_stmt_close($files_stmt);

// Delete related records in order (due to foreign key constraints)
$tables = array(
    "comments",
    "file_attachments",
    "task_assignments",
    "tasks"
);

foreach($tables as $table){
    // Use different WHERE clause for tasks table
    $where_clause = ($table === "tasks") ? "id = ?" : "task_id = ?";
    $delete_sql = "DELETE FROM $table WHERE $where_clause";
    $delete_stmt = mysqli_prepare($conn, $delete_sql);
    mysqli_stmt_bind_param($delete_stmt, "i", $task_id);
    mysqli_stmt_execute($delete_stmt);
    mysqli_stmt_close($delete_stmt);
}

// Redirect back to admin dashboard
header("location: admin_dashboard.php");
exit;
?>