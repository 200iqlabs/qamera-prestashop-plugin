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

        bindActions(root, ctx);
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

    // Source-image analysis can outlast a single sync request. Retry the
    // submit a few times — the server dedups by sha256, so a retry re-checks
    // analysis and submits without re-uploading.
    var SUBMIT_RETRY_MAX = 12;
    var SUBMIT_RETRY_DELAY_MS = 5000;

    /** Single delegated click handler for every tab action. */
    function bindActions(root, ctx) {
        root.addEventListener('click', function (e) {
            var target = closestAction(e.target);
            if (!target) {
                return;
            }
            var action = target.getAttribute('data-action');
            var vote = target.getAttribute('data-vote');
            if (action === 'register-image') {
                handleRegister(root, ctx, target, false);
            } else if (action === 'register-packshot-direct') {
                handleRegister(root, ctx, target, true);
            } else if (action === 'generate-packshot') {
                handleGeneratePackshot(root, ctx, target);
            } else if (action === 'generate-session') {
                handleGenerateSession(root, ctx, target);
            } else if (action === 'accept-session') {
                handleAcceptSession(root, ctx, target);
            } else if (action === 'delete-packshot') {
                handleDelete(root, ctx, target);
            } else if (vote === 'accept' || vote === 'reject') {
                handleVote(root, ctx, target, vote);
            }
        });
    }

    /** Walk up to the nearest element carrying a data-action / data-vote. */
    function closestAction(el) {
        while (el && el.getAttribute) {
            if (el.getAttribute('data-action') || el.getAttribute('data-vote')) {
                return el;
            }
            el = el.parentElement;
        }
        return null;
    }

    /** Register a gallery image as source image or directly as packshot. */
    function handleRegister(root, ctx, target, asPackshot) {
        var idImage = target.getAttribute('data-id-image');
        if (!idImage) {
            return;
        }
        // Grab the source thumbnail now — reused for the platform tile.
        var galItem = root.querySelector('.qamera-gallery__item[data-id-image="' + idImage + '"]');
        var galImg = galItem ? galItem.querySelector('img') : null;
        var imgUrl = galImg ? galImg.getAttribute('src') : '';

        target.disabled = true;
        setStatus(root, asPackshot ? 'Dodawanie jako packshot…' : 'Dodawanie jako zdjęcie produktu…', 'busy');

        var action = asPackshot ? 'registerPackshot' : 'registerImage';
        postForm(actionUrl(ctx, action), formData({ id_product: ctx.idProduct, id_image: idImage })).then(function (res) {
            if (!res || !res.ok) {
                target.disabled = false;
                setStatus(root, (res && res.error) ? res.error : 'Nie udało się dodać zdjęcia.', 'error');
                return;
            }
            updateGalleryItem(root, idImage, asPackshot);
            // Live injection: the image now lives in section 3 (platform). A
            // source image gets a "Generuj packshot" button; a direct packshot
            // shows 3a + 3b with no generation (no packshot-from-packshot).
            injectPlatformTile(root, {
                idImage: idImage,
                imgUrl: imgUrl,
                asPackshot: asPackshot,
                assetId: res.asset_id || '',
                ref: res.external_ref || ''
            });
            setStatus(
                root,
                asPackshot ? 'Dodano jako packshot.' : 'Dodano jako zdjęcie produktu — możesz wygenerować packshot poniżej.',
                'success'
            );
        }).catch(function () {
            target.disabled = false;
            setStatus(root, 'Błąd sieci podczas dodawania.', 'error');
        });
    }

    /** Reflect a fresh registration on the gallery tile (badge, no actions). */
    function updateGalleryItem(root, idImage, asPackshot) {
        var item = root.querySelector('.qamera-gallery__item[data-id-image="' + idImage + '"]');
        if (!item) {
            return;
        }
        var badges = item.querySelector('.qamera-gallery__badges');
        var actions = item.querySelector('.qamera-gallery__actions');

        item.setAttribute(asPackshot ? 'data-as-packshot' : 'data-as-image', '1');
        if (badges) {
            var cls = asPackshot ? 'qamera-badge--role' : 'qamera-badge--accepted';
            if (!badges.querySelector('.' + cls)) {
                var b = document.createElement('span');
                b.className = 'qamera-badge ' + cls;
                b.textContent = asPackshot ? 'packshot' : 'źródło';
                badges.appendChild(b);
            }
        }
        // No generation here — that happens on the platform tile (section 3).
        if (actions) { actions.innerHTML = ''; }
    }

    /** Find or create the section-3 (platform) container for a source image. */
    function injectPlatformTile(root, o) {
        var listWrap = root.querySelector('#qamera-platform-list');
        if (!listWrap) {
            return;
        }
        var empty = root.querySelector('#qamera-empty');
        if (empty) { empty.hidden = true; }

        var container = listWrap.querySelector('.qamera-container[data-id-image="' + o.idImage + '"]');
        if (!container) {
            container = document.createElement('section');
            container.className = 'qamera-container';
            container.setAttribute('data-id-image', o.idImage);

            var photo = document.createElement('div');
            photo.className = 'qamera-container__photo';
            var role = document.createElement('span');
            role.className = 'qamera-badge qamera-badge--role';
            role.textContent = 'Zdjęcie';
            photo.appendChild(role);
            if (o.imgUrl) {
                var im = document.createElement('img');
                im.src = o.imgUrl; im.alt = ''; im.loading = 'lazy';
                photo.appendChild(im);
            }
            // A direct packshot's source must not be re-generated from.
            if (!o.asPackshot) {
                var gen = document.createElement('button');
                gen.type = 'button';
                gen.className = 'qamera-btn qamera-btn--primary';
                gen.setAttribute('data-action', 'generate-packshot');
                gen.setAttribute('data-id-image', o.idImage);
                gen.textContent = 'Generuj packshot';
                photo.appendChild(gen);
            }
            container.appendChild(photo);

            var packs = document.createElement('div');
            packs.className = 'qamera-container__packshots';
            container.appendChild(packs);

            listWrap.insertBefore(container, listWrap.firstChild);
        }

        if (o.asPackshot) {
            var packsArea = container.querySelector('.qamera-container__packshots');
            clearMuted(packsArea);
            packsArea.appendChild(makeDirectPackshotTile(o.assetId, o.ref, o.imgUrl));
        }
    }

    /** Build an auto-accepted direct-packshot tile (3b): session + delete only. */
    function makeDirectPackshotTile(assetId, ref, imgUrl) {
        var fig = document.createElement('figure');
        fig.className = 'qamera-packshot qamera-vote--accepted';
        fig.setAttribute('data-asset-id', assetId || '');
        fig.setAttribute('data-packshot-ref', ref || '');
        fig.setAttribute('data-voting', 'accepted');

        var role = document.createElement('span');
        role.className = 'qamera-badge qamera-badge--role';
        role.textContent = 'Packshot';
        fig.appendChild(role);
        var acc = document.createElement('span');
        acc.className = 'qamera-badge qamera-badge--accepted';
        acc.textContent = 'Zatwierdzony';
        fig.appendChild(acc);

        if (imgUrl) {
            var im = document.createElement('img');
            im.src = imgUrl; im.alt = ''; im.loading = 'lazy';
            fig.appendChild(im);
        } else {
            var box = document.createElement('div');
            box.className = 'qamera-thumb qamera-thumb--placeholder';
            fig.appendChild(box);
        }

        var actions = document.createElement('div');
        actions.className = 'qamera-packshot__actions';
        var ses = document.createElement('button');
        ses.type = 'button';
        ses.className = 'qamera-btn qamera-btn--primary';
        ses.setAttribute('data-action', 'generate-session');
        ses.setAttribute('data-packshot-asset-id', assetId || '');
        ses.textContent = 'Generuj sesję';
        var del = document.createElement('button');
        del.type = 'button';
        del.className = 'qamera-btn qamera-btn--delete';
        del.setAttribute('data-action', 'delete-packshot');
        del.setAttribute('data-packshot-ref', ref || '');
        del.textContent = 'Usuń';
        actions.appendChild(ses);
        actions.appendChild(del);
        fig.appendChild(actions);
        return fig;
    }

    /** Drop a "Brak packshotów…" placeholder line from a packshots container. */
    function clearMuted(area) {
        if (!area) { return; }
        var m = area.querySelector('.qamera-muted');
        if (m && m.parentNode) { m.parentNode.removeChild(m); }
    }

    /** Generate a packshot from a registered source image (with placeholder). */
    function handleGeneratePackshot(root, ctx, target) {
        var idImage = target.getAttribute('data-id-image');
        if (!idImage) {
            return;
        }
        target.disabled = true;
        // A packshot carries no style params: just the source. Always 1 image,
        // AI model from module Configuration (server-side). Style/count/model
        // belong to the session, not the packshot.
        var fd = formData({
            id_product: ctx.idProduct,
            id_image: idImage
        });

        // Placeholder appears immediately, inside this image's container (3b).
        var container = target.closest ? target.closest('.qamera-container') : null;
        var list = container ? container.querySelector('.qamera-container__packshots') : null;
        clearMuted(list);
        var ph = addPlaceholderTile(list);
        submitGenerate(root, ctx, target, fd, 0, ph);
    }

    function submitGenerate(root, ctx, btn, fd, attempt, ph) {
        setStatus(
            root,
            attempt === 0
                ? 'Przygotowanie zdjęcia źródłowego…'
                : 'Analiza zdjęcia źródłowego… (próba ' + (attempt + 1) + ')',
            'busy'
        );

        postForm(actionUrl(ctx, 'generatePackshot'), fd).then(function (res) {
            if (res && !res.ok && res.code === 'analysis_pending' && attempt < SUBMIT_RETRY_MAX) {
                setTimeout(function () {
                    submitGenerate(root, ctx, btn, fd, attempt + 1, ph);
                }, SUBMIT_RETRY_DELAY_MS);
                return;
            }
            if (!res || !res.ok) {
                setStatus(root, (res && res.error) ? res.error : 'Nie udało się rozpocząć generacji.', 'error');
                removeTile(ph);
                btn.disabled = false;
                return;
            }
            setStatus(root, 'Generowanie packshotu… (to może potrwać do kilku minut)', 'busy');
            pollJob(root, ctx, res.job_id, btn, res.packshot_external_ref || '', ph);
        }).catch(function () {
            setStatus(root, 'Błąd sieci podczas wysyłki. Spróbuj ponownie.', 'error');
            removeTile(ph);
            btn.disabled = false;
        });
    }

    /** Poll the getJob proxy until the job is terminal or the timeout elapses. */
    function pollJob(root, ctx, jobId, btn, packshotRef, ph) {
        var fetchJob = function () {
            return postForm(actionUrl(ctx, 'getJob'), formData({ job_id: jobId }));
        };

        poll(fetchJob, null, function (job) {
            btn.disabled = false;
            if (!job || !job.ok) {
                setStatus(root, (job && job.error) ? job.error : 'Nie udało się odczytać statusu zadania.', 'error');
                removeTile(ph);
                return;
            }
            if (job.status === 'completed') {
                setStatus(root, 'Packshot gotowy.', 'success');
                fillPackshotTile(ph, job, packshotRef);
            } else if (job.status === 'failed' || job.status === 'expired') {
                setStatus(root, job.error ? ('Generacja nie powiodła się: ' + job.error) : 'Generacja nie powiodła się.', 'error');
                removeTile(ph);
            } else if (job.status === 'cancelled') {
                setStatus(root, 'Generacja anulowana.', 'error');
                removeTile(ph);
            } else {
                setStatus(root, 'Zadanie zakończone w stanie: ' + (job.status || '—'), 'error');
                removeTile(ph);
            }
        }, function (err) {
            btn.disabled = false;
            removeTile(ph);
            if (err && err.message === 'poll_timeout') {
                setStatus(root, 'Przekroczono limit oczekiwania (5 min). Odśwież stronę, aby sprawdzić wynik.', 'error');
            } else if (err && err.payload && err.payload.error) {
                setStatus(root, err.payload.error, 'error');
            } else {
                setStatus(root, 'Błąd podczas sprawdzania statusu.', 'error');
            }
        });
    }

    /** Insert an empty placeholder packshot tile into a container's list. */
    function addPlaceholderTile(list) {
        if (!list) {
            return null;
        }

        var fig = document.createElement('figure');
        fig.className = 'qamera-packshot qamera-packshot--loading qamera-vote--pending';

        var roleBadge = document.createElement('span');
        roleBadge.className = 'qamera-badge qamera-badge--role';
        roleBadge.textContent = 'Packshot';
        fig.appendChild(roleBadge);

        var box = document.createElement('div');
        box.className = 'qamera-thumb qamera-thumb--placeholder qamera-thumb--loading';
        fig.appendChild(box);

        list.appendChild(fig);
        return fig;
    }

    function removeTile(ph) {
        if (ph && ph.parentNode) {
            ph.parentNode.removeChild(ph);
        }
    }

    /** Replace a placeholder tile's content with the completed packshot. */
    function fillPackshotTile(ph, job, packshotRef) {
        if (!ph) {
            return;
        }
        ph.className = 'qamera-packshot qamera-vote--pending';
        ph.setAttribute('data-job-id', job.job_id || '');
        ph.setAttribute('data-voting', 'pending');
        while (ph.firstChild) {
            ph.removeChild(ph.firstChild);
        }

        var roleBadge = document.createElement('span');
        roleBadge.className = 'qamera-badge qamera-badge--role';
        roleBadge.textContent = 'Packshot';
        ph.appendChild(roleBadge);

        if (job.url) {
            var img = document.createElement('img');
            img.src = job.url;
            img.alt = '';
            img.loading = 'lazy';
            ph.appendChild(img);
        } else {
            var box = document.createElement('div');
            box.className = 'qamera-thumb qamera-thumb--placeholder';
            ph.appendChild(box);
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

        if (packshotRef) {
            ph.setAttribute('data-packshot-ref', packshotRef);
            var delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.className = 'qamera-btn qamera-btn--delete';
            delBtn.setAttribute('data-action', 'delete-packshot');
            delBtn.setAttribute('data-packshot-ref', packshotRef);
            delBtn.textContent = 'Usuń';
            actions.appendChild(delBtn);
        }

        ph.appendChild(actions);
    }

    /* ── Flow B: packshot -> session -> publish ────────────────────────── */

    /** Generate a session from a packshot with the left-panel params. */
    function handleGenerateSession(root, ctx, target) {
        var fig = target.closest ? target.closest('.qamera-packshot') : null;
        // Server-rendered packshots carry the real asset id. A fresh (just
        // accepted) packshot tile may not — then we omit it and the backend
        // resolves the latest accepted packshot for the product.
        var packshotAsset = target.getAttribute('data-packshot-asset-id')
            || (fig ? fig.getAttribute('data-asset-id') : '') || '';
        var count = clampCount(val(root, '#qamera-count'));

        var fd = formData({
            id_product: ctx.idProduct,
            packshot_asset_id: packshotAsset,
            count: count,
            preset_id: val(root, '#qamera-preset'),
            model_id: val(root, '#qamera-model'),
            scenery_id: val(root, '#qamera-scenery'),
            aspect_ratio: val(root, '#qamera-aspect'),
            suggestions: val(root, '#qamera-context')
        });

        target.disabled = true;
        setStatus(root, 'Zlecanie sesji…', 'busy');

        // N placeholders appear immediately (one per requested image).
        var outputs = ensureSessionOutputs(fig);
        var tiles = [];
        if (outputs) {
            for (var i = 0; i < count; i++) {
                tiles.push(addSessionPlaceholder(outputs));
            }
        }

        postForm(actionUrl(ctx, 'generateSession'), fd).then(function (res) {
            if (!res || !res.ok) {
                setStatus(root, (res && res.error) ? res.error : 'Nie udało się zlecić sesji.', 'error');
                tiles.forEach(removeTile);
                target.disabled = false;
                return;
            }
            var jobIds = (res.job_ids && res.job_ids.length) ? res.job_ids : [];
            // Reconcile the placeholder count with the jobs actually created.
            while (tiles.length > jobIds.length) { removeTile(tiles.pop()); }
            while (tiles.length < jobIds.length && outputs) { tiles.push(addSessionPlaceholder(outputs)); }

            setStatus(root, 'Generowanie sesji… (to może potrwać do kilku minut)', 'busy');
            jobIds.forEach(function (jobId, idx) {
                pollSessionJob(root, ctx, jobId, tiles[idx] || null);
            });
            target.disabled = false;
        }).catch(function () {
            setStatus(root, 'Błąd sieci podczas zlecania sesji.', 'error');
            tiles.forEach(removeTile);
            target.disabled = false;
        });
    }

    /** Find or create the session outputs container inside a packshot figure. */
    function ensureSessionOutputs(fig) {
        if (!fig) {
            return null;
        }
        var outputs = fig.querySelector('.qamera-session__outputs');
        if (outputs) {
            return outputs;
        }
        var sessions = document.createElement('div');
        sessions.className = 'qamera-sessions';
        var meta = document.createElement('span');
        meta.className = 'qamera-meta';
        meta.textContent = 'Sesje';
        sessions.appendChild(meta);
        outputs = document.createElement('div');
        outputs.className = 'qamera-session__outputs';
        sessions.appendChild(outputs);
        fig.appendChild(sessions);
        return outputs;
    }

    /** Insert an empty loading session tile and return it. */
    function addSessionPlaceholder(container) {
        if (!container) {
            return null;
        }
        var fig = document.createElement('figure');
        fig.className = 'qamera-output qamera-vote--pending';
        var box = document.createElement('div');
        box.className = 'qamera-thumb qamera-thumb--placeholder qamera-thumb--loading';
        fig.appendChild(box);
        container.appendChild(fig);
        return fig;
    }

    /** Poll one session job (photo_shoot = 1 image) until terminal. */
    function pollSessionJob(root, ctx, jobId, tile) {
        var fetchJob = function () {
            return postForm(actionUrl(ctx, 'getJob'), formData({ job_id: jobId }));
        };
        poll(fetchJob, null, function (job) {
            if (!job || !job.ok) {
                markSessionFailed(tile, (job && job.error) ? job.error : '');
                return;
            }
            if (job.status === 'completed') {
                fillSessionTile(tile, job);
            } else {
                markSessionFailed(tile, job.error || ('Stan: ' + (job.status || '—')));
            }
        }, function (err) {
            var msg = (err && err.message === 'poll_timeout')
                ? 'Przekroczono limit oczekiwania.'
                : 'Błąd podczas sprawdzania statusu.';
            markSessionFailed(tile, msg);
        });
    }

    /** Show a failed session tile (kept visible so the merchant sees the gap). */
    function markSessionFailed(tile, msg) {
        if (!tile) {
            return;
        }
        tile.className = 'qamera-output qamera-vote--rejected';
        while (tile.firstChild) {
            tile.removeChild(tile.firstChild);
        }
        var box = document.createElement('div');
        box.className = 'qamera-thumb qamera-thumb--placeholder';
        tile.appendChild(box);
        var meta = document.createElement('span');
        meta.className = 'qamera-meta';
        meta.textContent = msg || 'Nie powiodło się.';
        tile.appendChild(meta);
    }

    /** Replace a session placeholder with the generated image + accept/reject. */
    function fillSessionTile(tile, job) {
        if (!tile) {
            return;
        }
        tile.className = 'qamera-output qamera-vote--pending';
        tile.setAttribute('data-job-id', job.job_id || '');
        tile.setAttribute('data-voting', 'pending');
        while (tile.firstChild) {
            tile.removeChild(tile.firstChild);
        }

        if (job.url) {
            var img = document.createElement('img');
            img.src = job.url;
            img.alt = '';
            img.loading = 'lazy';
            tile.appendChild(img);
        } else {
            var box = document.createElement('div');
            box.className = 'qamera-thumb qamera-thumb--placeholder';
            tile.appendChild(box);
        }

        var actions = document.createElement('div');
        actions.className = 'qamera-output__actions';

        var acceptBtn = document.createElement('button');
        acceptBtn.type = 'button';
        acceptBtn.className = 'qamera-btn qamera-btn--accept';
        acceptBtn.setAttribute('data-action', 'accept-session');
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
        tile.appendChild(actions);
    }

    /** Accept a session image → publish it to the product gallery. */
    function handleAcceptSession(root, ctx, target) {
        var jobId = target.getAttribute('data-job-id');
        if (!jobId) {
            return;
        }
        var fig = target.closest ? target.closest('.qamera-output') : null;
        target.disabled = true;
        setStatus(root, 'Publikowanie zdjęcia w galerii…', 'busy');

        postForm(actionUrl(ctx, 'acceptSession'), formData({ job_id: jobId, id_product: ctx.idProduct })).then(function (res) {
            if (!res || !res.ok) {
                target.disabled = false;
                setStatus(root, (res && res.error) ? res.error : 'Nie udało się opublikować zdjęcia.', 'error');
                return;
            }
            if (fig) {
                fig.className = fig.className.replace(/qamera-vote--\w+/, 'qamera-vote--accepted');
                fig.setAttribute('data-voting', 'accepted');
                var actions = fig.querySelector('.qamera-output__actions');
                if (actions && actions.parentNode) {
                    actions.parentNode.removeChild(actions);
                }
                setStateBadge(fig, 'accepted');
            }
            setStatus(
                root,
                res.duplicate ? 'Zdjęcie było już w galerii produktu.' : 'Zatwierdzono — dodano do galerii produktu.',
                'success'
            );
        }).catch(function () {
            target.disabled = false;
            setStatus(root, 'Błąd sieci podczas publikacji.', 'error');
        });
    }

    /** Add a "Generuj sesję" button to a freshly approved packshot tile. */
    function addSessionButton(fig) {
        if (!fig || fig.querySelector('[data-action="generate-session"]')) {
            return;
        }
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'qamera-btn qamera-btn--primary';
        btn.setAttribute('data-action', 'generate-session');
        // No asset id on a fresh tile — the server resolves the latest accepted
        // packshot for the product when packshot_asset_id is omitted.
        btn.setAttribute('data-packshot-asset-id', fig.getAttribute('data-asset-id') || '');
        btn.textContent = 'Generuj sesję';
        var actions = fig.querySelector('.qamera-packshot__actions');
        if (actions && actions.parentNode) {
            actions.parentNode.insertBefore(btn, actions);
        } else {
            fig.appendChild(btn);
        }
    }

    /** Remove the accept/reject vote buttons from a card. */
    function removeVoteButtons(fig) {
        if (!fig) { return; }
        var btns = fig.querySelectorAll('[data-vote="accept"], [data-vote="reject"]');
        for (var i = 0; i < btns.length; i++) {
            if (btns[i].parentNode) { btns[i].parentNode.removeChild(btns[i]); }
        }
    }

    function handleVote(root, ctx, target, vote) {
        var jobId = target.getAttribute('data-job-id');
        if (!jobId) {
            return;
        }
        var fig = target.closest ? target.closest('.qamera-packshot, .qamera-output') : null;
        var isPackshot = fig && fig.classList && fig.classList.contains('qamera-packshot');
        var isOutput = fig && fig.classList && fig.classList.contains('qamera-output');
        target.disabled = true;

        var action = vote === 'accept' ? 'acceptJob' : 'rejectJob';
        postForm(actionUrl(ctx, action), formData({ job_id: jobId })).then(function (res) {
            if (!res || !res.ok) {
                target.disabled = false;
                setStatus(root, (res && res.error) ? res.error : 'Nie udało się zapisać oceny.', 'error');
                return;
            }

            // Rejecting a packshot removes it from the catalog (PRD rule).
            if (vote === 'reject' && isPackshot) {
                var ref = fig.getAttribute('data-packshot-ref') || fig.getAttribute('data-packshot-id') || '';
                if (ref) {
                    postForm(actionUrl(ctx, 'deletePackshot'), formData({ packshot_ref: ref })).then(function () {
                        removeTile(fig);
                        setStatus(root, 'Packshot odrzucony i usunięty.', 'success');
                    }).catch(function () {
                        removeTile(fig);
                        setStatus(root, 'Packshot odrzucony.', 'success');
                    });
                } else {
                    removeTile(fig);
                    setStatus(root, 'Packshot odrzucony.', 'success');
                }
                return;
            }

            // Rejecting a session image just discards the tile (never published).
            if (vote === 'reject' && isOutput) {
                removeTile(fig);
                setStatus(root, 'Zdjęcie odrzucone.', 'success');
                return;
            }

            target.disabled = false;
            if (fig) {
                fig.className = fig.className.replace(/qamera-vote--\w+/, 'qamera-vote--' + res.voting);
                fig.setAttribute('data-voting', res.voting);
                if (vote === 'accept' && isPackshot) {
                    fig.classList.add('qamera-packshot--selectable');
                    // Approved → drop accept/reject, reveal "Generuj sesję".
                    removeVoteButtons(fig);
                    addSessionButton(fig);
                }
                setStateBadge(fig, res.voting);
            }
            setStatus(root, vote === 'accept' ? 'Packshot zatwierdzony.' : 'Odrzucono.', 'success');
        }).catch(function () {
            target.disabled = false;
            setStatus(root, 'Błąd sieci podczas zapisu oceny.', 'error');
        });
    }

    function handleDelete(root, ctx, target) {
        var fig = target.closest ? target.closest('.qamera-packshot') : null;
        var ref = target.getAttribute('data-packshot-ref')
            || (fig ? fig.getAttribute('data-packshot-ref') || fig.getAttribute('data-packshot-id') : '');
        if (!ref) {
            setStatus(root, 'Brak identyfikatora packshota do usunięcia.', 'error');
            return;
        }
        if (window.confirm('Usunąć ten packshot z katalogu Qamera AI? Tej operacji nie można cofnąć.')) {
            target.disabled = true;
            postForm(actionUrl(ctx, 'deletePackshot'), formData({ packshot_ref: ref })).then(function (res) {
                if (!res || !res.ok) {
                    target.disabled = false;
                    setStatus(root, (res && res.error) ? res.error : 'Nie udało się usunąć packshota.', 'error');
                    return;
                }
                if (fig && fig.parentNode) {
                    fig.parentNode.removeChild(fig);
                }
                setStatus(root, 'Packshot usunięty.', 'success');
            }).catch(function () {
                target.disabled = false;
                setStatus(root, 'Błąd sieci podczas usuwania.', 'error');
            });
        }
    }

    /** Swap the accepted/pending/rejected badge on a card to reflect a new vote. */
    function setStateBadge(fig, voting) {
        var labels = { accepted: 'Zatwierdzony', rejected: 'Odrzucony', pending: 'Oczekuje' };
        var classes = { accepted: 'accepted', rejected: 'rejected', pending: 'pending' };
        var badge = fig.querySelector('.qamera-badge--accepted, .qamera-badge--rejected, .qamera-badge--pending');
        if (!badge) {
            // Fresh card has only the role badge — insert a state badge after it.
            var role = fig.querySelector('.qamera-badge--role');
            badge = document.createElement('span');
            if (role && role.parentNode) {
                role.parentNode.insertBefore(badge, role.nextSibling);
            } else {
                fig.insertBefore(badge, fig.firstChild);
            }
        }
        badge.className = 'qamera-badge qamera-badge--' + (classes[voting] || 'pending');
        badge.textContent = labels[voting] || 'Oczekuje';
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
