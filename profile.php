<?php
session_start();
require_once "config.php";

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.php");
    exit;
}

$success_msg = $error_msg = "";
$username = $email = $faculty_name = $phone_number = $department = "";
$username_err = $email_err = $faculty_name_err = $phone_number_err = $department_err = "";

// Fetch user details
$user_sql = "SELECT * FROM users WHERE id = ?";
$user_stmt = mysqli_prepare($conn, $user_sql);
mysqli_stmt_bind_param($user_stmt, "i", $_SESSION["id"]);
mysqli_stmt_execute($user_stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($user_stmt));

// Process form submission
if($_SERVER["REQUEST_METHOD"] == "POST"){
    if(isset($_POST["update_profile"])){
        // Validate username
        if(empty(trim($_POST["username"]))){
            $username_err = "Please enter a username.";
        } else{
            $username = trim($_POST["username"]);
            // Check if username exists for other users
            $check_sql = "SELECT id FROM users WHERE username = ? AND id != ?";
            if($stmt = mysqli_prepare($conn, $check_sql)){
                mysqli_stmt_bind_param($stmt, "si", $username, $_SESSION["id"]);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);
                
                if(mysqli_stmt_num_rows($stmt) > 0){
                    $username_err = "This username is already taken.";
                }
            }
        }
        
        // Validate email
        if(empty(trim($_POST["email"]))){
            $email_err = "Please enter an email.";
        } else{
            $email = trim($_POST["email"]);
            // Check if email exists for other users
            $check_sql = "SELECT id FROM users WHERE email = ? AND id != ?";
            if($stmt = mysqli_prepare($conn, $check_sql)){
                mysqli_stmt_bind_param($stmt, "si", $email, $_SESSION["id"]);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);
                
                if(mysqli_stmt_num_rows($stmt) > 0){
                    $email_err = "This email is already registered.";
                }
            }
        }
        
        // Validate faculty name (for faculty members)
        if($_SESSION["role"] == "faculty"){
            if(empty(trim($_POST["faculty_name"]))){
                $faculty_name_err = "Please enter faculty name.";
            } else{
                $faculty_name = trim($_POST["faculty_name"]);
            }
            
            // Validate phone number
            if(empty(trim($_POST["phone_number"]))){
                $phone_number_err = "Please enter phone number.";
            } else{
                $phone_number = trim($_POST["phone_number"]);
            }
            
            // Validate department
            if(empty(trim($_POST["department"]))){
                $department_err = "Please select department.";
            } else{
                $department = trim($_POST["department"]);
            }
        }
        
        // Check input errors before updating database
        if(empty($username_err) && empty($email_err) && empty($faculty_name_err) && 
           empty($phone_number_err) && empty($department_err)){
            
            // Prepare an update statement
            if($_SESSION["role"] == "admin"){
                $sql = "UPDATE users SET username = ?, email = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "ssi", $username, $email, $_SESSION["id"]);
            } else {
                $sql = "UPDATE users SET username = ?, email = ?, faculty_name = ?, phone_number = ?, department = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "sssssi", $username, $email, $faculty_name, $phone_number, $department, $_SESSION["id"]);
            }
            
            // Attempt to execute the prepared statement
            if(mysqli_stmt_execute($stmt)){
                $success_msg = "Profile updated successfully.";
                // Refresh user data
                mysqli_stmt_execute($user_stmt);
                $user = mysqli_fetch_assoc(mysqli_stmt_get_result($user_stmt));
            } else{
                $error_msg = "Something went wrong. Please try again later.";
            }
            
            mysqli_stmt_close($stmt);
        }
    } elseif(isset($_POST["delete_account"])){
        // Verify password before deleting account
        if(empty(trim($_POST["password"]))){
            $error_msg = "Please enter your password to confirm account deletion.";
        } else {
            $password = trim($_POST["password"]);
            
            // Verify password
            $verify_sql = "SELECT password FROM users WHERE id = ?";
            $verify_stmt = mysqli_prepare($conn, $verify_sql);
            mysqli_stmt_bind_param($verify_stmt, "i", $_SESSION["id"]);
            mysqli_stmt_execute($verify_stmt);
            $result = mysqli_stmt_get_result($verify_stmt);
            $user_data = mysqli_fetch_assoc($result);
            
            if(password_verify($password, $user_data["password"])){
                // Delete user's tasks and assignments
                $delete_tasks_sql = "DELETE FROM task_assignments WHERE user_id = ?";
                $delete_tasks_stmt = mysqli_prepare($conn, $delete_tasks_sql);
                mysqli_stmt_bind_param($delete_tasks_stmt, "i", $_SESSION["id"]);
                mysqli_stmt_execute($delete_tasks_stmt);
                
                // Delete user's file attachments
                $delete_files_sql = "DELETE FROM file_attachments WHERE user_id = ?";
                $delete_files_stmt = mysqli_prepare($conn, $delete_files_sql);
                mysqli_stmt_bind_param($delete_files_stmt, "i", $_SESSION["id"]);
                mysqli_stmt_execute($delete_files_stmt);
                
                // Finally, delete the user account
                $delete_user_sql = "DELETE FROM users WHERE id = ?";
                $delete_user_stmt = mysqli_prepare($conn, $delete_user_sql);
                mysqli_stmt_bind_param($delete_user_stmt, "i", $_SESSION["id"]);
                
                if(mysqli_stmt_execute($delete_user_stmt)){
                    // Unset all session variables
                    $_SESSION = array();
                    
                    // Destroy the session
                    session_destroy();
                    
                    // Redirect to login page
                    header("location: index.php");
                    exit;
                } else {
                    $error_msg = "Something went wrong. Please try again later.";
                }
            } else {
                $error_msg = "Incorrect password. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile - Task Management System</title>
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
        .danger-zone {
            border: 1px solid #dc3545;
            border-radius: 5px;
            padding: 20px;
            margin-top: 20px;
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
                    <a href="profile.php" class="active"><i class="fas fa-user"></i> Profile</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <h3 class="card-title">Profile Information</h3>
                                
                                <?php if($success_msg): ?>
                                    <div class="alert alert-success"><?php echo $success_msg; ?></div>
                                <?php endif; ?>
                                
                                <?php if($error_msg): ?>
                                    <div class="alert alert-danger"><?php echo $error_msg; ?></div>
                                <?php endif; ?>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Name:</strong> <?php echo htmlspecialchars($user["faculty_name"]); ?></p>
                                        <p><strong>Department:</strong> <?php echo htmlspecialchars($user["department"]); ?></p>
                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user["email"]); ?></p>
                                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($user["phone_number"]); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Role:</strong> <?php echo ucfirst(htmlspecialchars($user["role"])); ?></p>
                                        <p><strong>Username:</strong> <?php echo htmlspecialchars($user["username"]); ?></p>
                                        <p><strong>Member Since:</strong> <?php echo date("M d, Y", strtotime($user["created_at"])); ?></p>
                                    </div>
                                </div>
                                
                                <button type="button" class="btn btn-primary mt-3" data-toggle="modal" data-target="#editProfileModal">
                                    <i class="fas fa-edit"></i> Edit Profile
                                </button>
                            </div>
                        </div>
                        
                        <!-- Danger Zone -->
                        <div class="danger-zone">
                            <h4 class="text-danger"><i class="fas fa-exclamation-triangle"></i> Danger Zone</h4>
                            <p class="text-muted">Once you delete your account, there is no going back. Please be certain.</p>
                            <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#deleteAccountModal">
                                <i class="fas fa-trash"></i> Delete Account
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div class="modal fade" id="editProfileModal" tabindex="-1" role="dialog" aria-labelledby="editProfileModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProfileModalLabel">Edit Profile</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($user["username"]); ?>">
                            <span class="invalid-feedback"><?php echo $username_err; ?></span>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($user["email"]); ?>">
                            <span class="invalid-feedback"><?php echo $email_err; ?></span>
                        </div>
                        <?php if($_SESSION["role"] == "faculty"): ?>
                            <div class="form-group">
                                <label>Faculty Name</label>
                                <input type="text" name="faculty_name" class="form-control <?php echo (!empty($faculty_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($user["faculty_name"]); ?>">
                                <span class="invalid-feedback"><?php echo $faculty_name_err; ?></span>
                            </div>
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="text" name="phone_number" class="form-control <?php echo (!empty($phone_number_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($user["phone_number"]); ?>">
                                <span class="invalid-feedback"><?php echo $phone_number_err; ?></span>
                            </div>
                            <div class="form-group">
                                <label>Department</label>
                                <select name="department" class="form-control <?php echo (!empty($department_err)) ? 'is-invalid' : ''; ?>">
                                    <option value="">Select Department</option>
                                    <option value="Computer Science" <?php echo ($user["department"] == "Computer Science") ? "selected" : ""; ?>>Computer Science</option>
                                    <option value="Information Technology" <?php echo ($user["department"] == "Information Technology") ? "selected" : ""; ?>>Information Technology</option>
                                    <option value="Electronics" <?php echo ($user["department"] == "Electronics") ? "selected" : ""; ?>>Electronics</option>
                                    <option value="Mechanical" <?php echo ($user["department"] == "Mechanical") ? "selected" : ""; ?>>Mechanical</option>
                                    <option value="Civil" <?php echo ($user["department"] == "Civil") ? "selected" : ""; ?>>Civil</option>
                                    <option value="Electrical" <?php echo ($user["department"] == "Electrical") ? "selected" : ""; ?>>Electrical</option>
                                </select>
                                <span class="invalid-feedback"><?php echo $department_err; ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Account Modal -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1" role="dialog" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger" id="deleteAccountModalLabel">Delete Account</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete your account? This action cannot be undone.</p>
                    <p>Please enter your password to confirm:</p>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <div class="form-group">
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="confirmDelete" required>
                                <label class="form-check-label" for="confirmDelete">I understand that this action cannot be undone</label>
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_account" class="btn btn-danger">Delete Account</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html> 