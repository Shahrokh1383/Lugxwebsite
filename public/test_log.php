<?php
// C:\xampp\htdocs\Lugxwebsite\public\test_log.php
error_log("This is a test log message from test_log.php. Current time: " . date('Y-m-d H:i:s'));
echo "Check your PHP error log file at C:\\xampp\\php\\logs\\php_error_log.log";
// Intentionally cause a PHP error to see if it's displayed on screen and logged
$undefined_variable = $non_existent_variable; 
?>
