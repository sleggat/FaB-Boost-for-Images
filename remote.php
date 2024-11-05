<?php

require 'vendor/autoload.php';

// Define source and cache directories
$sourceDir = 'remote';
$cacheDir = './cache';

// Create the Glide server
$server = League\Glide\ServerFactory::create([
    'source' => '.',
    'cache' => $cacheDir,
]);

function downloadImage($url, $sourceDir)
{
    $parsedUrl = parse_url($url);
    $domainName = str_replace(['http://', 'https://', 'www.'], '', $parsedUrl['host']);
    $path = $parsedUrl['path'];
    $baseName = basename($path);
    $md5Hash = md5($path);
    $outputFileName = './' . $sourceDir . '/' . $domainName . '/' . pathinfo($baseName, PATHINFO_FILENAME) . '-' . $md5Hash . '.jpg';

    if (!file_exists(dirname($outputFileName))) {
        mkdir(dirname($outputFileName), 0777, true);
    }

    $ch = curl_init($url);
    $fp = fopen($outputFileName, 'wb');
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
    ]);

    $success = curl_exec($ch);
    curl_close($ch);
    fclose($fp);

    return $success ? $outputFileName : null;
}

// Get URL parameters
$imageUrl = $_GET['i'] ?? null;
$glideParams = [
    'w' => $_GET['w'] ?? null,
    'h' => $_GET['h'] ?? null,
    'q' => $_GET['q'] ?? null,
    'blur' => $_GET['blur'] ?? null,
    'sharp' => $_GET['sharp'] ?? null,
    'fm' => $_GET['fm'] ?? null,
    'crop' => $_GET['crop'] ?? null,
    'bri' => $_GET['bri'] ?? null,
    'con' => $_GET['con'] ?? null,
    'gam' => $_GET['gam'] ?? null,
    'flip' => $_GET['flip'] ?? null,
    'or' => $_GET['or'] ?? null,
];

// Filter out null values
$glideParams = array_filter($glideParams, fn($value) => $value !== null);

// Process the image if a URL is provided
if ($imageUrl) {
    $savedFilePath = downloadImage($imageUrl, $sourceDir);

    if ($savedFilePath && file_exists($savedFilePath)) {
        $server->outputImage($savedFilePath, $glideParams);
        echo "Image should have been displayed";
    } else {
        echo 'File does not exist after download.';
    }
} else {
    echo 'No image URL provided.';
}