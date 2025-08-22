<?php
require_once 'config/config.php';

// Redirect based on login status
if (isLoggedIn()) {
    redirectTo('dashboard.php');
} else {
    redirectTo('login.php');
}
?> 