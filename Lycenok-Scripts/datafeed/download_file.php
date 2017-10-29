<?php 
if (!session_id()) {
    session_start();
}
$path = $_SESSION['download_file_path'];
header('Content-Type: application/octet-stream');
header("Content-Transfer-Encoding: Binary"); 
header("Content-disposition: attachment; filename=\"" . basename($path) . "\""); 
readfile($path);
?>