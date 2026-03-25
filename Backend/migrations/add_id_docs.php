<?php
// migrations/add_id_docs.php
// Run once from CLI or an admin-protected page.

require_once __DIR__ . '/../dbcon.php'; // adjust path to your dbcon.php

$columns = [
  'aadhar_front' => "ALTER TABLE `users` ADD COLUMN `aadhar_front` VARCHAR(255) DEFAULT NULL",
  'aadhar_back'  => "ALTER TABLE `users` ADD COLUMN `aadhar_back` VARCHAR(255) DEFAULT NULL",
  'pan_front'    => "ALTER TABLE `users` ADD COLUMN `pan_front` VARCHAR(255) DEFAULT NULL",
  'pan_back'     => "ALTER TABLE `users` ADD COLUMN `pan_back` VARCHAR(255) DEFAULT NULL",
  'driver_license_scan' => "ALTER TABLE `users` ADD COLUMN `driver_license_scan` VARCHAR(255) DEFAULT NULL",
  'id_doc_verified_at'  => "ALTER TABLE `users` ADD COLUMN `id_doc_verified_at` DATETIME DEFAULT NULL",
];

foreach ($columns as $col => $sql) {
    $res = $mysqli->query("SHOW COLUMNS FROM `users` LIKE '{$col}'");
    if ($res === false) {
        echo "Error checking column {$col}: " . $mysqli->error . PHP_EOL;
        continue;
    }
    if ($res->num_rows === 0) {
        if ($mysqli->query($sql)) {
            echo "Added column {$col}\n";
        } else {
            echo "Failed to add {$col}: " . $mysqli->error . PHP_EOL;
        }
    } else {
        echo "Column {$col} already exists\n";
    }
}

// Optional: add columns to driver_applications
$appCols = [
  'aadhar_front' => "ALTER TABLE `driver_applications` ADD COLUMN `aadhar_front` VARCHAR(255) DEFAULT NULL",
  'aadhar_back'  => "ALTER TABLE `driver_applications` ADD COLUMN `aadhar_back` VARCHAR(255) DEFAULT NULL",
  'license_scan' => "ALTER TABLE `driver_applications` ADD COLUMN `license_scan` VARCHAR(255) DEFAULT NULL",
];

foreach ($appCols as $col => $sql) {
    $res = $mysqli->query("SHOW TABLES LIKE 'driver_applications'");
    if ($res && $res->num_rows) {
        $res2 = $mysqli->query("SHOW COLUMNS FROM `driver_applications` LIKE '{$col}'");
        if ($res2 && $res2->num_rows === 0) {
            if ($mysqli->query($sql)) echo "Added driver_applications.{$col}\n";
            else echo "Failed to add driver_applications.{$col}: " . $mysqli->error . PHP_EOL;
        } else {
            echo "driver_applications.{$col} already exists or query failed\n";
        }
    } else {
        echo "Table driver_applications does not exist — skipping driver_applications columns\n";
    }
}
