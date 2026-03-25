<?php
ini_set('display_errors',1); error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) session_start();
echo "<h2>\$_SERVER</h2><pre>" . htmlspecialchars(var_export($_SERVER, true)) . "</pre>";
echo "<h2>\$_POST</h2><pre>" . htmlspecialchars(var_export($_POST, true)) . "</pre>";
echo "<h2>\$_SESSION</h2><pre>" . htmlspecialchars(var_export($_SESSION, true)) . "</pre>";
