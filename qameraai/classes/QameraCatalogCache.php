<?php
/**
 * Qamera AI for PrestaShop — catalog cache.
 *
 * Slow-changing catalog data (presets, models, sceneries, ai-models) is fetched
 * once and cached for 15 minutes so every product-page render does not hit the
 * API. Backed by ps_configuration (no extra dependency, survives across
 * requests). Failures are never cached — the loader re-runs next render.
 *
 * Source of truth for generation STATE stays the API; this caches only the
 * static catalog used to build the session-settings dropdowns.
 *
 * PHP 7.4 compatible (PrestaShop 8.x / 9.x).
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class QameraCatalogCache
{
    /** @var int Default TTL in seconds (15 minutes). */
    const TTL = 900;

    /** @var string Configuration key prefix. */
    const PREFIX = 'QAMERA_CACHE_';

    /**
     * Return cached value for $key if fresh, else run $loader, cache, return.
     * The loader may throw (e.g. QameraApiException) — the throw propagates and
     * nothing is cached, so the next call retries.
     *
     * @param string   $key    Logical cache key (namespaced internally).
     * @param callable $loader Produces the value to cache when stale/missing.
     * @param int      $ttl    Seconds the value stays fresh.
     * @return mixed Cached or freshly loaded value.
     */
    public static function remember($key, callable $loader, $ttl = self::TTL)
    {
        $cfgKey = self::cfgKey($key);

        $raw = Configuration::get($cfgKey);
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)
                && array_key_exists('e', $decoded)
                && array_key_exists('d', $decoded)
                && (int) $decoded['e'] > time()
            ) {
                return $decoded['d'];
            }
        }

        $data = $loader();

        Configuration::updateValue($cfgKey, json_encode([
            'e' => time() + (int) $ttl,
            'd' => $data,
        ]));

        return $data;
    }

    /**
     * Drop a cached entry (e.g. after a settings change invalidates the key).
     *
     * @param string $key
     * @return void
     */
    public static function forget($key)
    {
        Configuration::deleteByName(self::cfgKey($key));
    }

    /**
     * Normalize a logical key into a safe, bounded Configuration name.
     *
     * @param string $key
     * @return string
     */
    private static function cfgKey($key)
    {
        $safe = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '_', (string) $key));

        return substr(self::PREFIX . $safe, 0, 254);
    }
}
