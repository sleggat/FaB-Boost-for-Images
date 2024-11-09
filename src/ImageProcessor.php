<?php
class ImageProcessor
{
    private $server;
    private $remoteDir;
    private $localDir;

    public function __construct()
    {
        $this->remoteDir = './cache_remote';
        $this->localDir = 'cache_local';
        $this->initializeGlideServer();
    }

    private function initializeGlideServer()
    {
        $psr17Factory = new \Nyholm\Psr7\Factory\Psr17Factory();
        $responsePrototype = new \Nyholm\Psr7\Response();

        $streamCallback = function ($source) use ($psr17Factory) {
            if (is_resource($source)) {
                return $psr17Factory->createStreamFromResource($source);
            }
            $resource = fopen($source, 'r');
            return $psr17Factory->createStreamFromResource($resource);
        };

        $this->server = \League\Glide\ServerFactory::create([
            'source' => '.',
            'cache' => $this->localDir,
            'response' => new \League\Glide\Responses\PsrResponseFactory(
                $responsePrototype,
                $streamCallback
            ),
        ]);
    }

    public function handleRequest($params)
    {

        error_log("Request parameters: " . print_r($params, true));

        $imageUrl = isset($params['i']) ? $params['i'] : null;

        if ($imageUrl) {
            if (!preg_match('~^(?:f|ht)tps?://~i', $imageUrl)) {
                $imageUrl = 'https://' . $imageUrl;
            }

            error_log("Processing URL: " . $imageUrl);
        }

        if (!$imageUrl) {
            Utils::sendError(400, 'No image URL provided.');
            return;
        }

        $glideParams = $this->sanitizeParameters($params);
        $this->processImage($imageUrl, $glideParams);
    }

    private function sanitizeParameters($params)
    {
        return array_filter([
            'w' => isset($params['w']) ? filter_var($params['w'], FILTER_SANITIZE_NUMBER_INT) : null,
            'h' => isset($params['h']) ? filter_var($params['h'], FILTER_SANITIZE_NUMBER_INT) : null,
            'q' => isset($params['q']) ? filter_var($params['q'], FILTER_SANITIZE_NUMBER_INT) : null,
            'blur' => isset($params['blur']) ? filter_var($params['blur'], FILTER_SANITIZE_NUMBER_INT) : null,
            'sharp' => isset($params['sharp']) ? filter_var($params['sharp'], FILTER_SANITIZE_NUMBER_INT) : null,
            'fm' => isset($params['fm']) ? filter_var($params['fm'], FILTER_SANITIZE_SPECIAL_CHARS) : null,
            'crop' => isset($params['crop']) ? filter_var($params['crop'], FILTER_SANITIZE_STRING) : null,
            'bri' => isset($params['bri']) ? filter_var($params['bri'], FILTER_SANITIZE_NUMBER_INT) : null,
            'con' => isset($params['con']) ? filter_var($params['con'], FILTER_SANITIZE_NUMBER_INT) : null,
            'gam' => isset($params['gam']) ? filter_var($params['gam'], FILTER_SANITIZE_NUMBER_INT) : null,
            'flip' => isset($params['flip']) ? filter_var($params['flip'], FILTER_SANITIZE_STRING) : null,
            'or' => isset($params['or']) ? filter_var($params['or'], FILTER_SANITIZE_STRING) : null,
            'bg' => isset($params['bg']) ? filter_var($params['bg'], FILTER_SANITIZE_SPECIAL_CHARS) : null,
        ]);
    }

    private function processImage($imageUrl, $glideParams)
    {
        try {
            $downloader = new ImageDownloader($this->remoteDir);
            $savedFilePath = $downloader->getImage($imageUrl);

            if (!$savedFilePath || !file_exists($savedFilePath)) {
                Utils::sendError(404, 'File (' . $imageUrl . ') does not exist after download.');
                return;
            }

            $this->validateImage($savedFilePath);
            $this->outputProcessedImage($savedFilePath, $glideParams, $imageUrl);
        } catch (Exception $e) {
            error_log("Error processing image: " . $e->getMessage());
            Utils::sendError(500, 'Error processing image: ' . $e->getMessage());
        }
    }

    private function validateImage($savedFilePath)
    {
        $maxFileSize = 10 * 1024 * 1024;
        if (filesize($savedFilePath) > $maxFileSize) {
            unlink($savedFilePath);
            Utils::sendError(413, 'File size exceeds the limit.');
        }

        $imageInfo = getimagesize($savedFilePath);
        if (!$imageInfo || !in_array($imageInfo['mime'], ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp', 'image/avif'])) {
            unlink($savedFilePath);
            Utils::sendError(400, 'Invalid image file.');
        }
    }

    private function outputProcessedImage($savedFilePath, $glideParams, $imageUrl)
    {
        try {
            $cacheKey = Utils::generateCacheKey($imageUrl, $glideParams);
            $response = $this->server->getImageResponse($savedFilePath, $glideParams);

            $response = $response
                ->withHeader('FaB-Cache-Status', 'HIT')
                ->withHeader('FaB-Cache-Key', $cacheKey)
                ->withHeader('Cache-Control', 'public, max-age=8640000, s-maxage=31536000, stale-while-revalidate=86400, stale-if-error=86400')
                ->withHeader('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000));

            $canonicalUrl = $this->buildCanonicalUrl($imageUrl, $glideParams, $cacheKey);
            $response = $response->withHeader('Link', "<$canonicalUrl>; rel=\"canonical\"");

            (new \Laminas\HttpHandlerRunner\Emitter\SapiEmitter())->emit($response);
            Utils::logImageAccess($imageUrl, $glideParams);
        } catch (Exception $e) {
            error_log("Error in outputProcessedImage: " . $e->getMessage());
            throw $e; // Re-throw to be caught by processImage
        }
    }

    private function buildCanonicalUrl($imageUrl, $glideParams, $cacheKey)
    {
        // the very complicated way of url encoding just the URI section of a URL
        $parts = parse_url($imageUrl);
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $parts['path'])));
        $encodedUrl = "{$parts['scheme']}://{$parts['host']}{$encodedPath}" .
            (isset($parts['query']) ? "?{$parts['query']}" : '') .
            (isset($parts['fragment']) ? "#{$parts['fragment']}" : '');
        return $encodedUrl;
        // Still not convinced we need to add: '&v='.$cacheKey seems to cause more issues that it supposedly solves. BTW if no other queries it should be ?v= 
    }
}
