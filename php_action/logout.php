<?php
require_once __DIR__ . '/../config/config.php';

// Destroy session and redirect to login
if (isLoggedIn()) {
    session_destroy();
}

redirectTo('login.php');
?> 
