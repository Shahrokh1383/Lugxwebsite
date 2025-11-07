<?php

namespace App\Services;

use Exception;

class UploadService
{
    private array $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private int $maxFileSize = 2097152; // 2 MB
    private string $uploadDir = __DIR__ . '/../../public/uploads/';

    public function __construct()
    {
        // Ensure the upload directory exists
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
    }

    /**
     * Handles the upload of a single file.
     *
     * @param array $fileData The $_FILES array data for a single file.
     * @param string $subDir The subdirectory to save the file in (e.g., 'products', 'users').
     * @return string|false The path to the uploaded file relative to the public directory on success, or false on failure.
     * @throws Exception If an upload error occurs.
     */
    public function uploadFile(array $fileData, string $subDir): string|false
    {
        if ($fileData['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload error: " . $fileData['error']);
        }

        $extension = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));

        // Validate file type and size
        if (!in_array($extension, $this->allowedExtensions)) {
            throw new Exception("Invalid file type. Only " . implode(', ', $this->allowedExtensions) . " are allowed.");
        }
        if ($fileData['size'] > $this->maxFileSize) {
            throw new Exception("File size exceeds the maximum limit of " . ($this->maxFileSize / 1024 / 1024) . " MB.");
        }

        $fileName = uniqid() . '.' . $extension;
        $targetDir = $this->uploadDir . $subDir . '/';

        // Create subdirectory if it doesn't exist
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $targetPath = $targetDir . $fileName;

        if (move_uploaded_file($fileData['tmp_name'], $targetPath)) {
            // Return the path relative to the public directory
            return "uploads/{$subDir}/{$fileName}";
        } else {
            throw new Exception("Failed to move uploaded file.");
        }
    }

    /**
     * Handles the upload of multiple files (e.g., a product gallery).
     *
     * @param array $filesData The $_FILES array for a group of files.
     * @param string $subDir The subdirectory to save the files in.
     * @return array An array of paths to the uploaded files relative to the public directory.
     */
    public function uploadMultipleFiles(array $filesData, string $subDir): array
    {
        $uploadedPaths = [];
        $fileCount = count($filesData['name']);

        for ($i = 0; $i < $fileCount; $i++) {
            if ($filesData['error'][$i] === UPLOAD_ERR_OK) {
                try {
                    $fileData = [
                        'name' => $filesData['name'][$i],
                        'type' => $filesData['type'][$i],
                        'tmp_name' => $filesData['tmp_name'][$i],
                        'error' => $filesData['error'][$i],
                        'size' => $filesData['size'][$i],
                    ];
                    $uploadedPaths[] = $this->uploadFile($fileData, $subDir);
                } catch (Exception $e) {
                    error_log("Failed to upload file {$filesData['name'][$i]}: " . $e->getMessage());
                    // We can choose to continue or break here. Continuing allows other files to be uploaded.
                }
            } else {
                error_log("File upload error for {$filesData['name'][$i]}: " . $filesData['error'][$i]);
            }
        }
        return $uploadedPaths;
    }

    /**
     * Deletes a file from the file system.
     *
     * @param string $filePath The path to the file relative to the public directory.
     * @return bool True on success, false on failure.
     */
    public function deleteFile(string $filePath): bool
    {
        $fullPath = __DIR__ . '/../../public/' . $filePath;
        if (file_exists($fullPath) && is_file($fullPath)) {
            return unlink($fullPath);
        }
        return false;
    }
}