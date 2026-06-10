<?php
/**
 * Qamera AI for PrestaShop — API client.
 *
 * Thin HTTP wrapper around the Qamera AI plugin API (base /api/v1/plugin).
 * Auth: X-Api-Key header. Error envelope: { error: { code, message_i18n } }.
 * Source of truth for generation state is the Qamera API, not this module.
 *
 * PHP 7.4 compatible (PrestaShop 8.x / 9.x).
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class QameraApiClient
{
    /** @var string */
    private $apiKey;

    /** @var string */
    private $apiBase;

    /** @var int Request timeout in seconds. */
    private $timeout;

    const DEFAULT_API_BASE = 'https://qamera.ai';
    const API_PREFIX = '/api/v1/plugin';

    /**
     * @param string $apiKey  Qamera API key (format mk_live_<keyId>.<secret>)
     * @param string $apiBase Base URL (default prod; override for local/dev)
     * @param int    $timeout Request timeout in seconds
     */
    public function __construct($apiKey, $apiBase = self::DEFAULT_API_BASE, $timeout = 30)
    {
        $this->apiKey = (string) $apiKey;
        $base = trim((string) $apiBase);
        if ($base === '') {
            $base = self::DEFAULT_API_BASE;
        }
        $this->apiBase = rtrim($base, '/');
        $this->timeout = (int) $timeout;
    }

    /**
     * Whether an API key is configured.
     *
     * @return bool
     */
    public function hasKey()
    {
        return $this->apiKey !== '';
    }

    /**
     * Account status + credit balance.
     * GET /api/v1/plugin/me
     *
     * @return array Decoded JSON body on success.
     * @throws QameraApiException On missing key, transport error, or API error envelope.
     */
    public function get_me()
    {
        return $this->request('GET', '/me');
    }

    /**
     * Available presets for the session parameter dropdown.
     * GET /api/v1/plugin/presets
     *
     * @return array Decoded JSON body on success.
     * @throws QameraApiException On missing key, transport error, or API error envelope.
     */
    public function get_presets()
    {
        return $this->request('GET', '/presets');
    }

    /**
     * Perform an HTTP request against the plugin API.
     *
     * @param string     $method  HTTP verb
     * @param string     $path     Path relative to API_PREFIX (leading slash)
     * @param array|null $body     Request body (JSON-encoded) for mutations
     * @param array      $headers  Extra headers (e.g. Idempotency-Key)
     *
     * @return array Decoded JSON body.
     * @throws QameraApiException
     */
    public function request($method, $path, $body = null, array $headers = [])
    {
        if (!$this->hasKey()) {
            throw new QameraApiException(
                'missing_api_key',
                'Brak klucza API. Wklej klucz Qamera AI w ustawieniach modułu.'
            );
        }

        $url = $this->apiBase . self::API_PREFIX . $path;

        $defaultHeaders = [
            'X-Api-Key: ' . $this->apiKey,
            'Accept: application/json',
        ];
        $payload = null;
        if ($body !== null) {
            $payload = json_encode($body);
            $defaultHeaders[] = 'Content-Type: application/json';
        }
        foreach ($headers as $h) {
            $defaultHeaders[] = $h;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $defaultHeaders);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new QameraApiException(
                'transport_error',
                'Nie można połączyć się z Qamera AI: ' . $error
            );
        }

        $decoded = json_decode((string) $raw, true);

        // API error envelope: { error: { code, message_i18n } }
        if (is_array($decoded) && isset($decoded['error']) && is_array($decoded['error'])) {
            $err = $decoded['error'];
            $code = isset($err['code']) ? (string) $err['code'] : 'api_error';
            $message = self::extractMessage($err, $status);
            throw new QameraApiException($code, $message, $status);
        }

        if ($status < 200 || $status >= 300) {
            throw new QameraApiException(
                'http_' . $status,
                self::defaultMessageForStatus($status),
                $status
            );
        }

        if (!is_array($decoded)) {
            throw new QameraApiException(
                'invalid_response',
                'Nieprawidłowa odpowiedź z API Qamera AI.',
                $status
            );
        }

        return $decoded;
    }

    /**
     * Resolve a readable message from the error envelope.
     * message_i18n may be a plain string or a locale-keyed map.
     *
     * @param array $err
     * @param int   $status
     * @return string
     */
    private static function extractMessage(array $err, $status)
    {
        if (isset($err['message_i18n'])) {
            $m = $err['message_i18n'];
            if (is_string($m) && $m !== '') {
                return $m;
            }
            if (is_array($m)) {
                foreach (['pl', 'en'] as $locale) {
                    if (isset($m[$locale]) && is_string($m[$locale]) && $m[$locale] !== '') {
                        return $m[$locale];
                    }
                }
                foreach ($m as $value) {
                    if (is_string($value) && $value !== '') {
                        return $value;
                    }
                }
            }
        }
        if (isset($err['message']) && is_string($err['message']) && $err['message'] !== '') {
            return $err['message'];
        }

        return self::defaultMessageForStatus($status);
    }

    /**
     * @param int $status
     * @return string
     */
    private static function defaultMessageForStatus($status)
    {
        switch ($status) {
            case 401:
            case 403:
                return 'Nieprawidłowy lub nieautoryzowany klucz API Qamera AI.';
            case 402:
                return 'Brak kredytów na koncie Qamera AI.';
            case 404:
                return 'Nie znaleziono zasobu w API Qamera AI.';
            case 429:
                return 'Zbyt wiele żądań do Qamera AI. Spróbuj ponownie za chwilę.';
            default:
                if ($status >= 500) {
                    return 'Błąd serwera Qamera AI. Spróbuj ponownie później.';
                }

                return 'Błąd komunikacji z API Qamera AI.';
        }
    }
}

/**
 * Exception carrying the API error code + readable message.
 */
class QameraApiException extends Exception
{
    /** @var string */
    private $apiCode;

    /** @var int */
    private $httpStatus;

    /**
     * @param string $apiCode
     * @param string $message
     * @param int    $httpStatus
     */
    public function __construct($apiCode, $message, $httpStatus = 0)
    {
        parent::__construct($message);
        $this->apiCode = (string) $apiCode;
        $this->httpStatus = (int) $httpStatus;
    }

    /**
     * @return string
     */
    public function getApiCode()
    {
        return $this->apiCode;
    }

    /**
     * @return int
     */
    public function getHttpStatus()
    {
        return $this->httpStatus;
    }
}
