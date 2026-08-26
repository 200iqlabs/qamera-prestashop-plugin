# Qamera AI for PrestaShop

A thin wrapper around the Qamera AI API. From the product page in the
PrestaShop back office you generate **packshots** and **product sessions**, and publish the
approved results straight into the product gallery — without leaving the admin.

The Qamera API is the source of truth for generation state (roles, status, approval,
lineage) — **not** this module's database. Only ID mappings are stored locally ("Thin-B").

- **Compatibility:** PrestaShop **8.x** (PHP 7.4+) and **9.x** (PHP 8.1+) — one module.
- **Module slug:** `qameraai`
- **Interface languages:** Polish (primary) + English (`translations/pl.php`, `translations/en.php`).
- **Licence:** Academic Free License 3.0 (AFL-3.0) — see `LICENSE.md`.

---

## Requirements

- PrestaShop 8.0+ or 9.x.
- PHP 7.4 or newer with the **cURL** extension (required to reach the API).
- A Qamera AI account with an active API key (`mk_live_...`).

---

## Installation

1. Download the `qameraai.zip` package (see *Building the ZIP package*) or archive the
   `qameraai/` directory yourself.
2. In the PrestaShop back office: **Modules → Module Manager → Upload a module**, then pick
   `qameraai.zip`.
   - Alternatively copy the `qameraai/` directory into the shop's `modules/` and click **Install**.
3. On install the module:
   - creates two ID-mapping tables (`ps_qamera_order`, `ps_qamera_import`),
   - registers a hidden admin tab for its AJAX controller,
   - hooks into `displayAdminProductsExtra` (the tab on the product page).

---

## Where to get the API key

1. Sign in to your Qamera AI account.
2. Open the account / integrations settings and generate a **plugin API key**.
3. The key looks like `mk_live_<keyId>.<secret>`. Copy the whole thing.
4. **Do not share** the key and do not commit it — it lives only in the shop configuration
   (`ps_configuration`), never in the module's files.

---

## Configuration

Go to **Modules → Module Manager → Qamera AI → Configure**:

1. **API key** — paste the `mk_live_...` key. Once saved, the module fetches the account
   status and credit balance.
2. **API base URL** — the production address by default. Change it **only** for a
   dev/local environment.
3. **Default preset** — an optional session preset (the list appears once a valid key is saved).
4. **AI model** — one model shared by packshots and sessions. **Required** — generation is
   blocked without it.

With a valid key saved, the panel shows **Account**, **Plan** and **Credit balance**. Every
failure (missing or invalid key, no credits, rate limit, server error, no connection) is
surfaced as a readable message — the module never renders a blank screen.

---

## End-to-end flow (Core Flow)

Everything happens on the product page, under the **Qamera AI** tab:

1. **Add product photos** the usual way (the *Images* tab in PrestaShop). Those are the source.
2. **Pick a photo** from the gallery and add it either as a **source** (for generation) or
   directly as a finished **packshot**.
3. **Generate a packshot** from the source photo. The module uploads the file to Qamera, waits
   for the analysis, and submits the generation — synchronously, with the result delivered by
   polling (no cron, no queue).
4. **Accept or reject** the generated packshot. Rejecting removes it from the catalogue.
5. **Generate a session** from an accepted packshot, using the settings in the panel (preset,
   model/mannequin, scenery, aspect ratio, image count, context).
   - Hard rule: **a session always comes from a packshot, never straight from a source photo.**
6. **Accept** the session results you want — accepted images are **published into the product
   gallery** (deduplicated by SHA-256, so accepting again never creates a duplicate) and show
   up on the storefront.

---

## Error handling

Every API call path maps failures to a readable admin message:

| Situation | Message |
|---|---|
| Missing / invalid API key | "No API key…" / "Invalid or unauthorised API key…" |
| No credits (402) | "No credits left on the Qamera AI account." |
| Rate limit (429) | "Too many requests to Qamera AI. Try again in a moment." |
| Server error (5xx) | "Qamera AI server error. Try again later." |
| No connection / timeout | "Cannot reach Qamera AI: …" |

All of these have Polish and English translations (`translations/`).

---

## Translations

- `translations/pl.php` — Polish (primary interface language).
- `translations/en.php` — English (fallback).

Both use PrestaShop's classic `$_MODULE` format and cover the settings page, both Smarty
templates, the AJAX controller's error responses, the API client's messages **and the strings
rendered in the browser** (button labels, progress messages, confirmations) — see below.

---

## Building the ZIP package

From the repository root:

```bash
php tools/build-zip.php
```

The package must contain the `qameraai/` directory at the top level (PrestaShop requires the
directory name inside the archive to match the module slug). `config_*.xml` is excluded — that
is PrestaShop's per-installation cache of the module description, not source (`config.xml`
without a suffix stays).

---

## Scope (deliberate MVP boundaries)

In scope: photo → packshot → session → publishing approved results into the gallery, entirely
from the product page.

Out of scope: webhooks, an own queue/cron, bulk generation across many products, product
combinations, multistore, full i18n (PL+EN only), editing/regenerating/cloning results.

---

## JavaScript strings (i18n)

Labels, progress messages and confirmations rendered in the browser
(`views/js/qamera-product.js`) **are translated PL/EN**. The `product-tab.tpl` template emits a
JSON payload (`<script type="application/json" id="qamera-i18n">`) in which every string goes
through PrestaShop's translation system (`{l s='…' mod='qameraai' js=1}`, domain `product-tab`).
The script reads that payload (`JSON.parse`) and substitutes the texts; with no payload the
`t()` helper falls back to the Polish source, so the interface never renders empty labels. When
adding a new JS string: add the key to the payload in `product-tab.tpl` and an entry to
`translations/pl.php` + `translations/en.php` (domain `product-tab`, key = `md5` of the Polish
source).
