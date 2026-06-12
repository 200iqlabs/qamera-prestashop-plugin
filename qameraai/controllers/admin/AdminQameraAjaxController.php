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
    private function apiError(QameraApiException $e)
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

        // Packshot generates with NO style params: always exactly 1, and the AI
        // model comes from module Configuration (QAMERA_AI_MODEL) — shared with
        // sessions. Style/count/model belong to the session, not the packshot.
        $aiModel = trim((string) Configuration::get('QAMERA_AI_MODEL'));
        if ($aiModel === '') {
            $this->json(['ok' => false, 'error' => $this->l('Skonfiguruj model AI w ustawieniach modułu Qamera AI przed generacją.')]);
        }

        // Source is an existing PrestaShop gallery image — never an upload to
        // the plugin. The merchant adds photos to the product gallery normally.
        $idImage = (int) Tools::getValue('id_image');
        $filePath = $this->localImagePath($idImage);
        if ($filePath === '') {
            $this->json(['ok' => false, 'error' => $this->l('Nie znaleziono pliku zdjęcia w galerii produktu.')]);
        }

        // Dedup by SHA-256 of the gallery file: the asset_id is cached so the
        // same image is uploaded+registered only once.
        $sha = hash_file('sha256', $filePath);
        if ($sha === false || $sha === '') {
            $this->json(['ok' => false, 'error' => $this->l('Nie można odczytać pliku zdjęcia.')]);
        }

        // For job_type=packshot the API requires packshot_asset_id to reference
        // a registered product_packshots row (PLUGIN_JOB_MISSING_CATALOG_ENTRY
        // otherwise) — so the raw input is registered via POST /packshots. The
        // backing image row is auto-created and analyzed, satisfying the
        // analysis_status='described' gate below.
        try {
            $assetId = $this->ensureCatalogAsset($client, $externalRef, $idProduct, $idImage, $filePath, $sha, true);
        } catch (QameraApiException $e) {
            $this->apiError($e);
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
        // carry the raw input asset and opt into catalog write-back. No
        // session_config — packshots carry no style params (1 image, fixed).
        $packshotRef = $externalRef . '-pk-' . substr($sha, 0, 12);
        $subject = [
            'product_ref' => $externalRef,
            'product_label' => $this->productLabel($idProduct),
            'images_count' => 1,
            'ai_model' => $aiModel,
            'packshot_asset_id' => $assetId,
            'auto_register_packshot' => true,
            'packshot_external_ref' => $packshotRef,
        ];

        // Idempotency-Key derived from the canonical payload so a retried POST
        // does not double-charge.
        $idem = 'pk-' . substr(hash('sha256', json_encode([$subject, 'packshot'])), 0, 40);

        try {
            $res = $client->submit_job([], [$subject], 'packshot', $idem);
        } catch (QameraApiException $e) {
            $this->apiError($e);
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
     * Flow B core: submit a job_type=photo_shoot session from a packshot, using
     * the session style params from the left panel (preset/model/scenery/aspect/
     * suggestions) + count. The AI model comes from module Configuration (shared
     * with packshots). Hard rule (§3): a session is ALWAYS generated from a
     * packshot — never directly from a source image. Returns the N job ids (one
     * per requested image) for the JS poller, and persists the order mapping.
     *
     * @return void
     */
    public function ajaxProcessGenerateSession()
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

        $aiModel = trim((string) Configuration::get('QAMERA_AI_MODEL'));
        if ($aiModel === '') {
            $this->json(['ok' => false, 'error' => $this->l('Skonfiguruj model AI w ustawieniach modułu Qamera AI przed generacją.')]);
        }

        // images_count from the session panel. UI offers 1..10; clamp to that.
        $count = (int) Tools::getValue('count', 1);
        if ($count < 1) {
            $count = 1;
        }
        if ($count > 10) {
            $count = 10;
        }

        // Hard rule (§3): the source must be a packshot. packshot_asset_id is
        // optional in the API (omitted => backend resolves the latest accepted
        // packshot for the product), but when provided we verify it is actually
        // a packshot of THIS product — blocking any attempt to seed a session
        // from a raw source image.
        $packshotAsset = trim((string) Tools::getValue('packshot_asset_id'));
        if ($packshotAsset !== '' && $this->assertProductPackshot($client, $externalRef, $packshotAsset) === 'not_packshot') {
            $this->json([
                'ok' => false,
                'error' => $this->l('Sesję można zlecić tylko z packshotu (zatwierdzonego lub bezpośredniego), nie ze zdjęcia źródłowego.'),
            ]);
        }

        $sessionConfig = $this->buildSessionConfig();

        $subject = [
            'product_ref' => $externalRef,
            'product_label' => $this->productLabel($idProduct),
            'images_count' => $count,
            'ai_model' => $aiModel,
        ];
        if ($packshotAsset !== '') {
            $subject['packshot_asset_id'] = $packshotAsset;
        }

        $idem = 'ses-' . substr(hash('sha256', json_encode([$sessionConfig, $subject, 'photo_shoot'])), 0, 40);

        try {
            $res = $client->submit_job($sessionConfig, [$subject], 'photo_shoot', $idem);
        } catch (QameraApiException $e) {
            $this->apiError($e);
        }

        $jobIds = $this->allJobIds($res);
        if (empty($jobIds)) {
            $this->json(['ok' => false, 'error' => $this->l('API nie zwróciło zadań sesji.')]);
        }

        $orderId = isset($res['order_id']) ? (string) $res['order_id'] : '';
        if ($orderId !== '') {
            $this->saveOrderMapping($idProduct, $orderId);
        }

        $this->json([
            'ok' => true,
            'order_id' => $orderId,
            'job_ids' => $jobIds,
        ]);
    }

    /**
     * Verify $assetId is a packshot of the product (enforces the §3 hard rule).
     *
     * @param QameraApiClient $client
     * @param string          $externalRef
     * @param string          $assetId
     * @return string ok | not_packshot | unknown (API unreachable — let backend gate).
     */
    private function assertProductPackshot(QameraApiClient $client, $externalRef, $assetId)
    {
        try {
            $product = $client->get_product($externalRef);
        } catch (QameraApiException $e) {
            return 'unknown';
        }

        $packshots = (isset($product['packshots']) && is_array($product['packshots'])) ? $product['packshots'] : [];
        foreach ($packshots as $pk) {
            if (is_array($pk) && isset($pk['asset_id']) && (string) $pk['asset_id'] === $assetId) {
                return 'ok';
            }
        }

        return 'not_packshot';
    }

    /**
     * Collect every job id from a SubmitJobResponse (one per requested image).
     *
     * @param array $res
     * @return array
     */
    private function allJobIds(array $res)
    {
        $ids = [];
        if (!isset($res['subjects']) || !is_array($res['subjects'])) {
            return $ids;
        }
        foreach ($res['subjects'] as $subject) {
            if (!is_array($subject) || !isset($subject['job_ids']) || !is_array($subject['job_ids'])) {
                continue;
            }
            foreach ($subject['job_ids'] as $jid) {
                if (is_string($jid) && $jid !== '') {
                    $ids[] = $jid;
                }
            }
        }

        return $ids;
    }

    /**
     * Persist the product -> Qamera session mapping (ps_qamera_order). Best
     * effort — the source of truth for state stays the API.
     *
     * @param int    $idProduct
     * @param string $orderId
     * @return void
     */
    private function saveOrderMapping($idProduct, $orderId)
    {
        try {
            Db::getInstance()->insert('qamera_order', [
                'id_product' => (int) $idProduct,
                'qamera_order_id' => pSQL((string) $orderId),
                'date_add' => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            // mapping is best-effort
        }
    }

    /**
     * Absolute path of a PrestaShop product gallery image file, or '' when the
     * id_image is invalid or the file is missing.
     *
     * @param int $idImage
     * @return string
     */
    private function localImagePath($idImage)
    {
        $idImage = (int) $idImage;
        if ($idImage <= 0) {
            return '';
        }
        $path = _PS_PROD_IMG_DIR_ . Image::getImgFolderStatic($idImage) . $idImage . '.jpg';

        return is_file($path) ? $path : '';
    }

    /**
     * Ensure a PrestaShop gallery image exists in the Qamera catalog and return
     * its asset_id. Uploads the file bytes once (dedup by sha256, cached on the
     * content), then registers it either as a source image (POST /images) or
     * directly as a packshot (POST /packshots). external_ref ties the catalog
     * row to the PrestaShop id_image: ps-{id_product}-img-{id_image}.
     *
     * @param QameraApiClient $client
     * @param string          $externalRef Product external_ref (ps-{id_product}).
     * @param int             $idProduct
     * @param int             $idImage     PrestaShop gallery image id.
     * @param string          $filePath    Absolute path to the gallery file.
     * @param string          $sha         sha256 of the file.
     * @param bool            $asPackshot  true => register as packshot, false => as image.
     * @return string asset_id
     * @throws QameraApiException
     */
    private function ensureCatalogAsset(QameraApiClient $client, $externalRef, $idProduct, $idImage, $filePath, $sha, $asPackshot)
    {
        $assetId = $this->ensureUploadedAsset($client, $filePath, $sha);

        $imageRef = $externalRef . '-img-' . (int) $idImage;
        $meta = ['display_name' => $this->productLabel($idProduct)];
        $idem = 'reg-' . substr($sha, 0, 36);

        if ($asPackshot) {
            $client->register_packshot($imageRef, $externalRef, $assetId, '', $meta, $idem . '-pk');
        } else {
            $client->register_image($imageRef, $externalRef, $assetId, $meta, $idem . '-img');
        }

        return $assetId;
    }

    /**
     * Upload the file bytes once and cache the resulting asset_id keyed by
     * sha256 (Configuration-backed dedup).
     *
     * @param QameraApiClient $client
     * @param string          $filePath
     * @param string          $sha
     * @return string asset_id
     * @throws QameraApiException
     */
    private function ensureUploadedAsset(QameraApiClient $client, $filePath, $sha)
    {
        $cacheKey = 'QAMERA_ASSET_' . substr($sha, 0, 40);
        $cached = (string) Configuration::get($cacheKey);
        if ($cached !== '') {
            return $cached;
        }

        $upload = $client->upload_asset($filePath, basename($filePath), 'image/jpeg');
        $assetId = isset($upload['asset_id']) ? (string) $upload['asset_id'] : '';
        if ($assetId === '') {
            throw new QameraApiException('invalid_response', $this->l('Upload nie zwrócił asset_id.'));
        }

        Configuration::updateValue($cacheKey, $assetId);

        return $assetId;
    }

    /**
     * Register a gallery image as a Qamera SOURCE IMAGE (POST /images). The
     * "Dodaj jako zdjęcie produktu" action — makes it generation-ready.
     *
     * @return void
     */
    public function ajaxProcessRegisterImage()
    {
        $this->registerGalleryImage(false);
    }

    /**
     * Register a gallery image DIRECTLY as a packshot (POST /packshots). The
     * "Dodaj jako packshot" action — skips generation.
     *
     * @return void
     */
    public function ajaxProcessRegisterPackshot()
    {
        $this->registerGalleryImage(true);
    }

    /**
     * Shared handler for the two per-image registration actions.
     *
     * @param bool $asPackshot
     * @return void
     */
    private function registerGalleryImage($asPackshot)
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

        $idImage = (int) Tools::getValue('id_image');
        $filePath = $this->localImagePath($idImage);
        if ($filePath === '') {
            $this->json(['ok' => false, 'error' => $this->l('Nie znaleziono pliku zdjęcia w galerii produktu.')]);
        }

        $sha = hash_file('sha256', $filePath);
        if ($sha === false || $sha === '') {
            $this->json(['ok' => false, 'error' => $this->l('Nie można odczytać pliku zdjęcia.')]);
        }

        try {
            $assetId = $this->ensureCatalogAsset($client, $externalRef, $idProduct, $idImage, $filePath, $sha, $asPackshot);
        } catch (QameraApiException $e) {
            $this->apiError($e);
        }

        $this->json([
            'ok' => true,
            'id_image' => $idImage,
            'asset_id' => $assetId,
            'as_packshot' => $asPackshot ? 1 : 0,
            'external_ref' => $externalRef . '-img-' . $idImage,
        ]);
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
            $this->apiError($e);
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
     * Accept a session image AND publish it to the product gallery (Flow B,
     * §3 step 7: zatwierdź → ps_image). Records the accept vote, downloads the
     * generated output, and imports it as a PrestaShop product image — deduped
     * by output sha256 (ps_qamera_import) so a re-accept / reload never creates
     * a duplicate. The imported image shows on the storefront.
     *
     * @return void
     */
    public function ajaxProcessAcceptSession()
    {
        $client = $this->getApiClient();
        if (!$client->hasKey()) {
            $this->json(['ok' => false, 'error' => $this->l('Brak klucza API.')]);
        }

        $jobId = trim((string) Tools::getValue('job_id'));
        if ($jobId === '') {
            $this->json(['ok' => false, 'error' => $this->l('Brak identyfikatora zadania.')]);
        }
        $idProduct = (int) Tools::getValue('id_product');
        if ($idProduct <= 0) {
            $this->json(['ok' => false, 'error' => $this->l('Brak identyfikatora produktu.')]);
        }

        // Record the accept vote, then resolve the output to publish.
        try {
            $client->accept_job($jobId);
            $job = $client->get_job($jobId);
        } catch (QameraApiException $e) {
            $this->apiError($e);
        }

        $url = $this->firstOutputUrl($job);
        if ($url === '') {
            $this->json(['ok' => false, 'error' => $this->l('Zatwierdzono, ale wynik nie ma jeszcze pliku do publikacji.')]);
        }

        $import = $this->importOutputToGallery($idProduct, $jobId, $url);
        if (empty($import['ok'])) {
            $this->json(['ok' => false, 'error' => $import['error']]);
        }

        $this->json([
            'ok' => true,
            'job_id' => $jobId,
            'voting' => 'accepted',
            'id_image' => $import['id_image'],
            'duplicate' => !empty($import['duplicate']),
        ]);
    }

    /**
     * Download a generated output and import it as a PrestaShop product image.
     * Dedup by sha256 of the bytes (ps_qamera_import). Generates the configured
     * product image thumbnails so the image renders in admin + storefront.
     *
     * @param int    $idProduct
     * @param string $jobId
     * @param string $url
     * @return array{ok: bool, id_image?: int, duplicate?: bool, error?: string}
     */
    private function importOutputToGallery($idProduct, $jobId, $url)
    {
        $bytes = $this->downloadUrl($url);
        if ($bytes === '') {
            return ['ok' => false, 'error' => $this->l('Nie udało się pobrać wygenerowanego zdjęcia.')];
        }
        $sha = hash('sha256', $bytes);

        // Dedup: this exact output already in the gallery → return its id_image.
        $existing = (int) Db::getInstance()->getValue(
            'SELECT id_image FROM `' . _DB_PREFIX_ . 'qamera_import` WHERE output_sha = \'' . pSQL($sha) . '\''
        );
        if ($existing > 0) {
            return ['ok' => true, 'id_image' => $existing, 'duplicate' => true];
        }

        $tmp = tempnam(_PS_TMP_IMG_DIR_, 'qamera');
        if ($tmp === false || file_put_contents($tmp, $bytes) === false) {
            if ($tmp !== false) {
                @unlink($tmp);
            }

            return ['ok' => false, 'error' => $this->l('Nie udało się zapisać pliku zdjęcia.')];
        }

        $image = new Image();
        $image->id_product = (int) $idProduct;
        $image->position = Image::getHighestPosition((int) $idProduct) + 1;
        // First image of a product with no cover becomes the cover.
        $image->cover = Image::getCover((int) $idProduct) ? false : true;

        if (!$image->add()) {
            @unlink($tmp);

            return ['ok' => false, 'error' => $this->l('Nie udało się utworzyć obrazu produktu.')];
        }
        // Associate to the current shop(s) so the image appears on the storefront.
        $image->associateTo(Shop::getContextListShopID());

        $newPath = $image->getPathForCreation();
        if (!ImageManager::resize($tmp, $newPath . '.jpg')) {
            $image->delete();
            @unlink($tmp);

            return ['ok' => false, 'error' => $this->l('Nie udało się przetworzyć zdjęcia.')];
        }
        // Generate the configured product image thumbnails (home/large/etc.).
        $types = ImageType::getImagesTypes('products');
        foreach ($types as $type) {
            ImageManager::resize(
                $tmp,
                $newPath . '-' . stripslashes($type['name']) . '.jpg',
                (int) $type['width'],
                (int) $type['height'],
                'jpg'
            );
        }
        @unlink($tmp);

        try {
            Db::getInstance()->insert('qamera_import', [
                'id_product' => (int) $idProduct,
                'id_image' => (int) $image->id,
                'qamera_job_id' => pSQL((string) $jobId),
                'output_sha' => pSQL($sha),
                'date_add' => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            // dedup row is best-effort; the image is already published
        }

        return ['ok' => true, 'id_image' => (int) $image->id, 'duplicate' => false];
    }

    /**
     * Download a signed output URL (no API key — the URL is pre-signed). Returns
     * the body bytes, or '' on any transport / non-2xx error.
     *
     * @param string $url
     * @return string
     */
    private function downloadUrl($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, (string) $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || $status < 200 || $status >= 300 || !is_string($raw) || $raw === '') {
            return '';
        }

        return $raw;
    }

    /**
     * Hard-delete a packshot from the catalog (DELETE /packshots/{idOrRef}).
     * Accepts either the packshot UUID or its external_ref.
     *
     * @return void
     */
    public function ajaxProcessDeletePackshot()
    {
        $client = $this->getApiClient();
        if (!$client->hasKey()) {
            $this->json(['ok' => false, 'error' => $this->l('Brak klucza API.')]);
        }

        $ref = trim((string) Tools::getValue('packshot_ref'));
        if ($ref === '') {
            $this->json(['ok' => false, 'error' => $this->l('Brak identyfikatora packshota.')]);
        }

        try {
            $client->delete_packshot($ref);
        } catch (QameraApiException $e) {
            // A 404 means it is already gone — treat as success so the UI tile
            // is removed either way.
            if ($e->getHttpStatus() === 404) {
                $this->json(['ok' => true, 'packshot_ref' => $ref, 'already_gone' => true]);
            }
            $this->apiError($e);
        }

        $this->json(['ok' => true, 'packshot_ref' => $ref]);
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
            $this->apiError($e);
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
