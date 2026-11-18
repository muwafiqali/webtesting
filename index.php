<?php
require_once __DIR__ . '/bootstrap.php';

if (current_user()) {
    redirect('dashboard.php');
}

redirect('login.php');
