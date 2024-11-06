<?php
require 'vendor/autoload.php';

$whitelistedDomains = [
    'frontandback.co.nz'
];

use League\Glide\ServerFactory;
use League\Glide\Responses\PsrResponseFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;

// Define source and cache directories
$sourceDir = 'remote';
$cacheDir = './cache';

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
    'source' => '.',
    'cache' => $cacheDir,
    'response' => new PsrResponseFactory(
        $responsePrototype,
        $streamCallback
    ),
]);

// Function to download an image
function downloadImage($url, $sourceDir)
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
        echo 'Forbidden: This domain is not allowed to serve images.';
        exit;
    }

    $domainName = basename(str_replace(['http://', 'https://', 'www.'], '', $parsedUrl['host']));
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if (!$success || $httpCode !== 200) {
        if (file_exists($outputFileName)) {
            unlink($outputFileName);
        }
        return null;
    }

    return $outputFileName;
}

// Get and Sanitize URL parameters
$imageUrl = isset($_GET['i']) ? filter_var($_GET['i'], FILTER_SANITIZE_URL) : null;

// Sanitize the glide parameters
$glideParams = array_filter([
    'w' => isset($_GET['w']) ? filter_var($_GET['w'], FILTER_SANITIZE_NUMBER_INT) : null,
    'h' => isset($_GET['h']) ? filter_var($_GET['h'], FILTER_SANITIZE_NUMBER_INT) : null,
    'q' => isset($_GET['q']) ? filter_var($_GET['q'], FILTER_SANITIZE_NUMBER_INT) : null,
    'blur' => isset($_GET['blur']) ? filter_var($_GET['blur'], FILTER_SANITIZE_NUMBER_INT) : null,
    'sharp' => isset($_GET['sharp']) ? filter_var($_GET['sharp'], FILTER_SANITIZE_NUMBER_INT) : null,
    'fm' => isset($_GET['fm']) ? filter_var($_GET['fm'], FILTER_SANITIZE_STRING) : null,
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
        $savedFilePath = downloadImage($imageUrl, $sourceDir);

        if ($savedFilePath && file_exists($savedFilePath)) {

            $imageInfo = getimagesize($savedFilePath);
            if (!$imageInfo) {
                unlink($savedFilePath);  // Remove invalid file
                header('HTTP/1.1 400 Bad Request');
                echo 'Invalid image file.';
                exit;
            }

            // Set canonical URL
            $canonicalUrl = urlencode($imageUrl);
            if (!empty($glideParams)) {
                $canonicalUrl .= '&' . http_build_query($glideParams);
            }

            // Generate the image response with Glide
            $response = $server->getImageResponse($savedFilePath, $glideParams);

            // Add canonical header
            $response = $response->withHeader('Link', sprintf('<%s>; rel="canonical"', $canonicalUrl))
                ->withHeader('Content-Type', 'image/jpeg')
                ->withHeader('Cache-Control', 'public, max-age=31536000')
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
