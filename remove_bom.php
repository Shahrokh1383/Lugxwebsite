<?php
// Lugxwebsite/clean_project_files.php

// Define color codes for terminal output (optional, remove if causing issues)
define('COLOR_RESET', "\033[0m");
define('COLOR_GREEN', "\033[0;32m");
define('COLOR_YELLOW', "\033[1;33m");
define('COLOR_RED', "\033[0;31m");
define('COLOR_CYAN', "\033[0;36m");

echo COLOR_CYAN . "Starting Project File Cleanup: BOM and Trailing Whitespace...\n" . COLOR_RESET;
echo "------------------------------------------------------------\n\n";

$basePath = __DIR__; // This script's directory is the project root
$processedFileCount = 0;
$fixedFileCount = 0;
$detailedLog = [];

/**
 * Checks if a file has a BOM and removes it if found.
 *
 * @param string $filePath The full path to the file.
 * @return bool True if a BOM was found and removed, false otherwise.
 */
function removeBom(string $filePath): bool
{
    $bom = pack('CCC', 0xef, 0xbb, 0xbf);
    
    // Attempt to read the file content
    $content = file_get_contents($filePath);
    if ($content === false) {
        error_log("ERROR: (clean_project_files.php) Could not read file for BOM check: " . $filePath);
        return false; // Failed to read
    }

    // Check for BOM
    if (str_starts_with($content, $bom)) {
        // Remove BOM and attempt to write back
        if (file_put_contents($filePath, substr($content, 3)) === false) {
            error_log("ERROR: (clean_project_files.php) Could not write (remove BOM) to file: " . $filePath);
            return false; // Failed to write
        }
        return true; // BOM found and removed
    }
    return false; // No BOM found
}

/**
 * Removes trailing whitespace/newlines specifically after the closing ?> tag in PHP files.
 * For other file types, it ensures a single newline at EOF.
 *
 * @param string $filePath The full path to the file.
 * @return bool True if trailing whitespace/newlines were fixed, false otherwise.
 */
function fixTrailingWhitespace(string $filePath): bool
{
    $fixed = false;
    
    // Attempt to read the file content
    $content = file_get_contents($filePath);
    if ($content === false) {
        error_log("ERROR: (clean_project_files.php) Could not read file for trailing whitespace check: " . $filePath);
        return false; // Failed to read
    }
    $originalContent = $content; // Keep original content to compare for changes
    // Logic specifically for PHP files
    if (pathinfo($filePath, PATHINFO_EXTENSION) === 'php') {
        $lastPhpTagPos = strrpos($content, '?>');
        if ($lastPhpTagPos !== false) {
            $contentAfterTag = substr($content, $lastPhpTagPos + 2);

            if (trim($contentAfterTag) === '') {
                $content = rtrim(substr($content, 0, $lastPhpTagPos + 2));

                if (!empty($content)) {
                    $content .= "\n";
                }
            }
        } else {
            $content = rtrim($content);
            if (!empty($content)) {
                $content .= "\n"; // Add a single newline if content is not empty
            }
        }
    } else {
        $content = rtrim($content); // Remove all existing trailing whitespace/newlines
        if (!empty($content)) {
            $content .= "\n"; // Add a single newline if content is not empty
        }
    }

    if ($content !== $originalContent) {
        if (file_put_contents($filePath, $content) === false) {
            error_log("ERROR: (clean_project_files.php) Could not write (fix trailing whitespace) to file: " . $filePath);
            return false; // Failed to write
        }
        $fixed = true;
    }
    return $fixed;
}
/**
 * Recursive function to scan directories and process files.
 *
 * @param string $dir The directory to scan.
 */
function scanAndCleanDir(string $dir): void
{
    global $processedFileCount, $fixedFileCount, $detailedLog;

    // Get all items in the directory
    $items = scandir($dir);
    if ($items === false) {
        error_log("ERROR: (clean_project_files.php) Could not scan directory: " . $dir);
        return; // Failed to scan
    }

    foreach ($items as $item) {
        // Skip current and parent directory entries
        if ($item === '.' || $item === '..') {
            continue;
        }

        $filePath = $dir . DIRECTORY_SEPARATOR . $item;

        if (is_dir($filePath)) {
            // Skip specified system/development directories
            if (in_array($item, ['.git', 'vendor', 'logs', 'node_modules', 'database', 'db', 'seed'])) {
                echo COLOR_YELLOW . "Skipping directory: " . $filePath . "\n" . COLOR_RESET;
                continue;
            }
            scanAndCleanDir($filePath); // Recurse into subdirectories
        } elseif (is_file($filePath)) {
            $extension = pathinfo($filePath, PATHINFO_EXTENSION);

            // Process only specified file types
            if (in_array($extension, ['php', 'html', 'css', 'js'])) {
                $processedFileCount++;
                $fileWasFixed = false;
                $fixesApplied = []; // To track types of fixes for this file

                // 1. Attempt to remove BOM
                if (removeBom($filePath)) {
                    $fixesApplied[] = "BOM removed";
                    $fileWasFixed = true;
                }

                // 2. Attempt to fix trailing whitespace/newlines
                if (fixTrailingWhitespace($filePath)) {
                    $fixesApplied[] = "Trailing whitespace/newlines fixed";
                    $fileWasFixed = true;
                }

                // Log if any fix was applied to this file
                if ($fileWasFixed) {
                    $fixedFileCount++;
                    $detailedLog[] = COLOR_GREEN . "  - " . implode(" and ", $fixesApplied) . " in: " . $filePath . COLOR_RESET;
                    echo COLOR_GREEN . "  - " . implode(" and ", $fixesApplied) . " in: " . $filePath . "\n" . COLOR_RESET;
                }
            }
        }
    }
}

// Start the scanning and cleaning process from the project root
scanAndCleanDir($basePath);

echo "\n" . COLOR_CYAN . "------------------------------------------------------------\n";
echo "Cleanup process completed.\n" . COLOR_RESET;
echo "Total files scanned: " . COLOR_CYAN . $processedFileCount . COLOR_RESET . "\n";
echo "Total files fixed: " . COLOR_GREEN . $fixedFileCount . COLOR_RESET . "\n\n";

if ($fixedFileCount === 0) {
    echo COLOR_GREEN . "No BOMs or problematic trailing whitespaces were found in the relevant files.\n" . COLOR_RESET;
} else {
    echo COLOR_YELLOW . "--- Detailed Report of Fixed Files ---\n" . COLOR_RESET;
    foreach ($detailedLog as $entry) {
        echo $entry . "\n";
    }
}

echo "\n" . COLOR_CYAN . "Remember to restart your Apache/Nginx server (or XAMPP/WAMP services) for changes to take full effect!\n" . COLOR_RESET;

?>
