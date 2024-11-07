<?php

// Set this to be called via cron

function deleteOldFilesAndEmptyDirs($directory, $fileAgeLimitInSeconds = 2592000)
{
    // Create a Recursive Directory Iterator to traverse the directory structure
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    $currentTime = time();

    foreach ($iterator as $fileInfo) {
        $filePath = $fileInfo->getRealPath();

        if ($fileInfo->isFile()) {
            // Check if the file is older than the specified age limit
            if ($currentTime - $fileInfo->getCTime() > $fileAgeLimitInSeconds) {
                unlink($filePath); // Delete old file
            }
        } elseif ($fileInfo->isDir()) {
            // Remove empty directories
            @rmdir($filePath);
        }
    }
}

// Run cleanup on both directories
deleteOldFilesAndEmptyDirs(__DIR__ . '/remote');
deleteOldFilesAndEmptyDirs(__DIR__ . '/cache/remote');

echo "Cleanup complete.\n";
