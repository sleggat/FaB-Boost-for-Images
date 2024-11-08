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
        $path = $parsedUrl['path'];
        $baseName = basename($path);
        $md5Hash = md5($path);
        $extension = strtolower(pathinfo($baseName, PATHINFO_EXTENSION));

        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
            Utils::sendError(415, 'Unsupported image format.');
        }

        $outputFileName = './' . $this->remoteDir . '/' . $domainName . '/' .
            pathinfo($baseName, PATHINFO_FILENAME) . '-' . $md5Hash . '.' . $extension;

        if (!file_exists(dirname($outputFileName))) {
            mkdir(dirname($outputFileName), 0777, true);
        }

        return $outputFileName;
    }

    private function downloadImage($url, $outputFileName)
    {
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
}
