<?php
/**
 * Dev smoke test for the Qamera API client — NOT shipped with the module.
 *
 * Loads .env, reuses qameraai/classes/QameraApiClient.php, and calls
 * get_me() + get_presets() against the live API. Run:
 *
 *   php tools/smoke-api.php
 *
 * Reads QAMERA_API_KEY / QAMERA_API_BASE from .env at repo root.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$root = dirname(__DIR__);

// --- load .env (split on first '=' only; base64 keys contain '=') -----------
$envPath = $root . '/.env';
if (!is_file($envPath)) {
    fwrite(STDERR, "Brak .env w $root\n");
    exit(1);
}
$env = [];
foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') {
        continue;
    }
    $pos = strpos($line, '=');
    if ($pos === false) {
        continue;
    }
    $env[trim(substr($line, 0, $pos))] = trim(substr($line, $pos + 1));
}

$apiKey = isset($env['QAMERA_API_KEY']) ? $env['QAMERA_API_KEY'] : '';
$apiBase = isset($env['QAMERA_API_BASE']) && $env['QAMERA_API_BASE'] !== '' ? $env['QAMERA_API_BASE'] : 'https://qamera.ai';

if ($apiKey === '') {
    fwrite(STDERR, "QAMERA_API_KEY pusty w .env\n");
    exit(1);
}

// --- load the real module client (guard the PrestaShop constant check) ------
if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '8.0.0-devsmoke');
}
require $root . '/qameraai/classes/QameraApiClient.php';

$client = new QameraApiClient($apiKey, $apiBase);

echo "Base: $apiBase\n";
echo "Key:  " . substr($apiKey, 0, 16) . "...\n";
echo str_repeat('-', 60) . "\n";

// --- get_me() ---------------------------------------------------------------
echo "GET /api/v1/plugin/me\n";
try {
    $me = $client->get_me();
    echo "  OK\n";
    echo "  " . json_encode($me, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
} catch (QameraApiException $e) {
    echo "  FAIL [" . $e->getApiCode() . " / http " . $e->getHttpStatus() . "]: " . $e->getMessage() . "\n";
}

echo str_repeat('-', 60) . "\n";

// --- get_presets() ----------------------------------------------------------
echo "GET /api/v1/plugin/presets\n";
try {
    $presets = $client->get_presets();
    echo "  OK\n";
    echo "  " . json_encode($presets, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
} catch (QameraApiException $e) {
    echo "  FAIL [" . $e->getApiCode() . " / http " . $e->getHttpStatus() . "]: " . $e->getMessage() . "\n";
}
