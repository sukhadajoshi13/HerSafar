<?php

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = ''; 
$DB_NAME = 'hersafar';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    error_log('DB connect error: ' . $mysqli->connect_error);
    die('Database connection failed.');
}
$mysqli->set_charset('utf8mb4');
