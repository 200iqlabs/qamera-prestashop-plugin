<?php
/**
 * Qamera AI for PrestaShop — admin AJAX controller (Flow A).
 *
 * Flow A: product photo -> packshot. The browser cannot hold the API key, so
 * every Qamera call is proxied server-side here. All actions are synchronous
 * (no cron/queue/webhook); the packshot result is delivered via JS polling of
 * the getJob action.
 *
 * Actions (action=<name>&ajax=1):
 *   - generatePackshot: upload+register source asset, wait for analysis,
 *     submit a job_type=packshot session, return the job id.
 *   - getJob:           proxy GET /jobs/{id} for the JS poller.
 *   - acceptJob:        POST /jobs/{id}/accept.
 *   - rejectJob:        POST /jobs/{id}/reject.
 *
 * API errors surface as { ok:false, error:<readable> } in the same response.
 *
 * PHP 7.4 compatible (PrestaShop 8.x / 9.x).
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminQameraAjaxController extends ModuleAdminController
{
    /** @var int Source-image analysis poll: max attempts before giving up. */
    const ANALYSIS_MAX_ATTEMPTS = 6;

    /** @var int Source-image analysis poll: seconds between attempts. */
    const ANALYSIS_WAIT_SECONDS = 2;

    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    /**
     * Build the API client from stored Configuration (same source as the module).
     *
     * @return QameraApiClient
     */
    private function getApiClient()
    {
        require_once _PS_MODULE_DIR_ . 'qameraai/classes/QameraApiClient.php';

        $apiKey = (string) Configuration::get('QAMERA_API_KEY');
        $apiBase = (string) Configuration::get('QAMERA_API_BASE');
        if ($apiBase === '') {
            $apiBase = QameraApiClient::DEFAULT_API_BASE;
        }

        return new QameraApiClient($apiKey, $apiBase);
    }

    /**
     * Stable shop->Qamera mapping key for a product (mirrors the module).
     *
     * @param int $idProduct
     * @return string
     */
    private function buildExternalRef($idProduct)
    {
        return 'ps-' . (int) $idProduct;
    }

    /**
     * Emit a JSON payload and stop. Always HTTP 200 — error state is carried
     * in the body so the browser reads a readable message immediately.
     *
     * @param array $payload
     * @return void
     */
    private function json(array $payload)
    {
        header('Content-Type: application/json');
        $this->ajaxRender(json_encode($payload));
        exit;
    }

    /**
     * Translate a QameraApiException into a JSON error response.
     *
     * @param QameraApiException $e
     * @return void
     */
    private function jsonError(QameraApiException $e)
    {
        $this->json([
            'ok' => false,
            'code' => $e->getApiCode(),
            'status' => $e->getHttpStatus(),
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * Flow A core: ensure the source asset exists in the Qamera catalog
     * (upload -> register -> cache by sha256), wait for the source image to be
     * analyzed (analysis_status='described'), then submit a job_type=packshot
     * session and return its job id. Fully synchronous.
     *
     * @return void
     */
    public function ajaxProcessGeneratePackshot()
    {
        $client = $this->getApiClient();
        if (!$client->hasKey()) {
            $this->json(['ok' => false, 'error' => $this->l('Brak klucza API. Skonfiguruj moduł Qamera AI w ustawieniach.')]);
        }

        $idProduct = (int) Tools::getValue('id_product');
        if ($idProduct <= 0) {
            $this->json(['ok' => false, 'error' => $this->l('Brak identyfikatora produktu.')]);
        }
        $externalRef = $this->buildExternalRef($idProduct);

        // Variant count is clamped to the API-allowed 1..10 range.
        $count = (int) Tools::getValue('count', 1);
        if ($count < 1) {
            $count = 1;
        }
        if ($count > 10) {
            $count = 10;
        }

        $aiModel = trim((string) Tools::getValue('ai_model'));
        if ($aiModel === '') {
            $this->json(['ok' => false, 'error' => $this->l('Wybierz model AI przed generacją.')]);
        }

        if (!isset($_FILES['file']) || !is_array($_FILES['file']) || empty($_FILES['file']['tmp_name'])) {
            $this->json(['ok' => false, 'error' => $this->l('Brak pliku źródłowego. Wgraj zdjęcie produktu.')]);
        }
        $file = $_FILES['file'];
        if (isset($file['error']) && (int) $file['error'] !== UPLOAD_ERR_OK) {
            $this->json(['ok' => false, 'error' => $this->l('Błąd przesyłania pliku (kod) ') . (int) $file['error'] . '.']);
        }
        $tmpPath = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmpPath) && !is_file($tmpPath)) {
            $this->json(['ok' => false, 'error' => $this->l('Nieprawidłowy plik źródłowy.')]);
        }
        $fileName = isset($file['name']) ? (string) $file['name'] : 'product.jpg';
        $contentType = isset($file['type']) ? (string) $file['type'] : '';

        // Dedup by SHA-256: the asset_id is cached on the source content so a
        // repeated upload of the same photo reuses the existing catalog asset.
        $sha = hash_file('sha256', $tmpPath);
        if ($sha === false || $sha === '') {
            $this->json(['ok' => false, 'error' => $this->l('Nie można odczytać pliku źródłowego.')]);
        }

        try {
            $assetId = $this->ensureSourceAsset($client, $externalRef, $idProduct, $tmpPath, $fileName, $contentType, $sha);
        } catch (QameraApiException $e) {
            $this->jsonError($e);
        }

        // Wait for the source image analysis to reach 'described' — otherwise
        // the generation worker may reject the job with PREPARE_PHOTOS_TIMEOUT.
        $analysis = $this->waitForDescribed($client, $externalRef, $assetId);
        if ($analysis === 'error') {
            $this->json(['ok' => false, 'error' => $this->l('Analiza zdjęcia źródłowego nie powiodła się. Wgraj inne zdjęcie.')]);
        }
        if ($analysis !== 'described') {
            $this->json([
                'ok' => false,
                'code' => 'analysis_pending',
                'error' => $this->l('Zdjęcie źródłowe jest wciąż analizowane. Spróbuj ponownie za chwilę.'),
            ]);
        }

        // Build the packshot session. For job_type=packshot every subject MUST
        // carry the raw input asset and opt into catalog write-back.
        $sessionConfig = $this->buildSessionConfig();
        $packshotRef = $externalRef . '-pk-' . substr($sha, 0, 12);
        $subject = [
            'product_ref' => $externalRef,
            'product_label' => $this->productLabel($idProduct),
            'images_count' => $count,
            'ai_model' => $aiModel,
            'packshot_asset_id' => $assetId,
            'auto_register_packshot' => true,
            'packshot_external_ref' => $packshotRef,
        ];

        // Idempotency-Key derived from the canonical payload so a retried POST
        // does not double-charge.
        $idem = 'pk-' . substr(hash('sha256', json_encode([$sessionConfig, $subject, 'packshot'])), 0, 40);

        try {
            $res = $client->submit_job($sessionConfig, [$subject], 'packshot', $idem);
        } catch (QameraApiException $e) {
            $this->jsonError($e);
        }

        $jobId = $this->firstJobId($res);
        if ($jobId === '') {
            $this->json(['ok' => false, 'error' => $this->l('API nie zwróciło identyfikatora zadania.')]);
        }

        $this->json([
            'ok' => true,
            'job_id' => $jobId,
            'order_id' => isset($res['order_id']) ? (string) $res['order_id'] : '',
            'packshot_external_ref' => $packshotRef,
        ]);
    }

    /**
     * Ensure the source photo exists in the Qamera catalog and return its
     * asset_id. Dedups by sha256 via a Configuration-backed cache so the same
     * content is uploaded+registered only once.
     *
     * @param QameraApiClient $client
     * @param string          $externalRef
     * @param int             $idProduct
     * @param string          $tmpPath
     * @param string          $fileName
     * @param string          $contentType
     * @param string          $sha
     * @return string asset_id
     * @throws QameraApiException
     */
    private function ensureSourceAsset(QameraApiClient $client, $externalRef, $idProduct, $tmpPath, $fileName, $contentType, $sha)
    {
        $cacheKey = 'QAMERA_ASSET_' . substr($sha, 0, 40);
        $cached = (string) Configuration::get($cacheKey);
        if ($cached !== '') {
            return $cached;
        }

        // 1) Upload bytes -> asset_id.
        $upload = $client->upload_asset($tmpPath, $fileName, $contentType);
        $assetId = isset($upload['asset_id']) ? (string) $upload['asset_id'] : '';
        if ($assetId === '') {
            throw new QameraApiException('invalid_response', $this->l('Upload nie zwrócił asset_id.'));
        }

        // 2) Register the asset as a catalog packshot. external_ref is keyed by
        // content so a re-register is idempotent. product_metadata cascades the
        // product create when the product_ref is new to Qamera.
        $packshotRef = $externalRef . '-src-' . substr($sha, 0, 12);
        $client->register_packshot(
            $packshotRef,
            $externalRef,
            $assetId,
            '',
            ['display_name' => $this->productLabel($idProduct)],
            'reg-' . substr($sha, 0, 40)
        );

        // 3) Cache asset_id on the source content (dedup).
        Configuration::updateValue($cacheKey, $assetId);

        return $assetId;
    }

    /**
     * Poll GET /products/{ref} until the source image (matched by asset_id)
     * reaches analysis_status='described'. Bounded so the request stays within
     * the PHP execution window.
     *
     * @param QameraApiClient $client
     * @param string          $externalRef
     * @param string          $assetId
     * @return string One of: described | error | pending (last seen state).
     */
    private function waitForDescribed(QameraApiClient $client, $externalRef, $assetId)
    {
        $last = 'pending';

        for ($attempt = 0; $attempt < self::ANALYSIS_MAX_ATTEMPTS; $attempt++) {
            try {
                $product = $client->get_product($externalRef);
            } catch (QameraApiException $e) {
                // 404 = product not yet visible; keep waiting. Other errors stop.
                if ($e->getHttpStatus() === 404) {
                    $last = 'pending';
                } else {
                    return 'pending';
                }
                $product = [];
            }

            $images = (isset($product['images']) && is_array($product['images'])) ? $product['images'] : [];
            foreach ($images as $img) {
                if (!is_array($img)) {
                    continue;
                }
                $imgAsset = isset($img['asset_id']) ? (string) $img['asset_id'] : '';
                if ($imgAsset !== '' && $imgAsset === $assetId) {
                    $last = isset($img['analysis_status']) ? (string) $img['analysis_status'] : 'pending';
                    break;
                }
            }

            if ($last === 'described' || $last === 'error') {
                return $last;
            }

            // Don't sleep after the final probe.
            if ($attempt < self::ANALYSIS_MAX_ATTEMPTS - 1) {
                sleep(self::ANALYSIS_WAIT_SECONDS);
            }
        }

        return $last;
    }

    /**
     * Build the SessionConfig from the posted session settings. Empty values
     * are dropped so the API applies its defaults.
     *
     * @return array
     */
    private function buildSessionConfig()
    {
        $config = [];

        $preset = trim((string) Tools::getValue('preset_id'));
        if ($preset !== '') {
            $config['preset_id'] = $preset;
        }
        $model = trim((string) Tools::getValue('model_id'));
        if ($model !== '') {
            $config['model_id'] = $model;
        }
        $scenery = trim((string) Tools::getValue('scenery_id'));
        if ($scenery !== '') {
            $config['scenery_id'] = $scenery;
        }
        $aspect = trim((string) Tools::getValue('aspect_ratio'));
        if ($aspect !== '') {
            $config['aspect_ratio'] = $aspect;
        }
        $suggestions = trim((string) Tools::getValue('suggestions'));
        if ($suggestions !== '') {
            $config['suggestions'] = Tools::substr($suggestions, 0, 2000);
        }

        return $config;
    }

    /**
     * Human-readable product name for the subject label.
     *
     * @param int $idProduct
     * @return string
     */
    private function productLabel($idProduct)
    {
        try {
            $idLang = (int) $this->context->language->id;
            $product = new Product((int) $idProduct, false, $idLang);
            $name = is_array($product->name) ? reset($product->name) : $product->name;
            $name = trim((string) $name);
            if ($name !== '') {
                return Tools::substr($name, 0, 200);
            }
        } catch (Exception $e) {
            // fall through to default
        }

        return 'Product ' . (int) $idProduct;
    }

    /**
     * Extract the first job id from a SubmitJobResponse.
     *
     * @param array $res
     * @return string
     */
    private function firstJobId(array $res)
    {
        if (!isset($res['subjects']) || !is_array($res['subjects'])) {
            return '';
        }
        foreach ($res['subjects'] as $subject) {
            if (!is_array($subject) || !isset($subject['job_ids']) || !is_array($subject['job_ids'])) {
                continue;
            }
            foreach ($subject['job_ids'] as $jid) {
                if (is_string($jid) && $jid !== '') {
                    return $jid;
                }
            }
        }

        return '';
    }

    /**
     * Proxy GET /jobs/{id} for the JS poller. Returns the relevant slice of the
     * job (status, voting, first output URL) without leaking the API key.
     *
     * @return void
     */
    public function ajaxProcessGetJob()
    {
        $client = $this->getApiClient();
        if (!$client->hasKey()) {
            $this->json(['ok' => false, 'error' => $this->l('Brak klucza API.')]);
        }

        $jobId = trim((string) Tools::getValue('job_id'));
        if ($jobId === '') {
            $this->json(['ok' => false, 'error' => $this->l('Brak identyfikatora zadania.')]);
        }

        try {
            $job = $client->get_job($jobId);
        } catch (QameraApiException $e) {
            $this->jsonError($e);
        }

        $this->json([
            'ok' => true,
            'job_id' => isset($job['id']) ? (string) $job['id'] : $jobId,
            'status' => isset($job['status']) ? (string) $job['status'] : '',
            'voting' => isset($job['voting']) ? $job['voting'] : null,
            'url' => $this->firstOutputUrl($job),
            'error' => $this->jobErrorMessage($job),
        ]);
    }

    /**
     * Record an accept vote (also approves a packshot job's packshot).
     *
     * @return void
     */
    public function ajaxProcessAcceptJob()
    {
        $this->voteJob('accept');
    }

    /**
     * Record a reject vote.
     *
     * @return void
     */
    public function ajaxProcessRejectJob()
    {
        $this->voteJob('reject');
    }

    /**
     * Shared accept/reject handler.
     *
     * @param string $vote accept|reject
     * @return void
     */
    private function voteJob($vote)
    {
        $client = $this->getApiClient();
        if (!$client->hasKey()) {
            $this->json(['ok' => false, 'error' => $this->l('Brak klucza API.')]);
        }

        $jobId = trim((string) Tools::getValue('job_id'));
        if ($jobId === '') {
            $this->json(['ok' => false, 'error' => $this->l('Brak identyfikatora zadania.')]);
        }

        try {
            if ($vote === 'accept') {
                $client->accept_job($jobId);
            } else {
                $client->reject_job($jobId);
            }
        } catch (QameraApiException $e) {
            $this->jsonError($e);
        }

        $this->json(['ok' => true, 'job_id' => $jobId, 'voting' => $vote === 'accept' ? 'accepted' : 'rejected']);
    }

    /**
     * First signed output URL among a job's outputs, or ''.
     *
     * @param array $job
     * @return string
     */
    private function firstOutputUrl(array $job)
    {
        if (!isset($job['outputs']) || !is_array($job['outputs'])) {
            return '';
        }
        foreach ($job['outputs'] as $o) {
            if (is_array($o) && isset($o['url']) && is_string($o['url']) && $o['url'] !== '') {
                return $o['url'];
            }
        }

        return '';
    }

    /**
     * Readable last-error message from a job's error envelope, or ''.
     *
     * @param array $job
     * @return string
     */
    private function jobErrorMessage(array $job)
    {
        if (!isset($job['error']) || !is_array($job['error'])) {
            return '';
        }
        $err = $job['error'];
        if (isset($err['message_i18n']) && is_array($err['message_i18n'])) {
            foreach (['pl', 'en'] as $locale) {
                if (isset($err['message_i18n'][$locale]) && (string) $err['message_i18n'][$locale] !== '') {
                    return (string) $err['message_i18n'][$locale];
                }
            }
            foreach ($err['message_i18n'] as $value) {
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        return isset($err['code']) ? (string) $err['code'] : '';
    }
}
