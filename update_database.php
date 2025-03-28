<?php
require_once "config.php";

// Add profile_pic column to users table
$sql = "ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_pic VARCHAR(255) DEFAULT NULL";

if(mysqli_query($conn, $sql)){
    echo "Profile picture column added successfully.";
} else {
    echo "Error adding profile picture column: " . mysqli_error($conn);
}

mysqli_close($conn);
?> 