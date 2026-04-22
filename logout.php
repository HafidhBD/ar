<?php
require_once 'config.php';
logActivity(getUserId(), 'logout', 'User logged out', 'user', getUserId());
logoutUser();
redirect('login.php');
