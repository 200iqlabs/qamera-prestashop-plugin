{*
 * Qamera AI — product page tab placeholder.
 * The Core Flow generator UI (select photo -> packshot -> session -> publish)
 * is built in later milestones. This skeleton renders the empty/error states.
 *}
<div class="panel" id="qamera-product-tab">
    <h3>{l s='Qamera AI' mod='qameraai'}</h3>
    {if !$qamera_has_key}
        <p class="alert alert-warning">
            {l s='Brak klucza API. Skonfiguruj moduł Qamera AI, aby generować zdjęcia.' mod='qameraai'}
        </p>
    {else}
        <p>{l s='Generator zdjęć Qamera AI dla tego produktu.' mod='qameraai'}</p>
    {/if}
</div>
