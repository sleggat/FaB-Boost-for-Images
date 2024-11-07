<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php';

$whitelistedDomains = include 'config/whitelist.php';

use League\Glide\ServerFactory;
use League\Glide\Responses\PsrResponseFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;

// Define source and cache directories
$cacheDir = '.';
$remoteDir = $cacheDir . '/cache_remote';
$localDir = 'cache_local';

// Create PSR-17 factory
$psr17Factory = new Psr17Factory();

// Create response prototype
$responsePrototype = new Response();

// Create the stream callback that handles both string paths and resources
$streamCallback = function ($source) use ($psr17Factory) {
    if (is_resource($source)) {
        return $psr17Factory->createStreamFromResource($source);
    } else {
        $resource = fopen($source, 'r');
        return $psr17Factory->createStreamFromResource($resource);
    }
};

// Create the Glide server
$server = ServerFactory::create([
    'source' => $cacheDir,
    'cache' => $localDir,
    'response' => new PsrResponseFactory(
        $responsePrototype,
        $streamCallback
    ),
]);

// Check if image exists already
function checkImageCache($server, $savedFilePath, $glideParams)
{
    try {
        $cachedPath = $server->makeImage($savedFilePath, $glideParams);
        if (file_exists($cachedPath)) {
            return true;
        }
    } catch (Exception $e) {
        return false;
    }
    return false;
}

// Function to download an image
function downloadImage($url, $remoteDir)
{
    $parsedUrl = parse_url($url);
    if (!$parsedUrl) {
        throw new RuntimeException('Invalid URL');
    }

    // Check if the domain is in the whitelist
    global $whitelistedDomains;
    $domain = $parsedUrl['host'];

    if (!in_array($domain, $whitelistedDomains)) {
        header('HTTP/1.1 403 Forbidden');
        echo 'Forbidden: This domain (' . $domain . ') is not allowed to serve images.';
        exit;
    }


    $domainName = basename(str_replace(['http://', 'https://', 'www.'], '', $parsedUrl['host']));
    $path = $parsedUrl['path'];
    $baseName = basename($path);
    $md5Hash = md5($path);

    // Extract the file extension
    $extension = strtolower(pathinfo($baseName, PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
        header('HTTP/1.1 415 Unsupported Media Type');
        echo 'Unsupported image format.';
        exit;
    }

    // Save the file with the correct extension
    $outputFileName = './' . $remoteDir . '/' . $domainName . '/' . pathinfo($baseName, PATHINFO_FILENAME) . '-' . $md5Hash . '.' . $extension;

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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if (
        !$success || $httpCode !== 200
    ) {
        if (file_exists($outputFileName)) {
            unlink($outputFileName);
        }
        return null;
    }

    return $outputFileName;
}

function generateCacheKey($imageUrl, $glideParams)
{
    // Create a string that includes both the image URL and parameters
    $cacheString = $imageUrl . http_build_query($glideParams);

    // Generate a hash (checksum) from the string
    return md5($cacheString);  // Or use sha1 or any other hashing method
}

// Get and Sanitize URL parameters
$imageUrl = isset($_GET['i']) ? filter_var($_GET['i'], FILTER_SANITIZE_URL) : null;
if ($imageUrl && strpos($imageUrl, 'http') !== 0) {
    $imageUrl = 'https://' . $imageUrl;
}

// Sanitize the glide parameters
$glideParams = array_filter([
    'w' => isset($_GET['w']) ? filter_var($_GET['w'], FILTER_SANITIZE_NUMBER_INT) : null,
    'h' => isset($_GET['h']) ? filter_var($_GET['h'], FILTER_SANITIZE_NUMBER_INT) : null,
    'q' => isset($_GET['q']) ? filter_var($_GET['q'], FILTER_SANITIZE_NUMBER_INT) : null,
    'blur' => isset($_GET['blur']) ? filter_var($_GET['blur'], FILTER_SANITIZE_NUMBER_INT) : null,
    'sharp' => isset($_GET['sharp']) ? filter_var($_GET['sharp'], FILTER_SANITIZE_NUMBER_INT) : null,
    'fm' => isset($_GET['fm']) ? filter_var($_GET['fm'], FILTER_SANITIZE_FULL_SPECIAL_CHARS) : null, // Changed here
    'crop' => isset($_GET['crop']) ? filter_var($_GET['crop'], FILTER_SANITIZE_STRING) : null,
    'bri' => isset($_GET['bri']) ? filter_var($_GET['bri'], FILTER_SANITIZE_NUMBER_INT) : null,
    'con' => isset($_GET['con']) ? filter_var($_GET['con'], FILTER_SANITIZE_NUMBER_INT) : null,
    'gam' => isset($_GET['gam']) ? filter_var($_GET['gam'], FILTER_SANITIZE_NUMBER_INT) : null,
    'flip' => isset($_GET['flip']) ? filter_var($_GET['flip'], FILTER_SANITIZE_STRING) : null,
    'or' => isset($_GET['or']) ? filter_var($_GET['or'], FILTER_SANITIZE_STRING) : null,
]);

if ((isset($glideParams['w']) && $glideParams['w'] > 5000) || (isset($glideParams['h']) && $glideParams['h'] > 5000)) {
    header('HTTP/1.1 413 Payload Too Large');
    echo 'Image dimensions are too large.';
    exit;
}

// Process the image if a URL is provided
if ($imageUrl) {
    try {
        $parsedUrl = parse_url($imageUrl);
        $domainName = basename(str_replace(['http://', 'https://', 'www.'], '', $parsedUrl['host']));
        $path = $parsedUrl['path'];
        $baseName = basename($path);
        $md5Hash = md5($path);
        $extension = strtolower(pathinfo($baseName, PATHINFO_EXTENSION));

        // Construct the expected source path
        $expectedSourcePath = './' . $remoteDir . '/' . $domainName . '/' .
            pathinfo($baseName, PATHINFO_FILENAME) . '-' .
            $md5Hash . '.' . $extension;

        // Check if we need to download
        if (!file_exists($expectedSourcePath)) {
            $savedFilePath = downloadImage($imageUrl, $remoteDir);
        } else {
            $savedFilePath = $expectedSourcePath;
        }

        if ($savedFilePath && file_exists($savedFilePath)) {

            $maxFileSize = 10 * 1024 * 1024; // 10MB
            if (filesize($savedFilePath) > $maxFileSize) {
                unlink($savedFilePath);  // Remove large file
                header('HTTP/1.1 413 Payload Too Large');
                echo 'File size exceeds the limit.';
                exit;
            }

            $imageInfo = getimagesize($savedFilePath);
            if (!$imageInfo || !in_array($imageInfo['mime'], ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'])) {
                unlink($savedFilePath);  // Remove invalid image
                header('HTTP/1.1 400 Bad Request');
                echo 'Invalid image file.';
                exit;
            }

            // Set canonical URL
            $cacheKey = generateCacheKey($imageUrl, $glideParams);

            // Construct the canonical URL with the cache version
            $canonicalUrl = urlencode($imageUrl) . '&v=' . $cacheKey;
            if (!empty($glideParams)) {
                $canonicalUrl .= '&' . http_build_query($glideParams);
            }

            // Generate the image response with Glide

            $response = $server->getImageResponse($savedFilePath, $glideParams);

            $response = $response->withHeader('X-Cache-Status', file_exists($expectedSourcePath) ? 'HIT' : 'MISS')
                ->withHeader('Cache-Control', 'public, max-age=8640000, s-maxage=31536000, stale-while-revalidate=86400, stale-if-error=86400')
                ->withHeader('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000));


            // Output the response
            (new SapiEmitter())->emit($response);
        } else {
            header('HTTP/1.1 404 Not Found');
            echo 'File does not exist after download.';
        }
    } catch (Exception $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo 'Error processing image: ' . $e->getMessage();
    }
} else {
    header('HTTP/1.1 400 Bad Request');
    echo 'No image URL provided.';
}
