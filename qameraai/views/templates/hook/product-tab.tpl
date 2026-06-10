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
<div class="qamera-tab" id="qamera-product-tab"
     data-id-product="{$qamera_id_product|intval}"
     data-external-ref="{$qamera_external_ref|escape:'htmlall':'UTF-8'}"
     data-default-preset="{$qamera_default_preset_id|escape:'htmlall':'UTF-8'}">

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
                    <h4 class="qamera-card__title">{l s='Źródło' mod='qameraai'}</h4>
                    <div class="qamera-field">
                        <label for="qamera-source-file">{l s='Wgraj zdjęcie produktu' mod='qameraai'}</label>
                        <input type="file" id="qamera-source-file" accept="image/*" />
                    </div>
                    <div class="qamera-field">
                        <label for="qamera-role">{l s='Rola' mod='qameraai'}</label>
                        <select id="qamera-role">
                            <option value="photo">{l s='Zdjęcie (źródło)' mod='qameraai'}</option>
                            <option value="packshot">{l s='Packshot bezpośredni' mod='qameraai'}</option>
                        </select>
                    </div>
                    <button type="button" class="qamera-btn qamera-btn--primary" id="qamera-generate-packshot">
                        {l s='Generuj packshot' mod='qameraai'}
                    </button>
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

                    <div class="qamera-field">
                        <label for="qamera-ai-model">{l s='Model AI' mod='qameraai'}</label>
                        <select id="qamera-ai-model">
                            {foreach from=$qamera_ai_models item=aimodel}
                                <option value="{$aimodel.id|escape:'htmlall':'UTF-8'}">{$aimodel.name|escape:'htmlall':'UTF-8'}</option>
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

                {if $qamera_state_error}
                    <p class="qamera-alert qamera-alert--error">{$qamera_state_error|escape:'htmlall':'UTF-8'}</p>
                {elseif $qamera_is_empty}
                    <div class="qamera-empty">
                        <p>{l s='Brak zdjęć i packshotów dla tego produktu.' mod='qameraai'}</p>
                        <p class="qamera-empty__hint">{l s='Wgraj zdjęcie po lewej i wygeneruj packshot, aby zacząć.' mod='qameraai'}</p>
                    </div>
                {else}

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
