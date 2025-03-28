<?php
require_once 'config.php';

try {
    // Add completed_date column if it doesn't exist
    $sql = "ALTER TABLE task_assignments ADD COLUMN IF NOT EXISTS completed_date DATETIME DEFAULT NULL";
    if(mysqli_query($conn, $sql)) {
        echo "Column 'completed_date' added successfully to task_assignments table.";
    } else {
        throw new Exception(mysqli_error($conn));
    }
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}

mysqli_close($conn);
?> 