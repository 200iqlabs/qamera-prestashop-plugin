# Qamera AI for PrestaShop

Moduł PrestaShop integrujący **Qamera AI** - z karty produktu generujesz packshoty i sesje
produktowe i publikujesz zatwierdzone wyniki w galerii produktu, bez opuszczania panelu.

Cienki wrapper na [API Qamera AI](https://qamera.ai). Źródłem prawdy o stanie generacji
(role, status, akceptacja, lineage) jest API Qamery, **nie** baza wtyczki.

---

## Co robi (Core Flow)

Cały przepływ z karty produktu PrestaShop (układ pionowy: **Zdjęcia produktu** → **Ustawienia sesji** → **Zdjęcia platformy**):

1. **Zdjęcie produktu** (galeria PS) → rejestracja w katalogu Qamera: „Dodaj jako zdjęcie produktu" (źródło) albo „Dodaj jako packshot" (gotowy packshot, bez generacji). Po rejestracji kafelek pojawia się od razu w sekcji „Zdjęcia platformy" (bez przeładowania).
2. **Packshot** — „Generuj packshot" na zdjęciu platformy: `job_type=packshot`, 1 sztuka, bez parametrów stylu (sync + polling). Model AI z konfiguracji modułu.
3. **Akceptacja** packshota (accept/reject) — zatwierdzony (lub bezpośredni) staje się źródłem sesji.
4. **Sesja produktowa** — „Generuj sesję" na packshocie: `job_type=photo_shoot` z parametrami z lewego/górnego panelu (preset/model manekina/sceneria/proporcje/kontekst, count 1-10). Model AI z konfiguracji (wspólny z packshotem).
5. **Publikacja** — zatwierdzone wyniki sesji trafiają do galerii produktu (`ps_image`, storefront); dedup po sha outputu.

Twarda reguła: **sesja zawsze z packshota, nigdy wprost ze zdjęcia.**

**Pełny opis procesu (krok po kroku, z endpointami i stanami):** [`docs/process.md`](docs/process.md).

> **Model AI** wybierany **raz** w konfiguracji modułu (`QAMERA_AI_MODEL`, lista z `GET /ai-models` filtrowana do `output_type=image`) — wspólny dla packshota i sesji. Generator nie ma dropdownu modelu AI. Bez ustawionego modelu generacja jest zablokowana z czytelnym komunikatem.

## Stack

- **PrestaShop 8.x (PHP 7.4+) oraz 9.x (PHP 8.1+)** - jeden moduł, kod zgodny z PHP 7.4.
- Slug: `qameraai`. Widoki: Smarty (`.tpl`). HTTP: cURL, nagłówek `X-Api-Key`, baza `/api/v1/plugin/*`.
- **Async: brak** - upload+submit synchronicznie w AJAX; wynik przez **polling** `GET /jobs/{id}`. Bez crona, kolejki, webhooka (MVP).
- Stan lokalny **Thin-B** - Configuration + 2 tabele tylko z ID (`ps_qamera_order`, `ps_qamera_import`). Reszta z API.
- UI: zakładka `displayAdminProductsExtra` na karcie produktu; ustawienia w `getContent()`.

## Architektura (gdzie co leży)

```
qameraai/
+- qameraai.php                          <- klasa Module: install/uninstall, hooki, render zakładki
+- classes/QameraApiClient.php           <- HTTP wrapper API Qamery (X-Api-Key, error envelope)
+- controllers/admin/
|  \- AdminQameraAjaxController.php       <- proxy AJAX server-side (klucz API nigdy w przeglądarce)
\- views/
   +- templates/hook/                     <- product-tab.tpl, _packshot.tpl
   +- js/qamera-product.js                <- polling, accept/reject, render bez przeładowania
   \- css/qamera-admin.css                <- style (brand: grafit #252b30, teal #83babc, Inter)
```

Klucz API żyje w `ps_configuration` (ekran ustawień modułu), **nie** w `.env`.

## Instalacja

1. Skopiuj katalog `qameraai/` do `modules/` swojego PrestaShop (albo zainstaluj ZIP przez Menedżer modułów).
2. Panel admina -> **Moduły** -> zainstaluj "Qamera AI for PrestaShop".
3. Konfiguracja modułu -> wklej **klucz API** Qamera (format `mk_live_...`).
   Klucz pobierzesz z konta Qamera AI (panel -> integracje/plugin API).
4. Po zapisaniu klucza zobaczysz status konta + saldo kredytów (`GET /me`).
5. Otwórz dowolny produkt -> zakładka **Qamera AI**.

## Środowisko dev / test

PrestaShop przez Docker (`docker-compose.yml`, profile):

```bash
docker compose --profile ps8 up -d   # PS8 -> http://localhost:8082
docker compose --profile ps9 up -d   # PS9 -> http://localhost:8091
```

- Admin: `/admin-dev`, login `admin@qamera.test` / hasło `qameraadmin1`.
- Moduł montowany live z `./qameraai` -> zmiany w kodzie widać po odświeżeniu (czasem wyczyść cache PS).
- Reset: `docker compose --profile ps8 down -v` (kasuje bazę + pliki sklepu).

**Smoke / probe API** (`tools/`, poza modułem, nie shippowane): `tools/smoke-api.php` (`/me` + `/presets`),
`tools/probe-m2.php` (`/jobs`, `/products/{ref}`), `tools/smoke-m4-ui.py` (render zakładki, bez kredytów),
`tools/flow-b.py` (Playwright E2E Flow B live — generacja sesji + publikacja, **zużywa kredyty**). Klucz czytany z `.env` (kopiuj z `.env.example`).

> **Nigdy nie commituj `.env` ani klucza `mk_live_...`.** `.env` jest w `.gitignore`.

## Status (milestone'y -> `goals.md`)

| Milestone | Zakres | Stan |
|---|---|---|
| M1 | Fundament modułu + `QameraApiClient` (`/me`, `/presets`) | ✅ |
| M2 | Zakładka produktu + odczyt stanu z API (`/products/{ref}`, `/jobs`) | ✅ |
| M3 | Flow A: zdjęcie -> packshot (sync + polling, accept/reject) | ✅ (PS8 live; PS9 do testu) |
| M4 | Flow B: packshot -> sesja + publikacja do galerii | ✅ (PS8 live; pełne e2e + PS9 do testu) |
| M5 | Hardening (błędy API, i18n PL+EN) + ZIP dystrybucyjny | ⬜ todo |

## Dokumentacja

- **API Qamera** (kontrakt): OpenAPI 3.1 - https://qamera.ai/openapi/plugin-v1.yaml |
  [Redoc](https://redocly.github.io/redoc/?url=https://qamera.ai/openapi/plugin-v1.yaml)
- **PrestaShop dev docs:** [PS8](https://devdocs.prestashop-project.org/8/) | [PS9](https://devdocs.prestashop-project.org/9/)
- Reguły projektu i konwencje: `CLAUDE.md`. Co budujemy (Core Flow §3, out-of-scope §6): `prd.md`. Milestone'y + log decyzji: `goals.md`.
