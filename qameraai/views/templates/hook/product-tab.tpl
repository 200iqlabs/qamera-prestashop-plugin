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

    <h3 class="qamera-tab__title">{l s='Qamera AI' mod='qameraai'}</h3>

    {if !$qamera_has_key}
        <p class="qamera-alert qamera-alert--warning">
            {l s='Brak klucza API. Skonfiguruj moduł Qamera AI w ustawieniach, aby generować zdjęcia.' mod='qameraai'}
        </p>
    {else}

        {if $qamera_catalog_error}
            <p class="qamera-alert qamera-alert--error">{$qamera_catalog_error|escape:'htmlall':'UTF-8'}</p>
        {/if}

        <div class="qamera-grid">

            {* ── LEFT: source + session settings ───────────────────────────── *}
            <aside class="qamera-col qamera-col--left">
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
                                        {if $g.as_image}
                                            <button type="button" class="qamera-btn qamera-btn--primary"
                                                    data-action="generate-packshot" data-id-image="{$g.id_image|intval}">
                                                {l s='Generuj packshot' mod='qameraai'}
                                            </button>
                                        {/if}
                                    </div>
                                </figure>
                            {/foreach}
                        </div>
                    {/if}

                    <p class="qamera-status" id="qamera-generate-status" role="status" aria-live="polite"></p>
                </section>

                <section class="qamera-card">
                    <h4 class="qamera-card__title">{l s='Ustawienia sesji' mod='qameraai'}</h4>

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
                        <label for="qamera-aspect">{l s='Proporcje' mod='qameraai'}</label>
                        <select id="qamera-aspect">
                            <option value="1:1">1:1</option>
                            <option value="4:5">4:5</option>
                            <option value="3:4">3:4</option>
                            <option value="16:9">16:9</option>
                        </select>
                        <label for="qamera-count">{l s='Liczba' mod='qameraai'}</label>
                        <input type="number" id="qamera-count" min="1" max="10" value="4" />
                    </div>

                    <div class="qamera-field">
                        <label for="qamera-context">{l s='Kontekst / sugestie' mod='qameraai'}</label>
                        <textarea id="qamera-context" rows="2"></textarea>
                    </div>
                </section>
            </aside>

            {* ── RIGHT: photo -> packshots -> sessions ─────────────────────── *}
            <main class="qamera-col qamera-col--right">

                {* Freshly generated packshots land here without a page reload. *}
                <section class="qamera-container qamera-container--fresh" id="qamera-new-packshots" hidden>
                    <div class="qamera-container__photo">
                        <span class="qamera-badge qamera-badge--role">{l s='Nowy packshot' mod='qameraai'}</span>
                    </div>
                    <div class="qamera-container__packshots" id="qamera-new-packshots-list"></div>
                </section>

                {if $qamera_state_error}
                    <p class="qamera-alert qamera-alert--error">{$qamera_state_error|escape:'htmlall':'UTF-8'}</p>
                {elseif $qamera_is_empty}
                    <div class="qamera-empty">
                        <p>{l s='Brak zdjęć i packshotów dla tego produktu.' mod='qameraai'}</p>
                        <p class="qamera-empty__hint">{l s='Wybierz zdjęcie z galerii produktu po lewej, dodaj je jako źródło i wygeneruj packshot, aby zacząć.' mod='qameraai'}</p>
                    </div>
                {else}

                    {if $qamera_truncated}
                        <p class="qamera-alert qamera-alert--warning">
                            {l s='Produkt ma ponad 100 zdjęć lub packshotów — pokazano pierwszą setkę.' mod='qameraai'}
                        </p>
                    {/if}

                    {foreach from=$qamera_containers item=container}
                        <section class="qamera-container" data-image-id="{$container.photo.id|escape:'htmlall':'UTF-8'}">
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

                {/if}
            </main>
        </div>
    {/if}
</div>
