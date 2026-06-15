<?php
/**
 * Build the distributable module ZIP (NOT shipped). Uses ZipArchive so entries
 * use forward-slash separators — required for extraction on Linux PrestaShop
 * hosts (Windows Compress-Archive writes backslashes, which break the install).
 *
 * Excludes per-install / dev artifacts: config_*.xml (PS-generated description
 * cache), .env, tools/cacert.pem. Run: php tools/build-zip.php
 */

$root = realpath(__DIR__ . '/..');
$src = $root . '/qameraai';
$out = $root . '/qameraai.zip';

if (!is_dir($src)) {
    fwrite(STDERR, "module dir not found: $src\n");
    exit(1);
}
if (file_exists($out)) {
    unlink($out);
}

$zip = new ZipArchive();
if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "cannot create $out\n");
    exit(1);
}

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$added = 0;
$skipped = [];
foreach ($it as $file) {
    $abs = $file->getPathname();
    // Entry path relative to repo root, forward slashes, top folder = qameraai/.
    $rel = str_replace('\\', '/', substr($abs, strlen($root) + 1));

    $base = basename($abs);
    if (preg_match('/^config_.*\.xml$/', $base) || $base === '.env' || $base === 'cacert.pem') {
        $skipped[] = $rel;
        continue;
    }

    if ($file->isDir()) {
        $zip->addEmptyDir($rel);
    } else {
        $zip->addFile($abs, $rel);
        $added++;
    }
}

$zip->close();

echo "built: $out ($added files)\n";
if ($skipped) {
    echo "excluded: " . implode(', ', $skipped) . "\n";
}
