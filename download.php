<?php
require_once 'config.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(404); die('File not found'); }

$stmt = $pdo->prepare("SELECT * FROM task_attachments WHERE id=?");
$stmt->execute([$id]);
$file = $stmt->fetch();

if (!$file) { http_response_code(404); die('File not found'); }

$filePath = UPLOAD_DIR . 'attachments/' . $file['file_name'];

if (!file_exists($filePath)) { http_response_code(404); die('File not found on server'); }

// Log download
logActivity(getUserId(), 'download_file', "Downloaded: {$file['original_name']}", 'file', $id);

// Send file
$mime = $file['file_type'] ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($file['original_name']) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($filePath);
exit;
