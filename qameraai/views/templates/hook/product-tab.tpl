{*
 * Qamera AI — product page tab (Core Flow generator UI).
 *
 * Left column:  source photo upload/role + session settings (catalog dropdowns).
 * Right column: containers grouping each photo -> its packshots -> their sessions.
 *
 * State (roles, approval, lineage) is derived in PHP from the Qamera API
 * (ProductPackshot.voting + job voting + source_image_id/packshot_asset_id) and
 * passed in here. This template only renders it. Brand: teal accent #83babc.
 *}
{* Assets inlined here: the PS8/9 product page is a Symfony route, so
   actionAdminControllerSetMedia does not fire — the hook output is the reliable
   place to load the tab's CSS/JS. The JS guards against double-init. *}
<link rel="stylesheet" href="{$qamera_css_url|escape:'htmlall':'UTF-8'}">
<script src="{$qamera_js_url|escape:'htmlall':'UTF-8'}" defer></script>
<div class="qamera-tab" id="qamera-product-tab"
     data-id-product="{$qamera_id_product|intval}"
     data-external-ref="{$qamera_external_ref|escape:'htmlall':'UTF-8'}"
     data-default-preset="{$qamera_default_preset_id|escape:'htmlall':'UTF-8'}"
     data-ajax-url="{$qamera_ajax_url|escape:'htmlall':'UTF-8'}">

    {* i18n payload for product-tab.js: strings rendered client-side (progress,
       button labels, confirms) routed through the PrestaShop translation domain
       so the JS UI is PL/EN like the server. The script reads it via JSON.parse;
       js=1 escaping keeps the values JSON-safe. *}
    <script type="application/json" id="qamera-i18n">
    {ldelim}
        "addingPackshot": "{l s='Dodawanie jako packshot…' mod='qameraai' js=1}",
        "addingImage": "{l s='Dodawanie jako zdjęcie produktu…' mod='qameraai' js=1}",
        "addFailed": "{l s='Nie udało się dodać zdjęcia.' mod='qameraai' js=1}",
        "addedPackshot": "{l s='Dodano jako packshot.' mod='qameraai' js=1}",
        "addedImage": "{l s='Dodano jako zdjęcie produktu — możesz wygenerować packshot poniżej.' mod='qameraai' js=1}",
        "netAdd": "{l s='Błąd sieci podczas dodawania.' mod='qameraai' js=1}",
        "badgeSource": "{l s='źródło' mod='qameraai' js=1}",
        "badgePackshotLower": "{l s='packshot' mod='qameraai' js=1}",
        "labelPhoto": "{l s='Zdjęcie' mod='qameraai' js=1}",
        "genPackshot": "{l s='Generuj packshot' mod='qameraai' js=1}",
        "rolePackshot": "{l s='Packshot' mod='qameraai' js=1}",
        "accepted": "{l s='Zatwierdzony' mod='qameraai' js=1}",
        "genSession": "{l s='Generuj sesję' mod='qameraai' js=1}",
        "delete": "{l s='Usuń' mod='qameraai' js=1}",
        "prepSource": "{l s='Przygotowanie zdjęcia źródłowego…' mod='qameraai' js=1}",
        "analyzing": "{l s='Analiza zdjęcia źródłowego… (próba %s)' mod='qameraai' js=1}",
        "genStartFailed": "{l s='Nie udało się rozpocząć generacji.' mod='qameraai' js=1}",
        "genPackshotBusy": "{l s='Generowanie packshotu… (to może potrwać do kilku minut)' mod='qameraai' js=1}",
        "netUpload": "{l s='Błąd sieci podczas wysyłki. Spróbuj ponownie.' mod='qameraai' js=1}",
        "jobStatusFailed": "{l s='Nie udało się odczytać statusu zadania.' mod='qameraai' js=1}",
        "packshotReady": "{l s='Packshot gotowy.' mod='qameraai' js=1}",
        "genFailedWith": "{l s='Generacja nie powiodła się: %s' mod='qameraai' js=1}",
        "genFailed": "{l s='Generacja nie powiodła się.' mod='qameraai' js=1}",
        "genCancelled": "{l s='Generacja anulowana.' mod='qameraai' js=1}",
        "jobEndedState": "{l s='Zadanie zakończone w stanie: %s' mod='qameraai' js=1}",
        "pollTimeout5": "{l s='Przekroczono limit oczekiwania (5 min). Odśwież stronę, aby sprawdzić wynik.' mod='qameraai' js=1}",
        "statusCheckErr": "{l s='Błąd podczas sprawdzania statusu.' mod='qameraai' js=1}",
        "accept": "{l s='Zatwierdź' mod='qameraai' js=1}",
        "reject": "{l s='Odrzuć' mod='qameraai' js=1}",
        "labelSessions": "{l s='Sesje' mod='qameraai' js=1}",
        "sessionAssign": "{l s='Zlecanie sesji…' mod='qameraai' js=1}",
        "sessionStartFailed": "{l s='Nie udało się zlecić sesji.' mod='qameraai' js=1}",
        "sessionBusy": "{l s='Generowanie sesji… (to może potrwać do kilku minut)' mod='qameraai' js=1}",
        "netSession": "{l s='Błąd sieci podczas zlecania sesji.' mod='qameraai' js=1}",
        "pollTimeoutShort": "{l s='Przekroczono limit oczekiwania.' mod='qameraai' js=1}",
        "stateColon": "{l s='Stan: %s' mod='qameraai' js=1}",
        "failedShort": "{l s='Nie powiodło się.' mod='qameraai' js=1}",
        "publishing": "{l s='Publikowanie zdjęcia w galerii…' mod='qameraai' js=1}",
        "publishFailed": "{l s='Nie udało się opublikować zdjęcia.' mod='qameraai' js=1}",
        "alreadyInGallery": "{l s='Zdjęcie było już w galerii produktu.' mod='qameraai' js=1}",
        "publishedOk": "{l s='Zatwierdzono — dodano do galerii produktu.' mod='qameraai' js=1}",
        "netPublish": "{l s='Błąd sieci podczas publikacji.' mod='qameraai' js=1}",
        "voteSaveFailed": "{l s='Nie udało się zapisać oceny.' mod='qameraai' js=1}",
        "packshotRejectedDeleted": "{l s='Packshot odrzucony i usunięty.' mod='qameraai' js=1}",
        "packshotRejected": "{l s='Packshot odrzucony.' mod='qameraai' js=1}",
        "imageRejected": "{l s='Zdjęcie odrzucone.' mod='qameraai' js=1}",
        "packshotAccepted": "{l s='Packshot zatwierdzony.' mod='qameraai' js=1}",
        "rejected": "{l s='Odrzucono.' mod='qameraai' js=1}",
        "netVote": "{l s='Błąd sieci podczas zapisu oceny.' mod='qameraai' js=1}",
        "noPackshotRef": "{l s='Brak identyfikatora packshota do usunięcia.' mod='qameraai' js=1}",
        "confirmDelete": "{l s='Usunąć ten packshot z katalogu Qamera AI? Tej operacji nie można cofnąć.' mod='qameraai' js=1}",
        "deletePackshotFailed": "{l s='Nie udało się usunąć packshota.' mod='qameraai' js=1}",
        "packshotDeleted": "{l s='Packshot usunięty.' mod='qameraai' js=1}",
        "netDelete": "{l s='Błąd sieci podczas usuwania.' mod='qameraai' js=1}",
        "badgeRejected": "{l s='Odrzucony' mod='qameraai' js=1}",
        "badgePending": "{l s='Oczekuje' mod='qameraai' js=1}"
    {rdelim}
    </script>

    <h3 class="qamera-tab__title">{l s='Qamera AI' mod='qameraai'}</h3>

    {if !$qamera_has_key}
        <p class="qamera-alert qamera-alert--warning">
            {l s='Brak klucza API. Skonfiguruj moduł Qamera AI w ustawieniach, aby generować zdjęcia.' mod='qameraai'}
        </p>
    {else}

        {if $qamera_catalog_error}
            <p class="qamera-alert qamera-alert--error">{$qamera_catalog_error|escape:'htmlall':'UTF-8'}</p>
        {/if}

        {* Global status line (registration + generation feedback). *}
        <p class="qamera-status" id="qamera-generate-status" role="status" aria-live="polite"></p>

        {* ── 1. Zdjęcia produktu (galeria PS = źródło) ─────────────────── *}
        <section class="qamera-card">
            <h4 class="qamera-card__title">{l s='Zdjęcia produktu' mod='qameraai'}</h4>
            <p class="qamera-card__hint">{l s='Wybierz zdjęcie z galerii produktu. Dodaj je jako źródło do generacji packshotu albo bezpośrednio jako gotowy packshot.' mod='qameraai'}</p>

            {if $qamera_gallery|@count == 0}
                <p class="qamera-muted">{l s='Brak zdjęć w galerii tego produktu. Dodaj zdjęcia do produktu (zakładka Zdjęcia), aby zacząć.' mod='qameraai'}</p>
            {else}
                <div class="qamera-gallery">
                    {foreach from=$qamera_gallery item=g}
                        <figure class="qamera-gallery__item" data-id-image="{$g.id_image|intval}"
                                data-as-image="{if $g.as_image}1{else}0{/if}"
                                data-as-packshot="{if $g.as_packshot}1{else}0{/if}">
                            {if $g.url}
                                <img src="{$g.url|escape:'htmlall':'UTF-8'}" alt="" loading="lazy" />
                            {else}
                                <div class="qamera-thumb qamera-thumb--placeholder"></div>
                            {/if}

                            <figcaption class="qamera-gallery__badges">
                                {if $g.as_image}<span class="qamera-badge qamera-badge--accepted">{l s='źródło' mod='qameraai'}</span>{/if}
                                {if $g.as_packshot}<span class="qamera-badge qamera-badge--role">{l s='packshot' mod='qameraai'}</span>{/if}
                            </figcaption>

                            {* Only two options on a product gallery photo: register
                               as source image OR directly as packshot. Generation
                               happens later, on the platform tile (section 3). *}
                            <div class="qamera-gallery__actions">
                                {if !$g.as_image && !$g.as_packshot}
                                    <button type="button" class="qamera-btn qamera-btn--ghost"
                                            data-action="register-image" data-id-image="{$g.id_image|intval}">
                                        {l s='Dodaj jako zdjęcie produktu' mod='qameraai'}
                                    </button>
                                    <button type="button" class="qamera-btn qamera-btn--ghost"
                                            data-action="register-packshot-direct" data-id-image="{$g.id_image|intval}">
                                        {l s='Dodaj jako packshot' mod='qameraai'}
                                    </button>
                                {/if}
                            </div>
                        </figure>
                    {/foreach}
                </div>
            {/if}
        </section>

        {* ── 2. Ustawienia sesji ───────────────────────────────────────── *}
        <section class="qamera-card">
            <h4 class="qamera-card__title">{l s='Ustawienia sesji' mod='qameraai'}</h4>

            <div class="qamera-settings__grid">
                <div class="qamera-field">
                    <label for="qamera-preset">{l s='Preset' mod='qameraai'}</label>
                    <select id="qamera-preset">
                        <option value="">{l s='— domyślny —' mod='qameraai'}</option>
                        {foreach from=$qamera_presets item=preset}
                            <option value="{$preset.id|escape:'htmlall':'UTF-8'}"{if $preset.id == $qamera_default_preset_id} selected="selected"{/if}>{$preset.name|escape:'htmlall':'UTF-8'}</option>
                        {/foreach}
                    </select>
                </div>

                <div class="qamera-field">
                    <label for="qamera-model">{l s='Model (manekin)' mod='qameraai'}</label>
                    <select id="qamera-model">
                        <option value="">{l s='— brak —' mod='qameraai'}</option>
                        {foreach from=$qamera_models item=model}
                            <option value="{$model.id|escape:'htmlall':'UTF-8'}">{$model.name|escape:'htmlall':'UTF-8'}</option>
                        {/foreach}
                    </select>
                </div>

                <div class="qamera-field">
                    <label for="qamera-scenery">{l s='Sceneria' mod='qameraai'}</label>
                    <select id="qamera-scenery">
                        <option value="">{l s='— brak —' mod='qameraai'}</option>
                        {foreach from=$qamera_sceneries item=scenery}
                            <option value="{$scenery.id|escape:'htmlall':'UTF-8'}">{$scenery.name|escape:'htmlall':'UTF-8'}</option>
                        {/foreach}
                    </select>
                </div>

                <div class="qamera-field qamera-field--inline">
                    <div>
                        <label for="qamera-aspect">{l s='Proporcje' mod='qameraai'}</label>
                        <select id="qamera-aspect">
                            <option value="1:1">1:1</option>
                            <option value="4:5">4:5</option>
                            <option value="3:4">3:4</option>
                            <option value="16:9">16:9</option>
                        </select>
                    </div>
                    <div>
                        <label for="qamera-count">{l s='Liczba' mod='qameraai'}</label>
                        <input type="number" id="qamera-count" min="1" max="10" value="4" />
                    </div>
                </div>

                <div class="qamera-field qamera-field--full">
                    <label for="qamera-context">{l s='Kontekst / sugestie' mod='qameraai'}</label>
                    <textarea id="qamera-context" rows="2"></textarea>
                </div>
            </div>
        </section>

        {* ── 3. Zdjęcia platformy (stan z API: zdjęcie → packshoty → sesje) *}
        <section class="qamera-card">
            <h4 class="qamera-card__title">{l s='Zdjęcia platformy' mod='qameraai'}</h4>

            {if $qamera_state_error}
                <p class="qamera-alert qamera-alert--error">{$qamera_state_error|escape:'htmlall':'UTF-8'}</p>
            {/if}

            {if $qamera_truncated}
                <p class="qamera-alert qamera-alert--warning">
                    {l s='Produkt ma ponad 100 zdjęć lub packshotów — pokazano pierwszą setkę.' mod='qameraai'}
                </p>
            {/if}

            {* Empty-state — hidden by JS once a tile is injected live. *}
            <div class="qamera-empty" id="qamera-empty"{if !$qamera_is_empty || $qamera_state_error} hidden{/if}>
                <p>{l s='Brak zdjęć i packshotów dla tego produktu.' mod='qameraai'}</p>
                <p class="qamera-empty__hint">{l s='Wybierz zdjęcie z galerii produktu powyżej, dodaj je jako źródło i wygeneruj packshot, aby zacząć.' mod='qameraai'}</p>
            </div>

            <div id="qamera-platform-list">
                {foreach from=$qamera_containers item=container}
                    <section class="qamera-container" data-image-id="{$container.photo.id|escape:'htmlall':'UTF-8'}" data-id-image="{$container.photo.id_image|intval}">
                        <div class="qamera-container__photo">
                            <span class="qamera-badge qamera-badge--role">{l s='Zdjęcie' mod='qameraai'}</span>
                            {if $container.photo.url}
                                <img src="{$container.photo.thumb|escape:'htmlall':'UTF-8'}" alt="" loading="lazy" />
                            {else}
                                <div class="qamera-thumb qamera-thumb--placeholder"></div>
                            {/if}
                            {if $container.photo.analysis_status}
                                <span class="qamera-meta">{l s='Analiza:' mod='qameraai'} {$container.photo.analysis_status|escape:'htmlall':'UTF-8'}</span>
                            {/if}
                            {if $container.photo.id_image}
                                <button type="button" class="qamera-btn qamera-btn--primary"
                                        data-action="generate-packshot" data-id-image="{$container.photo.id_image|intval}">
                                    {l s='Generuj packshot' mod='qameraai'}
                                </button>
                            {/if}
                        </div>

                        <div class="qamera-container__packshots">
                            {if $container.packshots|@count == 0}
                                <p class="qamera-muted">{l s='Brak packshotów z tego zdjęcia.' mod='qameraai'}</p>
                            {else}
                                {foreach from=$container.packshots item=packshot}
                                    {include file="./_packshot.tpl" packshot=$packshot}
                                {/foreach}
                            {/if}
                        </div>
                    </section>
                {/foreach}

                {if $qamera_standalone_packshots|@count > 0}
                    <section class="qamera-container qamera-container--standalone">
                        <div class="qamera-container__photo">
                            <span class="qamera-badge qamera-badge--role">{l s='Packshoty bezpośrednie' mod='qameraai'}</span>
                        </div>
                        <div class="qamera-container__packshots">
                            {foreach from=$qamera_standalone_packshots item=packshot}
                                {include file="./_packshot.tpl" packshot=$packshot}
                            {/foreach}
                        </div>
                    </section>
                {/if}
            </div>
        </section>
    {/if}
</div>
