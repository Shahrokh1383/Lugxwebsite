<?php
// This MUST be the absolute first line of the file, with no spaces or newlines before it.
// Ensure error reporting is on for debugging.
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure no output buffering is implicitly active from previous layers, or start a new one.
// We explicitly manage it here for this test.
// Clean any existing buffers to prevent premature output.
while (ob_get_level() > 0) {
    ob_end_clean();
}
// Start a new output buffer for this script's content.
ob_start();

// Check if headers have already been sent. If so, it means there was premature output
// even before this script started its own buffering.
if (headers_sent($file, $line)) {
    error_log("CRITICAL ERROR: Headers already sent from {$file}:{$line} in test_json.php. Premature output detected.");
    http_response_code(500);
    echo "Headers already sent before test_json.php could set them.";
    // Flush and exit even if headers were sent, to prevent further issues.
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    exit;
}

// Set the Content-Type header to application/json.
header('Content-Type: application/json; charset=utf-8');
// Set the HTTP status code.
http_response_code(200);

// Prepare the JSON data.
$testData = ['status' => 'success', 'message' => 'This is a direct JSON response from test_json.php!', 'data' => ['value' => 123]];

// Encode and echo the JSON data to the output buffer.
echo json_encode($testData, JSON_THROW_ON_ERROR);

// Flush the output buffer and send its content to the client.
if (ob_get_level() > 0) {
    ob_end_flush();
}

// Terminate the script execution.
exit;
?>
