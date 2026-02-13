<?php

namespace App\Helpers;

class FileUploader
{
    private string $uploadDir;
    private array $allowedTypes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'audio/ogg', 'audio/mpeg', 'audio/mp4', 'audio/wav', 'audio/x-wav', 'audio/webm',
        'video/webm',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain', 'text/csv',
        'application/zip', 'application/x-rar-compressed',
    ];
    private array $allowedExtensions = [
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'ogg', 'mp3', 'm4a', 'wav', 'webm',
        'pdf', 'doc', 'docx', 'xls', 'xlsx',
        'txt', 'csv', 'zip', 'rar'
    ];
    private int $maxFileSize = 10485760; // 10MB

    public function __construct(string $uploadDir = null)
    {
        $this->uploadDir = $uploadDir ?? (defined('BASE_PATH') ? BASE_PATH . '/public/uploads' : __DIR__ . '/../../public/uploads');

        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * Upload a single file
     */
    public function upload(array $file, string $subfolder = 'attachments'): array
    {
        // Validate file
        $this->validate($file);

        // Generate unique filename (includes date subdirectory)
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = $this->generateFilename($extension);

        // Build full target path
        $relativePath = $subfolder . '/' . $filename;
        $targetPath = $this->uploadDir . '/' . $relativePath;

        // Create full directory structure (including date subdirectories)
        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) {
                throw new \RuntimeException('Failed to create upload directory');
            }
        }

        // Move file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new \RuntimeException('Failed to move uploaded file');
        }

        return [
            'filename' => $filename,
            'original_name' => $file['name'],
            'mime_type' => $file['type'],
            'size' => $file['size'],
            'path' => $relativePath,
            'url' => '/support/public/uploads/' . $relativePath,
        ];
    }

    /**
     * Upload multiple files
     */
    public function uploadMultiple(array $files, string $subfolder = 'attachments'): array
    {
        $uploaded = [];

        // Normalize files array structure
        if (isset($files['name']) && is_array($files['name'])) {
            $count = count($files['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK && !empty($files['name'][$i])) {
                    $file = [
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i],
                    ];
                    try {
                        $uploaded[] = $this->upload($file, $subfolder);
                    } catch (\Exception $e) {
                        // Skip failed uploads, continue with others
                        continue;
                    }
                }
            }
        }

        return $uploaded;
    }

    /**
     * Validate uploaded file
     */
    private function validate(array $file): void
    {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException($this->getUploadErrorMessage($file['error']));
        }

        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            throw new \RuntimeException('File size exceeds maximum allowed (' . $this->formatBytes($this->maxFileSize) . ')');
        }

        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $this->allowedTypes)) {
            throw new \RuntimeException('File type not allowed: ' . $mimeType);
        }

        // Check extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            throw new \RuntimeException('File extension not allowed: ' . $extension);
        }
    }

    /**
     * Generate unique filename
     */
    private function generateFilename(string $extension): string
    {
        return date('Y/m/') . uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    }

    /**
     * Get upload error message
     */
    private function getUploadErrorMessage(int $error): string
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
        ];

        return $messages[$error] ?? 'Unknown upload error';
    }

    /**
     * Format bytes to human readable
     */
    public function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Delete a file
     */
    public function delete(string $path): bool
    {
        $fullPath = $this->uploadDir . '/' . $path;
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        return false;
    }

    /**
     * Check if file is an image
     */
    public static function isImage(string $mimeType): bool
    {
        return strpos($mimeType, 'image/') === 0;
    }

    /**
     * Get file icon based on mime type
     */
    public static function getFileIcon(string $mimeType): string
    {
        if (self::isImage($mimeType)) return 'fa-image';
        if (strpos($mimeType, 'pdf') !== false) return 'fa-file-pdf';
        if (strpos($mimeType, 'word') !== false) return 'fa-file-word';
        if (strpos($mimeType, 'excel') !== false || strpos($mimeType, 'spreadsheet') !== false) return 'fa-file-excel';
        if (strpos($mimeType, 'zip') !== false || strpos($mimeType, 'rar') !== false) return 'fa-file-archive';
        if (strpos($mimeType, 'text') !== false) return 'fa-file-alt';
        return 'fa-file';
    }
}
