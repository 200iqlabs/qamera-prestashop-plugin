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
        return parent::install()
            && $this->installDb()
            && $this->installConfiguration()
            && $this->registerHook('displayAdminProductsExtra');
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
     * Product-page tab (Core Flow generator UI lands here in M2+).
     *
     * @param array $params
     * @return string
     */
    public function hookDisplayAdminProductsExtra($params)
    {
        $idProduct = isset($params['id_product']) ? (int) $params['id_product'] : (int) Tools::getValue('id_product');

        $this->context->smarty->assign([
            'qamera_id_product' => $idProduct,
            'qamera_has_key' => $this->getApiClient()->hasKey(),
        ]);

        return $this->display(__FILE__, 'views/templates/admin/product_tab.tpl');
    }
}
