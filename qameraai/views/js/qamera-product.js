/*
 * Qamera AI for PrestaShop — product-tab admin script (Flow A).
 *
 * Flow A: product photo -> packshot. The browser never holds the API key — it
 * talks to the module's AdminQameraAjax controller, which proxies every Qamera
 * call server-side. Steps:
 *   1. POST the source file + session settings to generatePackshot (sync:
 *      upload + register + analysis wait + job submit), receive a job id.
 *   2. Poll getJob (GET /jobs/{id}) every 3s, up to 5min.
 *   3. On completion, render the packshot in place (no page reload) with
 *      accept / reject buttons wired to acceptJob / rejectJob.
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
        // Guard against double-init: the script is inlined in the hook output
        // and may also be enqueued via actionAdminControllerSetMedia on legacy
        // controllers — bind listeners exactly once.
        if (root.getAttribute('data-qamera-init') === '1') {
            return;
        }
        root.setAttribute('data-qamera-init', '1');

        var ctx = {
            idProduct: parseInt(root.getAttribute('data-id-product'), 10) || 0,
            externalRef: root.getAttribute('data-external-ref') || '',
            defaultPreset: root.getAttribute('data-default-preset') || '',
            ajaxUrl: root.getAttribute('data-ajax-url') || ''
        };

        bindGeneratePackshot(root, ctx);
        bindVoteButtons(root, ctx);
    }

    /** Append the &action/&ajax params PrestaShop's controller dispatch needs. */
    function actionUrl(ctx, action) {
        var sep = ctx.ajaxUrl.indexOf('?') === -1 ? '?' : '&';
        return ctx.ajaxUrl + sep + 'ajax=1&action=' + encodeURIComponent(action);
    }

    function setStatus(root, message, kind) {
        var el = root.querySelector('#qamera-generate-status');
        if (!el) {
            return;
        }
        el.textContent = message || '';
        el.className = 'qamera-status' + (kind ? ' qamera-status--' + kind : '');
    }

    function val(root, sel) {
        var el = root.querySelector(sel);
        return el ? el.value : '';
    }

    function clampCount(raw) {
        var n = parseInt(raw, 10);
        if (isNaN(n) || n < 1) { n = 1; }
        if (n > 10) { n = 10; }
        return n;
    }

    function bindGeneratePackshot(root, ctx) {
        var btn = root.querySelector('#qamera-generate-packshot');
        if (!btn) {
            return;
        }
        btn.addEventListener('click', function () {
            var fileInput = root.querySelector('#qamera-source-file');
            if (!fileInput || !fileInput.files || !fileInput.files.length) {
                setStatus(root, 'Wybierz zdjęcie produktu.', 'error');
                return;
            }

            var fd = new FormData();
            fd.append('id_product', String(ctx.idProduct));
            fd.append('count', String(clampCount(val(root, '#qamera-count'))));
            fd.append('ai_model', val(root, '#qamera-ai-model'));
            fd.append('preset_id', val(root, '#qamera-preset'));
            fd.append('model_id', val(root, '#qamera-model'));
            fd.append('scenery_id', val(root, '#qamera-scenery'));
            fd.append('aspect_ratio', val(root, '#qamera-aspect'));
            fd.append('suggestions', val(root, '#qamera-context'));
            fd.append('file', fileInput.files[0]);

            btn.disabled = true;
            setStatus(root, 'Wysyłanie zdjęcia i zlecanie generacji…', 'busy');

            postForm(actionUrl(ctx, 'generatePackshot'), fd).then(function (res) {
                if (!res || !res.ok) {
                    setStatus(root, (res && res.error) ? res.error : 'Nie udało się rozpocząć generacji.', 'error');
                    btn.disabled = false;
                    return;
                }
                setStatus(root, 'Generowanie packshotu… (to może potrwać do kilku minut)', 'busy');
                pollJob(root, ctx, res.job_id, btn);
            }).catch(function () {
                setStatus(root, 'Błąd sieci podczas wysyłki. Spróbuj ponownie.', 'error');
                btn.disabled = false;
            });
        });
    }

    /** Poll the getJob proxy until the job is terminal or the timeout elapses. */
    function pollJob(root, ctx, jobId, btn) {
        var fetchJob = function () {
            return postForm(actionUrl(ctx, 'getJob'), formData({ job_id: jobId }));
        };

        poll(fetchJob, null, function (job) {
            btn.disabled = false;
            if (!job || !job.ok) {
                setStatus(root, (job && job.error) ? job.error : 'Nie udało się odczytać statusu zadania.', 'error');
                return;
            }
            if (job.status === 'completed') {
                setStatus(root, 'Packshot gotowy.', 'success');
                renderPackshot(root, ctx, job);
            } else if (job.status === 'failed' || job.status === 'expired') {
                setStatus(root, job.error ? ('Generacja nie powiodła się: ' + job.error) : 'Generacja nie powiodła się.', 'error');
            } else if (job.status === 'cancelled') {
                setStatus(root, 'Generacja anulowana.', 'error');
            } else {
                setStatus(root, 'Zadanie zakończone w stanie: ' + (job.status || '—'), 'error');
            }
        }, function (err) {
            btn.disabled = false;
            if (err && err.message === 'poll_timeout') {
                setStatus(root, 'Przekroczono limit oczekiwania (5 min). Odśwież stronę, aby sprawdzić wynik.', 'error');
            } else if (err && err.payload && err.payload.error) {
                setStatus(root, err.payload.error, 'error');
            } else {
                setStatus(root, 'Błąd podczas sprawdzania statusu.', 'error');
            }
        });
    }

    /** Render a freshly completed packshot into the right column. */
    function renderPackshot(root, ctx, job) {
        var wrap = root.querySelector('#qamera-new-packshots');
        var list = root.querySelector('#qamera-new-packshots-list');
        if (!wrap || !list) {
            return;
        }
        wrap.hidden = false;

        var fig = document.createElement('figure');
        fig.className = 'qamera-packshot qamera-vote--pending';
        fig.setAttribute('data-job-id', job.job_id || '');
        fig.setAttribute('data-voting', 'pending');

        var roleBadge = document.createElement('span');
        roleBadge.className = 'qamera-badge qamera-badge--role';
        roleBadge.textContent = 'Packshot';
        fig.appendChild(roleBadge);

        if (job.url) {
            var img = document.createElement('img');
            img.src = job.url;
            img.alt = '';
            img.loading = 'lazy';
            fig.appendChild(img);
        } else {
            var ph = document.createElement('div');
            ph.className = 'qamera-thumb qamera-thumb--placeholder';
            fig.appendChild(ph);
        }

        var actions = document.createElement('div');
        actions.className = 'qamera-packshot__actions';

        var acceptBtn = document.createElement('button');
        acceptBtn.type = 'button';
        acceptBtn.className = 'qamera-btn qamera-btn--accept';
        acceptBtn.setAttribute('data-vote', 'accept');
        acceptBtn.setAttribute('data-job-id', job.job_id || '');
        acceptBtn.textContent = 'Zatwierdź';

        var rejectBtn = document.createElement('button');
        rejectBtn.type = 'button';
        rejectBtn.className = 'qamera-btn qamera-btn--reject';
        rejectBtn.setAttribute('data-vote', 'reject');
        rejectBtn.setAttribute('data-job-id', job.job_id || '');
        rejectBtn.textContent = 'Odrzuć';

        actions.appendChild(acceptBtn);
        actions.appendChild(rejectBtn);
        fig.appendChild(actions);

        list.appendChild(fig);
    }

    /** Event-delegated accept/reject for both server-rendered and fresh cards. */
    function bindVoteButtons(root, ctx) {
        root.addEventListener('click', function (e) {
            var target = e.target;
            if (!target || !target.getAttribute) {
                return;
            }
            var vote = target.getAttribute('data-vote');
            if (vote !== 'accept' && vote !== 'reject') {
                return;
            }
            var jobId = target.getAttribute('data-job-id');
            if (!jobId) {
                return;
            }

            var fig = target.closest ? target.closest('.qamera-packshot, .qamera-output') : null;
            target.disabled = true;

            var action = vote === 'accept' ? 'acceptJob' : 'rejectJob';
            postForm(actionUrl(ctx, action), formData({ job_id: jobId })).then(function (res) {
                target.disabled = false;
                if (!res || !res.ok) {
                    setStatus(root, (res && res.error) ? res.error : 'Nie udało się zapisać oceny.', 'error');
                    return;
                }
                if (fig) {
                    fig.className = fig.className.replace(/qamera-vote--\w+/, 'qamera-vote--' + res.voting);
                    fig.setAttribute('data-voting', res.voting);
                }
                setStatus(root, vote === 'accept' ? 'Packshot zatwierdzony.' : 'Packshot odrzucony.', 'success');
            }).catch(function () {
                target.disabled = false;
                setStatus(root, 'Błąd sieci podczas zapisu oceny.', 'error');
            });
        });
    }

    /* ── transport helpers ─────────────────────────────────────────────── */

    function formData(obj) {
        var fd = new FormData();
        Object.keys(obj).forEach(function (k) {
            fd.append(k, obj[k]);
        });
        return fd;
    }

    /** POST FormData, resolve parsed JSON. Rejects with {payload} on API error. */
    function postForm(url, fd) {
        return fetch(url, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (resp) {
            return resp.text().then(function (text) {
                var data;
                try {
                    data = text ? JSON.parse(text) : {};
                } catch (e) {
                    throw new Error('bad_json');
                }
                return data;
            });
        });
    }

    /**
     * Poll fetchJob() until status leaves pending/in_progress/retry_pending or
     * the 5-minute timeout. interval 3s. onTick(job) each cycle; onDone(job) on
     * terminal; onError(err) on timeout/transport.
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
                if (status === 'pending' || status === 'in_progress' || status === 'retry_pending') {
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
