<?php
/**
 * Scope audit for the Qamera plugin API (dev tool — NOT shipped).
 *
 * Answers one question and asserts the answer instead of leaving it to the eye:
 * does `GET /products/{external_ref}` return ONLY the images and packshots of
 * the requested product, or can material belonging to another product on the
 * same installation surface under it?
 *
 * Three checks per run:
 *   1. every embedded image/packshot carries `product_id` == the product's own id;
 *   2. no image id, packshot id or asset_id appears under two different products;
 *   3. `GET /packshots` narrows to one product when `product_ref` is supplied.
 *
 * Exit code 0 = correctly scoped, 1 = at least one violation (or a call failed).
 *
 * Reads QAMERA_API_KEY / QAMERA_API_BASE from the repo `.env`. Lives under
 * tools/, which the distributable ZIP never touches: tools/build-zip.php walks
 * the `qameraai/` module directory only.
 *
 *   php tools/probe-ref.php              # audit every product on the account
 *   php tools/probe-ref.php ps-20 ps-19  # audit only these refs
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

$root = dirname(__DIR__);
$env = [];
foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') { continue; }
    $pos = strpos($line, '=');
    if ($pos === false) { continue; }
    $env[trim(substr($line, 0, $pos))] = trim(substr($line, $pos + 1));
}
$apiKey = isset($env['QAMERA_API_KEY']) ? $env['QAMERA_API_KEY'] : '';
$apiBase = (isset($env['QAMERA_API_BASE']) && $env['QAMERA_API_BASE'] !== '') ? $env['QAMERA_API_BASE'] : 'https://qamera.ai';

if (!defined('_PS_VERSION_')) { define('_PS_VERSION_', '9.0.0-devprobe'); }
require $root . '/qameraai/classes/QameraApiClient.php';
$client = new QameraApiClient($apiKey, $apiBase);

$violations = [];

// Refs to audit: the CLI list, or the whole account catalogue.
$refs = array_slice($argv, 1);
if (!$refs) {
    echo "=== GET /products?limit=100 ===\n";
    try {
        $list = $client->request('GET', '/products?limit=100');
    } catch (Exception $e) {
        fwrite(STDERR, 'cannot list products: ' . $e->getMessage() . "\n");
        exit(1);
    }
    $items = isset($list['items']) && is_array($list['items']) ? $list['items'] : [];
    foreach ($items as $p) {
        if (isset($p['external_ref']) && $p['external_ref'] !== null) {
            $refs[] = (string) $p['external_ref'];
        }
    }
    printf("  %d product(s): %s\n\n", count($refs), implode(', ', $refs));
}

$seenImage = [];
$seenPack = [];
$seenAsset = [];

foreach ($refs as $ref) {
    echo "================ GET /products/$ref ================\n";
    try {
        $p = $client->get_product($ref);
    } catch (Exception $e) {
        printf("  ERROR: %s :: %s (HTTP %d)\n", get_class($e), $e->getMessage(),
            method_exists($e, 'getHttpStatus') ? $e->getHttpStatus() : 0);
        $violations[] = "GET /products/$ref failed";
        continue;
    }

    $pid = isset($p['id']) ? (string) $p['id'] : '';
    $images = isset($p['images']) && is_array($p['images']) ? $p['images'] : [];
    $packs = isset($p['packshots']) && is_array($p['packshots']) ? $p['packshots'] : [];
    printf("  product.id=%s  images=%d  packshots=%d  truncated=%s/%s\n", $pid, count($images), count($packs),
        !empty($p['images_truncated']) ? 'y' : 'n', !empty($p['packshots_truncated']) ? 'y' : 'n');

    foreach ($images as $i => $img) {
        $own = isset($img['product_id']) ? (string) $img['product_id'] : '';
        $ok = ($own === $pid);
        printf("    image[%d]    product_id=%s %s ref=%s asset=%s\n", $i, $own !== '' ? $own : '(none)',
            $ok ? 'OK' : '*** FOREIGN ***',
            isset($img['external_ref']) ? $img['external_ref'] : '(none)',
            isset($img['asset_id']) ? $img['asset_id'] : '(none)');
        if (!$ok) {
            $violations[] = 'image ' . (isset($img['id']) ? $img['id'] : '?') . " under $ref carries product_id $own";
        }
        if (isset($img['id'])) {
            if (isset($seenImage[$img['id']]) && $seenImage[$img['id']] !== $ref) {
                $violations[] = "image {$img['id']} appears under BOTH {$seenImage[$img['id']]} and $ref";
            }
            $seenImage[$img['id']] = $ref;
        }
        if (isset($img['asset_id'])) {
            $k = 'img:' . $img['asset_id'];
            if (isset($seenAsset[$k]) && $seenAsset[$k] !== $ref) {
                $violations[] = "image asset {$img['asset_id']} shared by {$seenAsset[$k]} and $ref";
            }
            $seenAsset[$k] = $ref;
        }
    }

    foreach ($packs as $i => $pk) {
        $own = isset($pk['product_id']) ? (string) $pk['product_id'] : '';
        $ok = ($own === $pid);
        printf("    packshot[%d] product_id=%s %s ref=%s asset=%s voting=%s src_img=%s job=%s\n", $i,
            $own !== '' ? $own : '(none)', $ok ? 'OK' : '*** FOREIGN ***',
            isset($pk['external_ref']) ? $pk['external_ref'] : '(none)',
            isset($pk['asset_id']) ? $pk['asset_id'] : '(none)',
            isset($pk['voting']) ? $pk['voting'] : '(none)',
            isset($pk['source_image_id']) ? $pk['source_image_id'] : '(none)',
            isset($pk['generated_by_job_id']) ? $pk['generated_by_job_id'] : '(none)');
        if (!$ok) {
            $violations[] = 'packshot ' . (isset($pk['id']) ? $pk['id'] : '?') . " under $ref carries product_id $own";
        }
        if (isset($pk['id'])) {
            if (isset($seenPack[$pk['id']]) && $seenPack[$pk['id']] !== $ref) {
                $violations[] = "packshot {$pk['id']} appears under BOTH {$seenPack[$pk['id']]} and $ref";
            }
            $seenPack[$pk['id']] = $ref;
        }
        if (isset($pk['asset_id'])) {
            $k = 'pk:' . $pk['asset_id'];
            if (isset($seenAsset[$k]) && $seenAsset[$k] !== $ref) {
                $violations[] = "packshot asset {$pk['asset_id']} shared by {$seenAsset[$k]} and $ref";
            }
            $seenAsset[$k] = $ref;
        }
    }
}

// The standalone listing is installation-scoped by design; with product_ref it
// must narrow to that product. The module does not call it — checked anyway so
// the answer covers the whole catalogue read surface, not just the endpoint in use.
if ($refs) {
    $probeRef = $refs[0];
    echo "\n================ GET /packshots?product_ref=$probeRef ================\n";
    try {
        $one = $client->request('GET', '/packshots?limit=100&product_ref=' . rawurlencode($probeRef));
        $items = isset($one['items']) && is_array($one['items']) ? $one['items'] : [];
        $wrong = 0;
        foreach ($items as $it) {
            $id = isset($it['id']) ? $it['id'] : '';
            $owner = ($id !== '' && isset($seenPack[$id])) ? $seenPack[$id] : null;
            if ($owner !== null && $owner !== $probeRef) {
                $violations[] = "GET /packshots?product_ref=$probeRef returned packshot $id of $owner";
                ++$wrong;
            }
        }
        printf("  %d item(s), %d belonging to another product\n", count($items), $wrong);
    } catch (Exception $e) {
        printf("  ERROR: %s\n", $e->getMessage());
        $violations[] = 'GET /packshots?product_ref failed';
    }
}

printf("\n=== VIOLATIONS: %d ===\n", count($violations));
foreach ($violations as $v) {
    echo "  !! $v\n";
}
exit($violations ? 1 : 0);
