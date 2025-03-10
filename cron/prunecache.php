<?php

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

function deleteOldFilesAndEmptyDirs($directory, $fileAgeLimitInSeconds = 2592000)
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    $currentTime = time();
    $deletedFiles = 0;
    $deletedDirs = 0;
    $totalSpaceSaved = 0;

    foreach ($iterator as $fileInfo) {
        $filePath = $fileInfo->getRealPath();

        if ($fileInfo->isFile()) {
            if ($currentTime - $fileInfo->getCTime() > $fileAgeLimitInSeconds) {
                $fileSize = $fileInfo->getSize();
                if (unlink($filePath)) {
                    echo "Deleted file: $filePath (Size: " . formatSize($fileSize) . ")\n";
                    $deletedFiles++;
                    $totalSpaceSaved += $fileSize;
                } else {
                    echo "Failed to delete file: $filePath\n";
                }
            }
        } elseif ($fileInfo->isDir()) {
            if (@rmdir($filePath)) {
                echo "Deleted empty directory: $filePath\n";
                $deletedDirs++;
            }
        }
    }

    echo "\nCleanup Summary:\n";
    echo "Total files deleted: $deletedFiles\n";
    echo "Total empty directories deleted: $deletedDirs\n";
    echo "Total disk space saved: " . formatSize($totalSpaceSaved) . "\n";
}

// Format bytes into a human-readable format (KB, MB, etc.)
function formatSize($bytes)
{
    if ($bytes < 1024) return $bytes . " B";
    $units = ['KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $bytes >= 1024 && $i < count($units); $i++) $bytes /= 1024;
    return round($bytes, 2) . ' ' . $units[$i];
}

// Run cleanup on both directories
echo "Starting cleanup for '/../cache_remote' directory...\n";
deleteOldFilesAndEmptyDirs(__DIR__ . '/../cache_remote');

echo "\nStarting cleanup for '/../cache_local/cache_remote' directory...\n";
deleteOldFilesAndEmptyDirs(__DIR__ . '/../cache_local/cache_remote');

echo "Cleanup complete.\n";
