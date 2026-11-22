<?php
namespace App\Core;

/**
 * Client HTTP minimal SANS dépendance dure :
 *  1. ext-curl si disponible ;
 *  2. streams PHP (file_get_contents + contexte) si allow_url_fopen ;
 *  3. fsockopen en dernier recours (HTTP/HTTPS, redirections, chunked).
 * But : fonctionner sur un hébergement mutualisé minimal (sans ext-curl).
 */
class Http
{
    /** @var string|null Transport forcé pour les tests ('curl'|'stream'|'socket'). */
    public static ?string $forceTransport = null;

    /** Transport choisi : $forceTransport (tests) > env LISONS_HTTP_TRANSPORT > auto-détection. */
    private static function transport(): string
    {
        $forced = self::$forceTransport ?? (getenv('LISONS_HTTP_TRANSPORT') ?: '');
        if (in_array($forced, ['curl', 'stream', 'socket'], true)) {
            return $forced;
        }
        return function_exists('curl_init') ? 'curl' : (ini_get('allow_url_fopen') ? 'stream' : 'socket');
    }

    /** GET une URL → corps de la réponse, ou null si erreur réseau / HTTP >= 400. */
    public static function get(string $url, int $timeout = 20, string $userAgent = 'Lisons-plus/1.0'): ?string
    {
        $transport = self::transport();
        $out = match ($transport) {
            'curl' => self::getCurl($url, $timeout, $userAgent),
            'stream' => self::getStream($url, $timeout, $userAgent),
            default => self::getSocket($url, $timeout, $userAgent, 5),
        };
        return $out;
    }

    /** Télécharge une URL vers un fichier. */
    public static function download(string $url, string $dest, int $timeout = 30, string $userAgent = 'Lisons-plus/1.0'): bool
    {
        $transport = self::transport();
        if ($transport === 'curl') {
            $ch = curl_init($url);
            $fp = @fopen($dest, 'wb');
            if (!$fp) {
                curl_close($ch);
                return false;
            }
            curl_setopt_array($ch, [
                CURLOPT_FILE => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_USERAGENT => $userAgent,
            ]);
            curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fp);
            return $code >= 200 && $code < 300 && filesize($dest) > 0;
        }
        $body = self::get($url, $timeout, $userAgent);
        if ($body === null || $body === '') {
            return false;
        }
        return file_put_contents($dest, $body) !== false;
    }

    private static function getCurl(string $url, int $timeout, string $userAgent): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_ACCEPT_ENCODING => '',
        ]);
        $out = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($out === false || $code >= 400) {
            return null;
        }
        return (string) $out;
    }

    private static function getStream(string $url, int $timeout, string $userAgent): ?string
    {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeout,
                'follow_location' => 1,
                'max_redirects' => 5,
                'user_agent' => $userAgent,
                'ignore_errors' => true,
            ],
        ]);
        $out = @file_get_contents($url, false, $ctx);
        if ($out === false) {
            return null;
        }
        $code = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $h, $m)) {
                $code = (int) $m[1];
            }
        }
        if ($code >= 400) {
            return null;
        }
        return (string) $out;
    }

    /** fsockopen : GET brut avec redirections et décodage chunked. */
    private static function getSocket(string $url, int $timeout, string $userAgent, int $maxRedirects): ?string
    {
        $current = $url;
        for ($i = 0; $i <= $maxRedirects; $i++) {
            $parts = parse_url($current);
            if ($parts === false || empty($parts['host'])) {
                return null;
            }
            $scheme = $parts['scheme'] ?? 'http';
            $host = $parts['host'];
            $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
            $path = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');

            $errno = 0;
            $errstr = '';
            $errnoStr = '';
            $fp = @fsockopen($scheme === 'https' ? 'ssl://' . $host : $host, $port, $errno, $errstr, $timeout);
            if (!$fp) {
                return null;
            }
            stream_set_timeout($fp, $timeout);
            $req = "GET $path HTTP/1.1\r\nHost: $host\r\nUser-Agent: $userAgent\r\nAccept: */*\r\nConnection: close\r\n\r\n";
            fwrite($fp, $req);
            $raw = '';
            while (!feof($fp)) {
                $chunk = fread($fp, 8192);
                if ($chunk === false) {
                    break;
                }
                $raw .= $chunk;
            }
            fclose($fp);

            $headerEnd = strpos($raw, "\r\n\r\n");
            if ($headerEnd === false) {
                return null;
            }
            $head = substr($raw, 0, $headerEnd);
            $body = substr($raw, $headerEnd + 4);
            if (!preg_match('#^HTTP/\S+\s+(\d{3})#i', $head, $m)) {
                return null;
            }
            $code = (int) $m[1];
            if ($code >= 300 && $code < 400 && preg_match('#^Location:\s*(\S+)#im', $head, $lm)) {
                $loc = trim($lm[1]);
                $current = str_starts_with($loc, 'http') ? $loc : $scheme . '://' . $host . (str_starts_with($loc, '/') ? $loc : '/' . $loc);
                continue;
            }
            if ($code >= 400) {
                return null;
            }
            if (preg_match('#^Transfer-Encoding:\s*chunked#im', $head)) {
                $body = self::decodeChunked($body);
            }
            return $body;
        }
        return null;
    }

    private static function decodeChunked(string $body): string
    {
        $out = '';
        $pos = 0;
        $len = strlen($body);
        while ($pos < $len) {
            $lineEnd = strpos($body, "\r\n", $pos);
            if ($lineEnd === false) {
                break;
            }
            $size = hexdec(trim(substr($body, $pos, $lineEnd - $pos)));
            if ($size === 0) {
                break;
            }
            $out .= substr($body, $lineEnd + 2, $size);
            $pos = $lineEnd + 2 + $size + 2;
        }
        return $out;
    }
}
