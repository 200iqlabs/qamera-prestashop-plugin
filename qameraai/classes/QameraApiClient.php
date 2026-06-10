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
     * Product state with embedded images + packshots (roles, voting, lineage).
     * GET /api/v1/plugin/products/{external_ref}
     *
     * Response is the source of truth for generation state. Expected shape
     * (defensively parsed by the caller — keys may drift):
     *   {
     *     external_ref, product_ref, display_name,
     *     images:    [ { id, asset_id, url, thumbnail_url, analysis_status } ],
     *     packshots: [ { id, packshot_asset_id|asset_id, source_image_id,
     *                    generated_by_job_id, url, thumbnail_url,
     *                    voting: "accepted"|"pending"|"rejected" } ]
     *   }
     *
     * @param string $externalRef Stable shop->Qamera map key (e.g. ps-{id_product}).
     * @return array Decoded JSON body on success.
     * @throws QameraApiException On missing key, transport error, or API error envelope.
     */
    public function get_product($externalRef)
    {
        return $this->request('GET', '/products/' . rawurlencode((string) $externalRef));
    }

    /**
     * List jobs (sessions/packshot generations) for the account.
     * GET /api/v1/plugin/jobs
     *
     * Used to attach session results to their source packshot (by
     * packshot_asset_id) since /products has no embedded session lineage.
     *
     * @param int    $limit  Page size (0 = server default).
     * @param string $cursor Pagination cursor.
     * @return array Decoded JSON body on success.
     * @throws QameraApiException On missing key, transport error, or API error envelope.
     */
    public function list_jobs($limit = 50, $cursor = '')
    {
        $query = [];
        if ((int) $limit > 0) {
            $query['limit'] = (int) $limit;
        }
        if ((string) $cursor !== '') {
            $query['cursor'] = (string) $cursor;
        }
        $qs = $query ? ('?' . http_build_query($query)) : '';

        return $this->request('GET', '/jobs' . $qs);
    }

    /**
     * Single job with outputs + voting + status (polling fallback).
     * GET /api/v1/plugin/jobs/{id}
     *
     * @param string $jobId
     * @return array Decoded JSON body on success.
     * @throws QameraApiException On missing key, transport error, or API error envelope.
     */
    public function get_job($jobId)
    {
        return $this->request('GET', '/jobs/' . rawurlencode((string) $jobId));
    }

    /**
     * Upload a raw file as an asset (multipart/form-data).
     * POST /api/v1/plugin/assets/upload
     *
     * Direct multipart mode: the bytes are sent in the request and the
     * response carries the registered asset_id (no follow-up PUT needed).
     *
     * @param string $filePath    Absolute path to the local file to upload.
     * @param string $fileName    Original file name (defaults to basename).
     * @param string $contentType MIME type (e.g. image/jpeg). '' lets cURL guess.
     * @return array Decoded JSON body (AssetUploadResponse) on success.
     * @throws QameraApiException On missing key, transport error, or API error envelope.
     */
    public function upload_asset($filePath, $fileName = '', $contentType = '')
    {
        $name = ($fileName !== '') ? $fileName : basename((string) $filePath);

        return $this->requestMultipart('/assets/upload', (string) $filePath, $name, (string) $contentType);
    }

    /**
     * Register a generation-ready packshot in the product catalog.
     * POST /api/v1/plugin/packshots
     *
     * Idempotent per (installation, external_ref). When $sourceImageRef is
     * empty the service auto-creates a backing product_images row and queues
     * it for analysis; identical content is deduplicated by SHA-256.
     *
     * @param string $externalRef     Stable packshot identifier (unique per installation).
     * @param string $productRef       Parent product external_ref.
     * @param string $assetId          asset_id from upload_asset().
     * @param string $sourceImageRef   Optional source image external_ref this packshot derives from.
     * @param array  $productMetadata  Optional metadata to cascade-create the product (display_name, sku...).
     * @param string $idempotencyKey   Optional Idempotency-Key.
     * @return array Decoded JSON body (RegisterPackshotsResponse) on success.
     * @throws QameraApiException On missing key, transport error, or API error envelope.
     */
    public function register_packshot($externalRef, $productRef, $assetId, $sourceImageRef = '', array $productMetadata = [], $idempotencyKey = '')
    {
        $item = [
            'external_ref' => (string) $externalRef,
            'product_ref' => (string) $productRef,
            'asset_id' => (string) $assetId,
        ];
        if ((string) $sourceImageRef !== '') {
            $item['source_image_ref'] = (string) $sourceImageRef;
        }
        if (!empty($productMetadata)) {
            $item['product_metadata'] = $productMetadata;
        }

        $headers = [];
        if ((string) $idempotencyKey !== '') {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }

        return $this->request('POST', '/packshots', ['packshots' => [$item]], $headers);
    }

    /**
     * Submit a single generation session (one shared config, N subjects).
     * POST /api/v1/plugin/jobs
     *
     * @param array  $sessionConfig  SessionConfig (preset_id, model_id, scenery_id, aspect_ratio, suggestions).
     * @param array  $subjects        List of Subject objects (product_ref, product_label, images_count, ai_model, ...).
     * @param string $jobType         photo_shoot | packshot | ... (default photo_shoot).
     * @param string $idempotencyKey  Optional Idempotency-Key for safe retry.
     * @return array Decoded JSON body (SubmitJobResponse) on success.
     * @throws QameraApiException On missing key, transport error, or API error envelope.
     */
    public function submit_job(array $sessionConfig, array $subjects, $jobType = 'photo_shoot', $idempotencyKey = '')
    {
        $body = [
            // Cast to object so an empty config still serializes as {} not [].
            'session_config' => (object) $sessionConfig,
            'subjects' => array_values($subjects),
        ];
        if ((string) $jobType !== '') {
            $body['job_type'] = (string) $jobType;
        }

        $headers = [];
        if ((string) $idempotencyKey !== '') {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }

        return $this->request('POST', '/jobs', $body, $headers);
    }

    /**
     * Record an accept vote on a completed job. For packshot jobs this also
     * approves the generated packshot (unblocks photo sessions).
     * POST /api/v1/plugin/jobs/{id}/accept (204 No Content).
     *
     * @param string $jobId
     * @return array Empty array on success (no body).
     * @throws QameraApiException On missing key, transport error, or API error envelope.
     */
    public function accept_job($jobId)
    {
        return $this->request('POST', '/jobs/' . rawurlencode((string) $jobId) . '/accept');
    }

    /**
     * Record a reject vote on a completed job.
     * POST /api/v1/plugin/jobs/{id}/reject (204 No Content).
     *
     * @param string $jobId
     * @return array Empty array on success (no body).
     * @throws QameraApiException On missing key, transport error, or API error envelope.
     */
    public function reject_job($jobId)
    {
        return $this->request('POST', '/jobs/' . rawurlencode((string) $jobId) . '/reject');
    }

    /**
     * Available mannequin/models for the account (account + marketplace).
     * GET /api/v1/plugin/models
     *
     * @return array Decoded JSON body on success.
     * @throws QameraApiException On missing key, transport error, or API error envelope.
     */
    public function get_models()
    {
        return $this->request('GET', '/models');
    }

    /**
     * Available sceneries (account + marketplace).
     * GET /api/v1/plugin/sceneries
     *
     * @return array Decoded JSON body on success.
     * @throws QameraApiException On missing key, transport error, or API error envelope.
     */
    public function get_sceneries()
    {
        return $this->request('GET', '/sceneries');
    }

    /**
     * Available generative AI models (filtered by account plan).
     * GET /api/v1/plugin/ai-models
     *
     * @return array Decoded JSON body on success.
     * @throws QameraApiException On missing key, transport error, or API error envelope.
     */
    public function get_ai_models()
    {
        return $this->request('GET', '/ai-models');
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

        // 2xx with an empty body (e.g. 204 from accept/reject) is success.
        if ($decoded === null && trim((string) $raw) === '') {
            return [];
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
     * Perform a multipart/form-data upload (single "file" field).
     *
     * @param string $path        Path relative to API_PREFIX (leading slash).
     * @param string $filePath    Absolute path to the file on disk.
     * @param string $fileName    Original file name sent to the API.
     * @param string $contentType MIME type ('' lets cURL decide).
     *
     * @return array Decoded JSON body.
     * @throws QameraApiException
     */
    public function requestMultipart($path, $filePath, $fileName, $contentType = '')
    {
        if (!$this->hasKey()) {
            throw new QameraApiException(
                'missing_api_key',
                'Brak klucza API. Wklej klucz Qamera AI w ustawieniach modułu.'
            );
        }

        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new QameraApiException(
                'invalid_input',
                'Nie można odczytać pliku do wysłania.'
            );
        }

        $url = $this->apiBase . self::API_PREFIX . $path;

        $cfile = ($contentType !== '')
            ? new CURLFile($filePath, $contentType, $fileName)
            : new CURLFile($filePath, '', $fileName);

        $headers = [
            'X-Api-Key: ' . $this->apiKey,
            'Accept: application/json',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['file' => $cfile]);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new QameraApiException(
                'transport_error',
                'Nie można wysłać pliku do Qamera AI: ' . $error
            );
        }

        $decoded = json_decode((string) $raw, true);

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
