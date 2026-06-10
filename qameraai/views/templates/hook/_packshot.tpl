{*
 * Qamera AI — single packshot card with its sessions.
 * Approval/role derived from API voting: accepted | pending | rejected.
 * $packshot is passed in by the including template.
 *}
<figure class="qamera-packshot qamera-vote--{$packshot.voting|escape:'htmlall':'UTF-8'}"
        data-packshot-id="{$packshot.id|escape:'htmlall':'UTF-8'}"
        data-asset-id="{$packshot.asset_id|escape:'htmlall':'UTF-8'}"
        data-voting="{$packshot.voting|escape:'htmlall':'UTF-8'}"
        data-approved="{if $packshot.approved}1{else}0{/if}">

    <span class="qamera-badge qamera-badge--role">{l s='Packshot' mod='qameraai'}</span>
    {if $packshot.approved}
        <span class="qamera-badge qamera-badge--accepted">{l s='Zatwierdzony' mod='qameraai'}</span>
    {elseif $packshot.rejected}
        <span class="qamera-badge qamera-badge--rejected">{l s='Odrzucony' mod='qameraai'}</span>
    {else}
        <span class="qamera-badge qamera-badge--pending">{l s='Oczekuje' mod='qameraai'}</span>
    {/if}

    {if $packshot.url}
        <img src="{$packshot.thumb|escape:'htmlall':'UTF-8'}" alt="" loading="lazy" />
    {else}
        <div class="qamera-thumb qamera-thumb--placeholder"></div>
    {/if}

    {* Session results from this packshot (grouped by packshot_asset_id). *}
    {if $packshot.sessions|@count > 0}
        <div class="qamera-sessions">
            {foreach from=$packshot.sessions item=session}
                <div class="qamera-session" data-job-id="{$session.job_id|escape:'htmlall':'UTF-8'}" data-status="{$session.status|escape:'htmlall':'UTF-8'}">
                    <span class="qamera-meta">{l s='Sesja:' mod='qameraai'} {$session.status|escape:'htmlall':'UTF-8'}</span>
                    <div class="qamera-session__outputs">
                        {foreach from=$session.outputs item=output}
                            <figure class="qamera-output qamera-vote--{$output.voting|escape:'htmlall':'UTF-8'}"
                                    data-asset-id="{$output.asset_id|escape:'htmlall':'UTF-8'}"
                                    data-approved="{if $output.approved}1{else}0{/if}">
                                {if $output.url}
                                    <img src="{$output.thumb|escape:'htmlall':'UTF-8'}" alt="" loading="lazy" />
                                {else}
                                    <div class="qamera-thumb qamera-thumb--placeholder"></div>
                                {/if}
                                {if $output.approved}
                                    <span class="qamera-badge qamera-badge--accepted">{l s='Zatwierdzony' mod='qameraai'}</span>
                                {/if}
                            </figure>
                        {/foreach}
                    </div>
                </div>
            {/foreach}
        </div>
    {/if}
</figure>
