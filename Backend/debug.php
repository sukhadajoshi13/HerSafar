<?php
ini_set('display_errors',1);
error_reporting(E_ALL);
ob_start();

echo "<h3>Debug wrapper</h3>";
try {
    require_once __DIR__ . '/functions.php';
    echo "Included functions.php OK<br>";
} catch (Throwable $e) {
    echo "<pre>Throwable: " . $e->getMessage() . "</pre>";
}
$buf = ob_get_clean();
echo nl2br(htmlentities($buf));
