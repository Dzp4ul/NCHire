<?php
session_start();
$_SESSION['user_id'] = 75;
$_SESSION['user_email'] = 'ulatmatic@gmail.com';
header('Location: user/user.php');
exit();
