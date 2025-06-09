<?php
// Check if the file exists
if (!$this->call('exists')) {

    // Show 404 Error
    $this->interrupt();
    $this->Router->render('404');
    exit;
}

// Drop any previous output
if (ob_get_length()) {
    ob_end_clean();
}

// Set Headers
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $this->call('name') . '"');
header('Content-Description: File Transfer');
header('Cache-Control: no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Length: ' . $this->call('size'));

// Output the file content
echo $this->call('content');
