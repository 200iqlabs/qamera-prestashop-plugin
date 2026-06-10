/*
 * Qamera AI for PrestaShop — product-tab admin script.
 *
 * Scope here (M2): wire up the tab UI, read the API-derived state already
 * rendered into the DOM, and expose the polling helper used by the generate
 * flows (packshot in M3, session in M4). No generation/import logic yet — the
 * AJAX endpoints land in later milestones. The render itself is server-side
 * (state comes from the Qamera API, not this script).
 */
(function () {
    'use strict';

    var POLL_INTERVAL_MS = 3000;
    var POLL_TIMEOUT_MS = 5 * 60 * 1000;

    function init() {
        var root = document.getElementById('qamera-product-tab');
        if (!root) {
            return;
        }

        var ctx = {
            idProduct: parseInt(root.getAttribute('data-id-product'), 10) || 0,
            externalRef: root.getAttribute('data-external-ref') || '',
            defaultPreset: root.getAttribute('data-default-preset') || ''
        };

        bindGeneratePackshot(root, ctx);
    }

    function bindGeneratePackshot(root, ctx) {
        var btn = root.querySelector('#qamera-generate-packshot');
        if (!btn) {
            return;
        }
        btn.addEventListener('click', function () {
            // M3 wires this to the AJAX upload+submit endpoint, then poll().
            // Intentionally a no-op stub in M2 so the UI is complete and inert.
            // eslint-disable-next-line no-console
            console.info('[Qamera] generate packshot — endpoint wired in M3', ctx.externalRef);
        });
    }

    /**
     * Poll GET /jobs/{id} until the job leaves pending/in_progress or timeout.
     * Used by the generate flows (M3/M4). onTick receives each decoded job.
     */
    function poll(fetchJob, onTick, onDone, onError) {
        var started = Date.now();

        function tick() {
            if (Date.now() - started > POLL_TIMEOUT_MS) {
                if (onError) { onError(new Error('poll_timeout')); }
                return;
            }
            fetchJob().then(function (job) {
                if (onTick) { onTick(job); }
                var status = job && job.status ? job.status : '';
                if (status === 'pending' || status === 'in_progress') {
                    setTimeout(tick, POLL_INTERVAL_MS);
                } else if (onDone) {
                    onDone(job);
                }
            }).catch(function (err) {
                if (onError) { onError(err); }
            });
        }

        tick();
    }

    // Expose the poller for later milestones without leaking the rest.
    window.QameraProduct = { poll: poll };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
