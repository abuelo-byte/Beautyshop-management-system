<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cafeteria-management-system";

// Check if a file was uploaded
if (!isset($_FILES['restore_file']) || $_FILES['restore_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

// Validate file type
$fileType = strtolower(pathinfo($_FILES['restore_file']['name'], PATHINFO_EXTENSION));
if ($fileType !== 'sql') {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only .sql files are allowed']);
    exit;
}

// Create temporary directory if it doesn't exist
$tempDir = __DIR__ . '/temp';
if (!file_exists($tempDir)) {
    mkdir($tempDir, 0777, true);
}

// Move uploaded file to temporary directory
$tempFile = $tempDir . '/restore_' . time() . '.sql';
if (!move_uploaded_file($_FILES['restore_file']['tmp_name'], $tempFile)) {
    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
    exit;
}

// Command to restore database
$command = "mysql --user={$username} --password={$password} --host={$servername} {$dbname} < {$tempFile}";

// Execute restore command
exec($command, $output, $returnVar);

// Delete temporary file
unlink($tempFile);

if ($returnVar === 0) {
    echo json_encode(['success' => true, 'message' => 'Database restored successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to restore database']);
}
?> 