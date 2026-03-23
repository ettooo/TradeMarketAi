<?php
// logout.php
require_once __DIR__ . '/config/auth.php';
logoutUser();
header('Location: login.php');
exit;
