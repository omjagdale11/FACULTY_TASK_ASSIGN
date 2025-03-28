<?php
function displayNotifications($user_id, $role) {
    if(isset($_SESSION['notifications'][$user_id]) && !empty($_SESSION['notifications'][$user_id])) {
        echo '<div class="notifications-container">';
        foreach($_SESSION['notifications'][$user_id] as $notification) {
            $icon = $notification['type'] == 'task_assigned' ? 'fa-tasks' : 'fa-check-circle';
            $color = $notification['type'] == 'task_assigned' ? 'primary' : 'success';
            echo '<div class="alert alert-' . $color . ' alert-dismissible fade show" role="alert">';
            echo '<i class="fas ' . $icon . '"></i> ' . htmlspecialchars($notification['message']);
            echo '<button type="button" class="close" data-dismiss="alert" aria-label="Close">';
            echo '<span aria-hidden="true">&times;</span>';
            echo '</button>';
            echo '</div>';
        }
        echo '</div>';
        
        // Clear notifications after displaying
        $_SESSION['notifications'][$user_id] = array();
    }
}
?> 