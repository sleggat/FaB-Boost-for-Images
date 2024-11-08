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
        $imageUrl = isset($params['i']) ? filter_var($params['i'], FILTER_SANITIZE_URL) : null;
        if ($imageUrl && strpos($imageUrl, 'http') !== 0) {
            $imageUrl = 'https://' . $imageUrl;
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
            'fm' => isset($params['fm']) ? filter_var($params['fm'], FILTER_SANITIZE_FULL_SPECIAL_CHARS) : null,
            'crop' => isset($params['crop']) ? filter_var($params['crop'], FILTER_SANITIZE_STRING) : null,
            'bri' => isset($params['bri']) ? filter_var($params['bri'], FILTER_SANITIZE_NUMBER_INT) : null,
            'con' => isset($params['con']) ? filter_var($params['con'], FILTER_SANITIZE_NUMBER_INT) : null,
            'gam' => isset($params['gam']) ? filter_var($params['gam'], FILTER_SANITIZE_NUMBER_INT) : null,
            'flip' => isset($params['flip']) ? filter_var($params['flip'], FILTER_SANITIZE_STRING) : null,
            'or' => isset($params['or']) ? filter_var($params['or'], FILTER_SANITIZE_STRING) : null,
        ]);
    }

    private function processImage($imageUrl, $glideParams)
    {
        try {
            $downloader = new ImageDownloader($this->remoteDir);
            $savedFilePath = $downloader->getImage($imageUrl);

            if (!$savedFilePath || !file_exists($savedFilePath)) {
                Utils::sendError(404, 'File does not exist after download.');
                return;
            }

            $this->validateImage($savedFilePath);
            $this->outputProcessedImage($savedFilePath, $glideParams, $imageUrl);
        } catch (Exception $e) {
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
        if (!$imageInfo || !in_array($imageInfo['mime'], ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'])) {
            unlink($savedFilePath);
            Utils::sendError(400, 'Invalid image file.');
        }
    }

    private function outputProcessedImage($savedFilePath, $glideParams, $imageUrl)
    {
        $cacheKey = Utils::generateCacheKey($imageUrl, $glideParams);
        $response = $this->server->getImageResponse($savedFilePath, $glideParams);

        // Add canonical URL and cache version headers
        $response = $response
            ->withHeader('X-Cache-Status', 'HIT')
            ->withHeader('X-Cache-Key', $cacheKey)  // Optional: helps with debugging
            ->withHeader('Cache-Control', 'public, max-age=8640000, s-maxage=31536000, stale-while-revalidate=86400, stale-if-error=86400')
            ->withHeader('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000));

        // Might also want to add a Link header for the canonical URL
        $canonicalUrl = $this->buildCanonicalUrl($imageUrl, $glideParams, $cacheKey);
        $response = $response->withHeader('Link', "<$canonicalUrl>; rel=\"canonical\"");

        (new \Laminas\HttpHandlerRunner\Emitter\SapiEmitter())->emit($response);
        Utils::logImageAccess($imageUrl, $glideParams);
    }

    private function buildCanonicalUrl($imageUrl, $glideParams, $cacheKey)
    {
        $canonicalUrl = urlencode($imageUrl) . '&v=' . $cacheKey;
        if (!empty($glideParams)) {
            $canonicalUrl .= '&' . http_build_query($glideParams);
        }
        return $canonicalUrl;
    }
}