<?php

require_once 'functions.php'; 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

logout_user(true);

header('Location: index.php');
exit;
