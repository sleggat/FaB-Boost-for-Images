<?php
class ImageDownloader
{
    private $remoteDir;
    private $whitelistedDomains;

    public function __construct($remoteDir)
    {
        $this->remoteDir = $remoteDir;
        $this->whitelistedDomains = include 'config/whitelist.php';
    }

    public function getImage($url)
    {
        $parsedUrl = parse_url($url);
        if (!$parsedUrl) {
            throw new RuntimeException('Invalid URL');
        }

        $this->validateDomain($parsedUrl['host']);
        $outputFileName = $this->generateFilePath($parsedUrl);

        if (file_exists($outputFileName)) {
            return $outputFileName;
        }

        return $this->downloadImage($url, $outputFileName);
    }

    private function validateDomain($domain)
    {
        if (!in_array($domain, $this->whitelistedDomains)) {
            Utils::sendError(403, 'Forbidden: This domain (' . $domain . ') is not allowed to serve images.');
        }
    }

    private function generateFilePath($parsedUrl)
    {
        $domainName = basename(str_replace(['http://', 'https://', 'www.'], '', $parsedUrl['host']));

        // Get the original path component
        $path = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';

        // Generate hash from the original URL path
        $md5Hash = md5($path);

        // Get the base filename, preserving UTF-8 characters
        $baseName = mb_basename($path);

        // Get extension
        $extension = strtolower(pathinfo($baseName, PATHINFO_EXTENSION));

        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'avif'])) {
            Utils::sendError(415, 'Unsupported image format.');
        }

        // Get filename without extension, preserving UTF-8 characters
        $filename = pathinfo($baseName, PATHINFO_FILENAME);

        // Sanitize filename while preserving UTF-8 characters
        $sanitizedFilename = preg_replace('/[\/\\\:\*\?"<>\|]/', '', $filename);

        // Construct the final path
        $outputFileName = './' . $this->remoteDir . '/' . $domainName . '/' .
            $sanitizedFilename . '-' . $md5Hash . '.' . $extension;

        if (!file_exists(dirname($outputFileName))) {
            mkdir(dirname($outputFileName), 0777, true);
        }

        return $outputFileName;
    }

    private function downloadImage($url, $outputFileName)
    {
        $ch = curl_init();

        // Set up CURL options
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        ]);

        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($data === false || $httpCode !== 200) {
            error_log("Failed to download image from URL: $url, HTTP Code: $httpCode");
            return null;
        }

        if (file_put_contents($outputFileName, $data) === false) {
            error_log("Failed to save image to: $outputFileName");
            return null;
        }

        return $outputFileName;
    }
}
function mb_basename($path)
{
    if (preg_match('@^.*[\\\\/]([^\\\\/]+)$@s', $path, $matches)) {
        return $matches[1];
    } else if (preg_match('@^([^\\\\/]+)$@s', $path, $matches)) {
        return $matches[1];
    }
    return '';
}
