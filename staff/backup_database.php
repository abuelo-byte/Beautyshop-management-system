<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cafeteria-management-system";

// Create backup directory if it doesn't exist
$backupDir = __DIR__ . '/backups';
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0777, true);
}

// Generate backup filename with timestamp
$backupFile = $backupDir . '/backup_' . date('Y-m-d_H-i-s') . '.sql';

// Command to create backup
$command = "mysqldump --user={$username} --password={$password} --host={$servername} {$dbname} > {$backupFile}";

// Execute backup command
exec($command, $output, $returnVar);

if ($returnVar === 0) {
    // Set headers for file download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($backupFile) . '"');
    header('Content-Length: ' . filesize($backupFile));
    
    // Read and output the backup file
    readfile($backupFile);
    
    // Delete the backup file after download
    unlink($backupFile);
} else {
    // If backup failed, redirect back to settings page with error
    header('Location: Settings.php?error=backup_failed');
    exit;
}
?> 