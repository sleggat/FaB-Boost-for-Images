<?php
class Utils
{
    public static function sendError($code, $message)
    {
        header('HTTP/1.1 ' . $code . ' ' . self::getStatusText($code));
        echo $message;
        exit;
    }

    public static function generateCacheKey($imageUrl, $glideParams)
    {
        $cacheString = $imageUrl . http_build_query($glideParams);
        return md5($cacheString);
    }

    public static function logImageAccess($imageUrl, $glideParams)
    {
        $logFile = __DIR__ . '/../logs/access.log';
        $timestamp = date("Y-m-d H:i:s");
        $message = $imageUrl . " with params " . json_encode($glideParams);
        $logEntry = "[$timestamp] $message\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

    private static function getStatusText($code)
    {
        $statusTexts = [
            200 => 'OK',
            400 => 'Bad Request',
            403 => 'Forbidden',
            404 => 'Not Found',
            413 => 'Payload Too Large',
            415 => 'Unsupported Media Type',
            500 => 'Internal Server Error',
        ];
        return $statusTexts[$code] ?? 'Unknown Status';
    }
}
