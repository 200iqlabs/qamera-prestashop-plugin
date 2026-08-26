{*
 * Copyright since 2026 200IQ Labs and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 *
 * @author    Qamera AI
 * @copyright Since 2026 200IQ Labs
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
{*
 * Account status panel on the module settings page: which Qamera account the
 * stored API key belongs to, its plan, and the credit balance behind every
 * generation. Values come from GET /me.
 *}
<div class="panel">
    <h3><i class="icon icon-qrcode"></i> {l s='Konto Qamera AI' mod='qameraai'}</h3>
    <p><strong>{l s='Konto:' mod='qameraai'}</strong> {$qamera_account|escape:'htmlall':'UTF-8'}</p>
    <p><strong>{l s='Plan:' mod='qameraai'}</strong> {$qamera_plan|escape:'htmlall':'UTF-8'}</p>
    <p><strong>{l s='Saldo kredytów:' mod='qameraai'}</strong> {$qamera_credits|escape:'htmlall':'UTF-8'}</p>
</div>
