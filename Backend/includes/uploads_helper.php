<?php

function ensure_dir_writable($dir, &$err = null) {
    $err = null;
    $dir = rtrim($dir, DIRECTORY_SEPARATOR);

    if (is_dir($dir)) {
        if (!is_writable($dir)) {
            $err = "Directory exists but is not writable: {$dir} (perms=" . substr(sprintf('%o', fileperms($dir)), -4) . ").";
            return false;
        }
        return true;
    }

    // Try to create recursively
    if (!@mkdir($dir, 0755, true)) {
        // collect diagnostics
        $parent = dirname($dir);
        $parentPerm = is_dir($parent) ? substr(sprintf('%o', fileperms($parent)), -4) : 'n/a';
        $parentWritable = is_writable($parent) ? 'yes' : 'no';
        $owner = @fileowner($parent) ?: 'n/a';
        $phpUser = function_exists('posix_getpwuid') ? @posix_getpwuid(posix_geteuid())['name'] ?? get_current_user() : get_current_user();
        $err = "Failed to create directory: {$dir}. Parent: {$parent} (perms={$parentPerm}, writable={$parentWritable}, owner={$owner}). PHP user: {$phpUser}.";
        return false;
    }
    // Try chmod
    @chmod($dir, 0755);
    if (!is_writable($dir)) {
        $err = "Directory created but not writable after chmod: {$dir} (perms=" . substr(sprintf('%o', fileperms($dir)), -4) . ").";
        return false;
    }
    return true;
}
function store_uploaded_file_safe($input_name, $target_dir, $allowed_mimes, $max_bytes, &$error_out) {
    $error_out = null;
    if (!isset($_FILES[$input_name]) || $_FILES[$input_name]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$input_name];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $error_out = "Upload error (code {$f['error']}) for {$input_name}.";
        return null;
    }
    if ($f['size'] > $max_bytes) {
        $error_out = "File too large for {$input_name} (max " . ($max_bytes/1024/1024) . " MB).";
        return null;
    }

    // verify MIME using finfo
    if (!function_exists('finfo_open')) {
        $error_out = "Server missing fileinfo extension.";
        return null;
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $f['tmp_name']);
    finfo_close($finfo);

    if (!array_key_exists($mime, $allowed_mimes)) {
        $error_out = "Unsupported file type ($mime).";
        return null;
    }

    // ensure target dir exists & writable
    if (!ensure_dir_writable($target_dir, $err)) {
        $error_out = $err;
        return null;
    }

    $ext = $allowed_mimes[$mime];
    $basename = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($f['name'], PATHINFO_FILENAME));
    try { $rand = bin2hex(random_bytes(5)); } catch (Exception $e) { $rand = substr(md5(uniqid('',true)),0,10); }
    $filename = $basename . '_' . time() . '_' . $rand . '.' . $ext;
    $dest = $target_dir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($f['tmp_name'], $dest)) {
        $error_out = "Failed to move uploaded file to {$dest}.";
        return null;
    }
    @chmod($dest, 0644);

    return [
        'path' => $dest,
        'filename' => $filename,
        'orig_name' => $f['name'],
        'mime' => $mime,
        'size' => (int)$f['size']
    ];
}
