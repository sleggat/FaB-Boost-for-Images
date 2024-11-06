<?php
require 'vendor/autoload.php';

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

// Get URL parameters
$imageUrl = $_GET['i'] ?? null;
$glideParams = array_filter([
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
]);

// Process the image if a URL is provided
if ($imageUrl) {
    try {
        $savedFilePath = downloadImage($imageUrl, $sourceDir);

        if ($savedFilePath && file_exists($savedFilePath)) {
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
