<?php
require_once "config.php";

// Add completed_date column to task_assignments table
$sql = "ALTER TABLE task_assignments ADD COLUMN IF NOT EXISTS completed_date DATETIME DEFAULT NULL";

if(mysqli_query($conn, $sql)){
    echo "Column 'completed_date' added successfully to task_assignments table.";
} else {
    echo "Error adding column: " . mysqli_error($conn);
}

mysqli_close($conn);
?> 