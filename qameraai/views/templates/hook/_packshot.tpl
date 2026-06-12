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

    <div class="qamera-packshot__main">
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

        <div class="qamera-packshot__actions">
            {* Approved (accepted / direct) packshot → can seed a session (§3 rule).
               Pending packshot → accept/reject (accepting approves the packshot). *}
            {if $packshot.approved}
                <button type="button" class="qamera-btn qamera-btn--primary"
                        data-action="generate-session"
                        data-packshot-asset-id="{$packshot.asset_id|escape:'htmlall':'UTF-8'}">
                    {l s='Generuj sesję' mod='qameraai'}
                </button>
            {elseif !$packshot.rejected && $packshot.job_id}
                <button type="button" class="qamera-btn qamera-btn--accept"
                        data-vote="accept" data-job-id="{$packshot.job_id|escape:'htmlall':'UTF-8'}">
                    {l s='Zatwierdź' mod='qameraai'}
                </button>
                <button type="button" class="qamera-btn qamera-btn--reject"
                        data-vote="reject" data-job-id="{$packshot.job_id|escape:'htmlall':'UTF-8'}">
                    {l s='Odrzuć' mod='qameraai'}
                </button>
            {/if}
            <button type="button" class="qamera-btn qamera-btn--delete"
                    data-action="delete-packshot"
                    data-packshot-ref="{$packshot.id|escape:'htmlall':'UTF-8'}">
                {l s='Usuń' mod='qameraai'}
            </button>
        </div>
    </div>

    {* Session results from this packshot (one job = one image, job-level voting). *}
    {if $packshot.sessions|@count > 0}
        <div class="qamera-sessions">
            <span class="qamera-meta">{l s='Sesje' mod='qameraai'}</span>
            <div class="qamera-session__outputs">
                {foreach from=$packshot.sessions item=session}
                    <figure class="qamera-output qamera-vote--{$session.voting|escape:'htmlall':'UTF-8'}"
                            data-job-id="{$session.job_id|escape:'htmlall':'UTF-8'}"
                            data-status="{$session.status|escape:'htmlall':'UTF-8'}"
                            data-voting="{$session.voting|escape:'htmlall':'UTF-8'}"
                            data-approved="{if $session.approved}1{else}0{/if}">
                        {if $session.url}
                            <img src="{$session.thumb|escape:'htmlall':'UTF-8'}" alt="" loading="lazy" />
                        {else}
                            <div class="qamera-thumb qamera-thumb--placeholder"></div>
                        {/if}
                        {if $session.approved}
                            <span class="qamera-badge qamera-badge--accepted">{l s='Zatwierdzony' mod='qameraai'}</span>
                        {elseif $session.rejected}
                            <span class="qamera-badge qamera-badge--rejected">{l s='Odrzucony' mod='qameraai'}</span>
                        {else}
                            <span class="qamera-meta">{$session.status|escape:'htmlall':'UTF-8'}</span>
                        {/if}

                        {* Approve → published to the product gallery; reject → discarded. *}
                        {if $session.url && !$session.approved && !$session.rejected}
                            <div class="qamera-output__actions">
                                <button type="button" class="qamera-btn qamera-btn--accept"
                                        data-action="accept-session"
                                        data-job-id="{$session.job_id|escape:'htmlall':'UTF-8'}">
                                    {l s='Zatwierdź' mod='qameraai'}
                                </button>
                                <button type="button" class="qamera-btn qamera-btn--reject"
                                        data-vote="reject"
                                        data-job-id="{$session.job_id|escape:'htmlall':'UTF-8'}">
                                    {l s='Odrzuć' mod='qameraai'}
                                </button>
                            </div>
                        {/if}
                    </figure>
                {/foreach}
            </div>
        </div>
    {/if}
</figure>
