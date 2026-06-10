<?php
/**
 * Dev probe for M2 read endpoints — learns real contract shapes before UI build.
 * NOT shipped with the module.
 *
 *   php tools/probe-m2.php
 *
 * Probes: GET /jobs (list), GET /jobs/{id} (if any), GET /products/{external_ref}.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$root = dirname(__DIR__);

$envPath = $root . '/.env';
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

if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '8.0.0-devprobe');
}
require $root . '/qameraai/classes/QameraApiClient.php';

$client = new QameraApiClient($apiKey, $apiBase);

function probe($client, $method, $path)
{
    echo "\n" . str_repeat('=', 70) . "\n$method $path\n" . str_repeat('-', 70) . "\n";
    try {
        $res = $client->request($method, $path);
        // Truncate huge values for readability, keep structure.
        echo "OK — top-level keys: " . implode(', ', array_keys($res)) . "\n";
        echo substr(json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 2500) . "\n";
    } catch (QameraApiException $e) {
        echo "FAIL [" . $e->getApiCode() . " / http " . $e->getHttpStatus() . "]: " . $e->getMessage() . "\n";
    }
}

probe($client, 'GET', '/jobs');
probe($client, 'GET', '/jobs?limit=3');
probe($client, 'GET', '/products/ps-1');
probe($client, 'GET', '/products/ps-999999');
