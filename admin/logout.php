<?php
session_start();

// Only unset admin-specific session variables (keep applicant login if exists)
unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_id']);
unset($_SESSION['admin_name']);
unset($_SESSION['admin_email']);
unset($_SESSION['admin_role']);
unset($_SESSION['admin_department']);
unset($_SESSION['admin_profile_picture']);
unset($_SESSION['password_change_required']);
unset($_SESSION['nchire_chatbot_state_v2']);
unset($_SESSION['nchire_chatbot_history']);
unset($_SESSION['nchire_chatbot_context']);

header('Location: ../public/index.php');
exit;
?>
