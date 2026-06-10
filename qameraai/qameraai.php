<?php
/**
 * Qamera AI for PrestaShop.
 *
 * Thin wrapper around the Qamera AI API. Merchant generates packshots and
 * product sessions from the product page and publishes approved results into
 * the product gallery — without leaving the back office.
 *
 * Source of truth for generation state (roles, status, approval, lineage) is
 * the Qamera API, NOT this module's database. Local state is Thin-B:
 * Configuration + two ID-only mapping tables.
 *
 * Target: PrestaShop 8.x (PHP 7.4+) and 9.x (PHP 8.1+). PHP 7.4 compatible.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/QameraApiClient.php';
require_once __DIR__ . '/classes/QameraCatalogCache.php';

class Qameraai extends Module
{
    /** Configuration keys (ps_configuration). */
    const KEY_API_KEY = 'QAMERA_API_KEY';
    const KEY_DEFAULT_PRESET_ID = 'QAMERA_DEFAULT_PRESET_ID';
    const KEY_API_BASE = 'QAMERA_API_BASE';

    public function __construct()
    {
        $this->name = 'qameraai';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Qamera AI';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => '9.99.99'];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Qamera AI for PrestaShop');
        $this->description = $this->l('Generuj packshoty i sesje produktowe Qamera AI z karty produktu i publikuj zatwierdzone wyniki w galerii.');
        $this->confirmUninstall = $this->l('Czy na pewno usunąć moduł Qamera AI? Lokalne mapowania ID zostaną usunięte (stan generacji pozostaje w Qamera AI).');
    }

    /**
     * Install: create local tables, register Configuration keys, hook the
     * product page tab.
     *
     * @return bool
     */
    public function install()
    {
        // displayAdminProductsExtra is the shared product-page tab hook on both
        // PrestaShop 8.x and 9.x (product page v2). actionAdminControllerSetMedia
        // enqueues the tab's JS/CSS on the product edit controller.
        return parent::install()
            && $this->installDb()
            && $this->installConfiguration()
            && $this->registerHook('displayAdminProductsExtra')
            && $this->registerHook('actionAdminControllerSetMedia');
    }

    /**
     * Uninstall: drop local tables and delete Configuration keys.
     *
     * @return bool
     */
    public function uninstall()
    {
        return $this->uninstallConfiguration()
            && $this->uninstallDb()
            && parent::uninstall();
    }

    /**
     * Create local mapping tables (Thin-B — ID only, no state duplication).
     *
     * @return bool
     */
    private function installDb()
    {
        $prefix = _DB_PREFIX_;
        $engine = _MYSQL_ENGINE_;

        $sql = [];

        // ps_qamera_order — product -> Qamera session mapping.
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'qamera_order` (
            `id_qamera_order` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_product` INT(11) UNSIGNED NOT NULL,
            `qamera_order_id` VARCHAR(64) NOT NULL,
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id_qamera_order`),
            KEY `id_product` (`id_product`),
            KEY `qamera_order_id` (`qamera_order_id`)
        ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;';

        // ps_qamera_import — dedup of approved-session imports into the gallery.
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . $prefix . 'qamera_import` (
            `id_qamera_import` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_product` INT(11) UNSIGNED NOT NULL,
            `id_image` INT(11) UNSIGNED NOT NULL,
            `qamera_job_id` VARCHAR(64) NOT NULL,
            `output_sha` VARCHAR(64) NOT NULL,
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id_qamera_import`),
            KEY `id_product` (`id_product`),
            UNIQUE KEY `output_sha` (`output_sha`)
        ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;';

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Drop local mapping tables.
     *
     * @return bool
     */
    private function uninstallDb()
    {
        $prefix = _DB_PREFIX_;
        $ok = true;
        $ok = Db::getInstance()->execute('DROP TABLE IF EXISTS `' . $prefix . 'qamera_import`') && $ok;
        $ok = Db::getInstance()->execute('DROP TABLE IF EXISTS `' . $prefix . 'qamera_order`') && $ok;

        return $ok;
    }

    /**
     * Register default Configuration keys.
     *
     * @return bool
     */
    private function installConfiguration()
    {
        return Configuration::updateValue(self::KEY_API_KEY, '')
            && Configuration::updateValue(self::KEY_DEFAULT_PRESET_ID, '')
            && Configuration::updateValue(self::KEY_API_BASE, QameraApiClient::DEFAULT_API_BASE);
    }

    /**
     * Delete Configuration keys.
     *
     * @return bool
     */
    private function uninstallConfiguration()
    {
        return Configuration::deleteByName(self::KEY_API_KEY)
            && Configuration::deleteByName(self::KEY_DEFAULT_PRESET_ID)
            && Configuration::deleteByName(self::KEY_API_BASE);
    }

    /**
     * Build the API client from stored Configuration.
     *
     * @return QameraApiClient
     */
    private function getApiClient()
    {
        $apiKey = (string) Configuration::get(self::KEY_API_KEY);
        $apiBase = (string) Configuration::get(self::KEY_API_BASE);
        if ($apiBase === '') {
            $apiBase = QameraApiClient::DEFAULT_API_BASE;
        }

        return new QameraApiClient($apiKey, $apiBase);
    }

    /**
     * Settings page rendered in the module configuration screen.
     * Handles form submit, then renders: settings form (API key + base),
     * account status + credit balance, and a preset dropdown. Shows a
     * readable error when the key is missing or invalid.
     *
     * @return string
     */
    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitQameraSettings')) {
            $output .= $this->postProcess();
        }

        $output .= $this->renderAccountStatus();
        $output .= $this->renderSettingsForm();

        return $output;
    }

    /**
     * Persist submitted settings.
     *
     * @return string Rendered confirmation / error banner.
     */
    private function postProcess()
    {
        $apiKey = trim((string) Tools::getValue(self::KEY_API_KEY));
        $apiBase = trim((string) Tools::getValue(self::KEY_API_BASE));
        $presetId = trim((string) Tools::getValue(self::KEY_DEFAULT_PRESET_ID));

        if ($apiBase === '') {
            $apiBase = QameraApiClient::DEFAULT_API_BASE;
        }

        Configuration::updateValue(self::KEY_API_KEY, $apiKey);
        Configuration::updateValue(self::KEY_API_BASE, $apiBase);
        Configuration::updateValue(self::KEY_DEFAULT_PRESET_ID, $presetId);

        return $this->displayConfirmation($this->l('Ustawienia zapisane.'));
    }

    /**
     * Render account status + credit balance, or a readable error.
     *
     * @return string
     */
    private function renderAccountStatus()
    {
        $client = $this->getApiClient();

        if (!$client->hasKey()) {
            return $this->displayWarning(
                $this->l('Brak klucza API. Wklej klucz Qamera AI poniżej, aby zobaczyć status konta i salda kredytów.')
            );
        }

        try {
            $me = $client->get_me();
        } catch (QameraApiException $e) {
            return $this->displayError($this->l('Nie udało się pobrać statusu konta:') . ' ' . $e->getMessage());
        }

        // Account name: GET /me returns account_name; subscription_plan may be null.
        $account = isset($me['account_name']) ? (string) $me['account_name'] : (isset($me['account_id']) ? (string) $me['account_id'] : '—');
        $plan = (isset($me['subscription_plan']) && $me['subscription_plan'] !== null && $me['subscription_plan'] !== '')
            ? (string) $me['subscription_plan']
            : $this->l('brak (pay-as-you-go)');

        // Credit balance: GET /me returns credits_balance (fallbacks for shape drift).
        $credits = '—';
        if (isset($me['credits_balance'])) {
            $credits = (string) $me['credits_balance'];
        } elseif (isset($me['credit_balance'])) {
            $credits = (string) $me['credit_balance'];
        } elseif (isset($me['credits'])) {
            $credits = is_array($me['credits']) && isset($me['credits']['balance']) ? (string) $me['credits']['balance'] : (string) $me['credits'];
        }

        $html = '<div class="panel">'
            . '<h3><i class="icon icon-qrcode"></i> ' . $this->l('Konto Qamera AI') . '</h3>'
            . '<p><strong>' . $this->l('Konto:') . '</strong> ' . htmlspecialchars($account, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>' . $this->l('Plan:') . '</strong> ' . htmlspecialchars($plan, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>' . $this->l('Saldo kredytów:') . '</strong> ' . htmlspecialchars($credits, ENT_QUOTES, 'UTF-8') . '</p>'
            . '</div>';

        return $html;
    }

    /**
     * Build the settings form (API key, API base, default preset dropdown).
     *
     * @return string
     */
    private function renderSettingsForm()
    {
        $presetOptions = $this->getPresetOptions();

        $fields = [];
        $fields[] = [
            'type' => 'text',
            'label' => $this->l('Klucz API'),
            'name' => self::KEY_API_KEY,
            'desc' => $this->l('Format: mk_live_<keyId>.<secret>. Klucz przechowywany w konfiguracji sklepu.'),
            'required' => true,
        ];
        $fields[] = [
            'type' => 'text',
            'label' => $this->l('Adres API (base URL)'),
            'name' => self::KEY_API_BASE,
            'desc' => $this->l('Domyślnie produkcyjny https://qamera.ai. Zmień tylko dla środowiska dev/local.'),
        ];

        if (!empty($presetOptions['options'])) {
            $fields[] = [
                'type' => 'select',
                'label' => $this->l('Domyślny preset'),
                'name' => self::KEY_DEFAULT_PRESET_ID,
                'options' => [
                    'query' => $presetOptions['options'],
                    'id' => 'id',
                    'name' => 'name',
                ],
            ];
        } else {
            $fields[] = [
                'type' => 'text',
                'label' => $this->l('Domyślny preset (ID)'),
                'name' => self::KEY_DEFAULT_PRESET_ID,
                'desc' => $presetOptions['error'] !== ''
                    ? $presetOptions['error']
                    : $this->l('Lista presetów dostępna po zapisaniu poprawnego klucza API.'),
            ];
        }

        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Ustawienia Qamera AI'),
                    'icon' => 'icon-cogs',
                ],
                'input' => $fields,
                'submit' => [
                    'title' => $this->l('Zapisz'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->submit_action = 'submitQameraSettings';
        $helper->default_form_language = (int) $this->context->language->id;

        $helper->fields_value = [
            self::KEY_API_KEY => Configuration::get(self::KEY_API_KEY),
            self::KEY_API_BASE => Configuration::get(self::KEY_API_BASE),
            self::KEY_DEFAULT_PRESET_ID => Configuration::get(self::KEY_DEFAULT_PRESET_ID),
        ];

        return $helper->generateForm([$form]);
    }

    /**
     * Fetch preset options for the dropdown.
     *
     * @return array{options: array, error: string}
     */
    private function getPresetOptions()
    {
        $result = ['options' => [], 'error' => ''];
        $client = $this->getApiClient();

        if (!$client->hasKey()) {
            return $result;
        }

        try {
            $response = $client->get_presets();
        } catch (QameraApiException $e) {
            $result['error'] = $this->l('Nie udało się pobrać presetów:') . ' ' . $e->getMessage();

            return $result;
        }

        $list = [];
        if (isset($response['presets']) && is_array($response['presets'])) {
            $list = $response['presets'];
        } elseif (isset($response['data']) && is_array($response['data'])) {
            $list = $response['data'];
        } elseif (is_array($response)) {
            $list = $response;
        }

        foreach ($list as $preset) {
            if (!is_array($preset)) {
                continue;
            }
            $id = isset($preset['id']) ? (string) $preset['id'] : (isset($preset['preset_id']) ? (string) $preset['preset_id'] : '');
            if ($id === '') {
                continue;
            }
            $name = isset($preset['name']) ? (string) $preset['name'] : (isset($preset['title']) ? (string) $preset['title'] : $id);
            $result['options'][] = ['id' => $id, 'name' => $name];
        }

        return $result;
    }

    /**
     * Enqueue the product-tab assets on the product edit screen (PS8 + PS9).
     *
     * @param array $params
     * @return void
     */
    public function hookActionAdminControllerSetMedia($params)
    {
        $controller = Tools::getValue('controller');
        if ($controller !== 'AdminProducts' && $controller !== 'AdminProductsV2') {
            return;
        }

        $this->context->controller->addCSS($this->_path . 'views/css/qamera-admin.css');
        $this->context->controller->addJS($this->_path . 'views/js/qamera-product.js');
    }

    /**
     * Product-page tab — Core Flow generator UI.
     *
     * Left column: source photo upload/role + session settings (catalog
     * dropdowns). Right column: containers grouping each photo -> its packshots
     * -> their sessions, with roles/approval derived from API voting and
     * lineage (source_image_id, packshot_asset_id). State is read from the
     * Qamera API — never duplicated locally. Empty state and catalog/state
     * errors are surfaced explicitly (no white screen).
     *
     * @param array $params
     * @return string
     */
    public function hookDisplayAdminProductsExtra($params)
    {
        $idProduct = isset($params['id_product']) ? (int) $params['id_product'] : (int) Tools::getValue('id_product');
        $client = $this->getApiClient();

        $assign = [
            'qamera_id_product' => $idProduct,
            'qamera_external_ref' => $this->buildExternalRef($idProduct),
            'qamera_has_key' => $client->hasKey(),
            'qamera_default_preset_id' => (string) Configuration::get(self::KEY_DEFAULT_PRESET_ID),
            'qamera_models' => [],
            'qamera_sceneries' => [],
            'qamera_ai_models' => [],
            'qamera_presets' => [],
            'qamera_catalog_error' => '',
            'qamera_containers' => [],
            'qamera_standalone_packshots' => [],
            'qamera_is_empty' => true,
            'qamera_state_error' => '',
            'qamera_truncated' => false,
        ];

        if ($client->hasKey()) {
            $catalog = $this->loadCatalog($client);
            $view = $this->buildProductView($client, $idProduct);

            $assign['qamera_models'] = $catalog['models'];
            $assign['qamera_sceneries'] = $catalog['sceneries'];
            $assign['qamera_ai_models'] = $catalog['ai_models'];
            $assign['qamera_presets'] = $catalog['presets'];
            $assign['qamera_catalog_error'] = $catalog['error'];
            $assign['qamera_containers'] = $view['containers'];
            $assign['qamera_standalone_packshots'] = $view['standalone'];
            $assign['qamera_is_empty'] = $view['is_empty'];
            $assign['qamera_state_error'] = $view['error'];
            $assign['qamera_truncated'] = $view['truncated'];
        }

        $this->context->smarty->assign($assign);

        return $this->display(__FILE__, 'views/templates/hook/product-tab.tpl');
    }

    /**
     * Stable shop->Qamera mapping key for a product.
     * MVP is single-store; a shop{id_shop}- prefix is added when multistore lands.
     *
     * @param int $idProduct
     * @return string
     */
    private function buildExternalRef($idProduct)
    {
        return 'ps-' . (int) $idProduct;
    }

    /**
     * Load the slow-changing catalog (presets/models/sceneries/ai-models),
     * cached 15 min. Each call is error-tolerant: a failed endpoint yields an
     * empty list and a collected, human-readable error message.
     *
     * @param QameraApiClient $client
     * @return array{models: array, sceneries: array, ai_models: array, presets: array, error: string}
     */
    private function loadCatalog(QameraApiClient $client)
    {
        $out = ['models' => [], 'sceneries' => [], 'ai_models' => [], 'presets' => [], 'error' => ''];
        $errors = [];

        $defs = [
            'presets' => ['presets', function () use ($client) { return $client->get_presets(); }],
            'models' => ['models', function () use ($client) { return $client->get_models(); }],
            'sceneries' => ['sceneries', function () use ($client) { return $client->get_sceneries(); }],
            'ai_models' => ['ai_models', function () use ($client) { return $client->get_ai_models(); }],
        ];

        foreach ($defs as $field => $def) {
            list($listKey, $loader) = $def;
            try {
                $res = QameraCatalogCache::remember('catalog_' . $field, $loader);
            } catch (QameraApiException $e) {
                $errors[] = $e->getMessage();
                continue;
            }
            $out[$field] = $this->extractList($res, $listKey);
        }

        if (!empty($errors)) {
            $out['error'] = $this->l('Nie udało się pobrać katalogu Qamera AI:') . ' ' . implode(' ', array_unique($errors));
        }

        return $out;
    }

    /**
     * Normalize an API list response into [{id, name}, ...].
     * Accepts { <listKey>: [...] }, { data: [...] }, { items: [...] } or a bare list.
     *
     * @param mixed  $res
     * @param string $listKey
     * @return array
     */
    private function extractList($res, $listKey)
    {
        $list = [];
        if (is_array($res)) {
            if (isset($res[$listKey]) && is_array($res[$listKey])) {
                $list = $res[$listKey];
            } elseif (isset($res['data']) && is_array($res['data'])) {
                $list = $res['data'];
            } elseif (isset($res['items']) && is_array($res['items'])) {
                $list = $res['items'];
            } else {
                $list = $res;
            }
        }

        $out = [];
        foreach ($list as $item) {
            if (!is_array($item)) {
                continue;
            }
            // ai-models include video generators; sessions produce images only.
            if ($listKey === 'ai_models' && isset($item['output_type']) && $item['output_type'] !== 'image') {
                continue;
            }
            $id = isset($item['id']) ? (string) $item['id'] : (isset($item[$listKey . '_id']) ? (string) $item[$listKey . '_id'] : '');
            if ($id === '') {
                continue;
            }
            $name = isset($item['name'])
                ? (string) $item['name']
                : (isset($item['label']) ? (string) $item['label'] : (isset($item['title']) ? (string) $item['title'] : $id));
            $out[] = ['id' => $id, 'name' => $name];
        }

        return $out;
    }

    /**
     * Build the right-column view model from API state: containers grouping
     * photo -> packshots (by lineage source_image_id) -> sessions (by
     * packshot_asset_id). Roles and approval come from API voting only.
     *
     * A 404 from /products means the product is not yet known to Qamera — that
     * is the empty state, not an error. Any other failure is surfaced as a
     * readable state error.
     *
     * @param QameraApiClient $client
     * @param int             $idProduct
     * @return array{containers: array, standalone: array, is_empty: bool, error: string}
     */
    private function buildProductView(QameraApiClient $client, $idProduct)
    {
        $view = ['containers' => [], 'standalone' => [], 'is_empty' => true, 'error' => '', 'truncated' => false];

        $externalRef = $this->buildExternalRef($idProduct);

        try {
            $product = $client->get_product($externalRef);
        } catch (QameraApiException $e) {
            if ($e->getHttpStatus() === 404) {
                return $view; // not yet known to Qamera → empty state
            }
            $view['error'] = $e->getMessage();

            return $view;
        }

        // ProductDetailResponse: images[] (ProductImage), packshots[] (ProductPackshot).
        $images = $this->arr($product, 'images');
        $packshots = $this->arr($product, 'packshots');

        // Jobs (sessions + packshot generations) scoped to THIS product by
        // product_ref. jobsById resolves generated images by generated_by_job_id;
        // sessionsByPackshot groups photo_shoot results under their source packshot.
        $jobIndex = $this->loadJobsForProduct($client, $externalRef);
        $jobsById = $jobIndex['by_id'];
        $sessionsByPackshot = $jobIndex['sessions_by_packshot'];

        // Group packshots under their source photo via lineage (source_image_id).
        $packshotsByImage = [];
        $standalone = [];
        foreach ($packshots as $pk) {
            if (!is_array($pk)) {
                continue;
            }
            $node = $this->mapPackshot($pk, $sessionsByPackshot, $jobsById);
            $srcImageId = isset($pk['source_image_id']) && $pk['source_image_id'] !== null
                ? (string) $pk['source_image_id']
                : '';
            if ($srcImageId !== '') {
                $packshotsByImage[$srcImageId][] = $node;
            } else {
                $standalone[] = $node; // direct packshot (no source photo)
            }
        }

        $containers = [];
        foreach ($images as $img) {
            if (!is_array($img)) {
                continue;
            }
            $imgId = isset($img['id']) ? (string) $img['id'] : '';
            // ProductImage carries no display URL — resolve to the local
            // PrestaShop image via external_ref (ps-img-{id_image}).
            $localUrl = $this->resolveLocalImageUrl(
                isset($img['external_ref']) ? (string) $img['external_ref'] : '',
                $idProduct
            );
            $containers[] = [
                'photo' => [
                    'id' => $imgId,
                    'url' => $localUrl,
                    'thumb' => $localUrl,
                    'asset_id' => isset($img['asset_id']) ? (string) $img['asset_id'] : '',
                    'analysis_status' => isset($img['analysis_status']) ? (string) $img['analysis_status'] : '',
                ],
                'packshots' => isset($packshotsByImage[$imgId]) ? $packshotsByImage[$imgId] : [],
            ];
        }

        $view['containers'] = $containers;
        $view['standalone'] = $standalone;
        $view['is_empty'] = (count($images) === 0 && count($packshots) === 0);
        $view['truncated'] = !empty($product['images_truncated']) || !empty($product['packshots_truncated']);

        return $view;
    }

    /**
     * Map one ProductPackshot to the view node. Role/approval come from the
     * PackshotVoting enum (pending|accepted|rejected). The generated image has
     * no URL on the catalog row — it is resolved from the producing job
     * (generated_by_job_id) when available. Sessions are attached by asset_id.
     *
     * @param array $pk
     * @param array $sessionsByPackshot Map asset_id -> [session, ...]
     * @param array $jobsById           Map job_id -> job (for image resolution)
     * @return array
     */
    private function mapPackshot(array $pk, array $sessionsByPackshot, array $jobsById)
    {
        $assetId = isset($pk['asset_id']) ? (string) $pk['asset_id'] : '';
        $state = $this->votingState($pk); // PackshotVoting: pending|accepted|rejected

        $genJobId = isset($pk['generated_by_job_id']) && $pk['generated_by_job_id'] !== null
            ? (string) $pk['generated_by_job_id']
            : '';
        $url = '';
        if ($genJobId !== '' && isset($jobsById[$genJobId])) {
            $url = $this->firstOutputUrl($jobsById[$genJobId]);
        }

        return [
            'id' => isset($pk['id']) ? (string) $pk['id'] : $assetId,
            'asset_id' => $assetId,
            'url' => $url,
            'thumb' => $url,
            'voting' => $state,
            'approved' => ($state === 'accepted'),
            'pending' => ($state === 'pending'),
            'rejected' => ($state === 'rejected'),
            'generated' => ($genJobId !== ''),
            'sessions' => isset($sessionsByPackshot[$assetId]) ? $sessionsByPackshot[$assetId] : [],
        ];
    }

    /**
     * Fetch the account's jobs, scope to this product by product_ref, and build:
     *  - by_id: job_id -> job (resolves packshot/output images on re-render),
     *  - sessions_by_packshot: packshot_asset_id -> [session-result tile, ...].
     *
     * Each photo_shoot job is one generated image with its OWN voting (the
     * Voting enum lives on the job, not on JobOutput). Sessions are optional
     * context — a failed /jobs call yields empty maps so packshots still render.
     *
     * @param QameraApiClient $client
     * @param string          $externalRef Product external_ref to match product_ref.
     * @return array{by_id: array, sessions_by_packshot: array}
     */
    private function loadJobsForProduct(QameraApiClient $client, $externalRef)
    {
        $byId = [];
        $sessionsByPackshot = [];

        try {
            $res = $client->list_jobs(100);
        } catch (QameraApiException $e) {
            return ['by_id' => $byId, 'sessions_by_packshot' => $sessionsByPackshot];
        }

        $jobs = $this->arr($res, 'jobs');

        foreach ($jobs as $job) {
            if (!is_array($job)) {
                continue;
            }
            $jobId = isset($job['id']) ? (string) $job['id'] : '';
            if ($jobId !== '') {
                $byId[$jobId] = $job;
            }

            // Scope to this product.
            $productRef = isset($job['product_ref']) && $job['product_ref'] !== null ? (string) $job['product_ref'] : '';
            if ($externalRef !== '' && $productRef !== '' && $productRef !== $externalRef) {
                continue;
            }

            $type = isset($job['job_type']) ? (string) $job['job_type'] : '';
            if ($type !== '' && $type !== 'photo_shoot') {
                continue; // sessions only (packshot jobs render via generated_by_job_id)
            }
            $packAsset = isset($job['packshot_asset_id']) && $job['packshot_asset_id'] !== null
                ? (string) $job['packshot_asset_id']
                : '';
            if ($packAsset === '') {
                continue;
            }

            $state = $this->votingState($job); // Voting: accepted|rejected|null(->pending)
            $sessionsByPackshot[$packAsset][] = [
                'job_id' => $jobId,
                'status' => isset($job['status']) ? (string) $job['status'] : '',
                'voting' => $state,
                'approved' => ($state === 'accepted'),
                'rejected' => ($state === 'rejected'),
                'url' => $this->firstOutputUrl($job),
                'thumb' => $this->firstOutputUrl($job),
            ];
        }

        return ['by_id' => $byId, 'sessions_by_packshot' => $sessionsByPackshot];
    }

    /**
     * First signed download URL among a job's outputs (JobOutput.url), or ''.
     *
     * @param array $job
     * @return string
     */
    private function firstOutputUrl(array $job)
    {
        foreach ($this->arr($job, 'outputs') as $o) {
            if (is_array($o) && isset($o['url']) && is_string($o['url']) && $o['url'] !== '') {
                return $o['url'];
            }
        }

        return '';
    }

    /**
     * Resolve a Qamera catalog image's external_ref (ps-img-{id_image}) to a
     * local PrestaShop image URL. Catalog rows carry no display URL, and the
     * source photo is the merchant's own gallery image. Returns '' on no match.
     *
     * @param string $externalRef
     * @param int    $idProduct
     * @return string
     */
    private function resolveLocalImageUrl($externalRef, $idProduct)
    {
        if (!preg_match('/img-(\d+)$/', (string) $externalRef, $m)) {
            return '';
        }
        $idImage = (int) $m[1];
        if ($idImage <= 0) {
            return '';
        }

        try {
            $idLang = (int) $this->context->language->id;
            $product = new Product((int) $idProduct, false, $idLang);
            $rewrite = is_array($product->link_rewrite) ? reset($product->link_rewrite) : $product->link_rewrite;
            if (!$rewrite) {
                $rewrite = 'product';
            }

            return $this->context->link->getImageLink($rewrite, $idProduct . '-' . $idImage, 'home_default');
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * Normalize the API voting signal to one of accepted|pending|rejected.
     * Accepts a string, a {state|status} map, or boolean accepted/approved flags.
     *
     * @param mixed $node
     * @return string
     */
    private function votingState($node)
    {
        if (!is_array($node)) {
            return 'pending';
        }

        $v = isset($node['voting']) ? $node['voting'] : null;
        if (is_string($v) && $v !== '') {
            return $v;
        }
        if (is_array($v)) {
            if (isset($v['state']) && is_string($v['state']) && $v['state'] !== '') {
                return $v['state'];
            }
            if (isset($v['status']) && is_string($v['status']) && $v['status'] !== '') {
                return $v['status'];
            }
            if (!empty($v['accepted'])) {
                return 'accepted';
            }
            if (!empty($v['rejected'])) {
                return 'rejected';
            }

            return 'pending';
        }

        if (array_key_exists('accepted', $node)) {
            return $node['accepted'] ? 'accepted' : 'pending';
        }
        if (array_key_exists('approved', $node)) {
            return $node['approved'] ? 'accepted' : 'pending';
        }

        return 'pending';
    }

    /**
     * Safely read a sub-array by key.
     *
     * @param mixed  $node
     * @param string $key
     * @return array
     */
    private function arr($node, $key)
    {
        return (is_array($node) && isset($node[$key]) && is_array($node[$key])) ? $node[$key] : [];
    }
}
