# Reguły projektu — Qamera AI for PrestaShop

> Ten plik czyta agent przy KAŻDEJ sesji. Dwie warstwy:
> 1. **Struktura repo** (poniżej — zostaw, opisuje gdzie co leży)
> 2. **Konstytucja projektu** (sekcja na dole — wygenerujesz ją komendą `/reguly` z `prd.md` + `brand.md`)

## Struktura repozytorium (gdzie szukać kontekstu)

- `context/w1/` — artefakty Week 1: walidacja problemu (BHC, deep analysis, notatki z wywiadów Mom Test)
- `context/w2/` — artefakty Week 2: micro-ICP, positioning, Customer Insights Playbook, atomy komunikacji, Landing Page Brief
- `context/brand.md` — brand reference (HEX-y, fonty, voice & tone). Stosuj w KAŻDYM ekranie.
- `prd.md` — żywy dokument: CO budujemy, JEDEN Core Flow (§3). Wracasz przy każdej decyzji.
- `goals.md` — milestone'y M1–M5 + log decyzji.

Gdy budujesz UI — czytaj `context/brand.md` i stosuj wartości stamtąd. Gdy decydujesz o zakresie — czytaj `prd.md` §3 (Core Flow) i §6 (Out of scope). Nie buduj nic spoza Core Flow.

## Konwencje techniczne (domyślne — nadpisze je `/reguly`)

- Język UI: {polski / angielski — ustal}
- Responsywność: od 375px wzwyż
- Każdy widok z danymi: obsłuż stan pusty i stan błędu
- Nie proponuj zmiany stacku ani nowych bibliotek bez pytania

---

## Konstytucja projektu

### Czym jest ten projekt
Moduł PrestaShop „Qamera AI for PrestaShop" — cienki wrapper na API Qamera AI. Merchant z karty produktu
generuje packshoty i sesje produktowe Qamera AI i publikuje zatwierdzone wyniki w galerii produktu, bez
opuszczania panelu. Źródłem prawdy o stanie generacji (role, status, akceptacja, lineage) jest API Qamery,
NIE baza wtyczki. Portujemy proces z istniejącej wtyczki WooCommerce (`C:\Projects\qamera-woocommerce`),
NIE jej architekturę (WooCommerce duplikuje stan w post_meta — my nie).

### Stack (zalockowany — nie zmieniaj)
- PrestaShop **8.x (PHP 7.4+) ORAZ 9.x (PHP 8.1+)** — jeden moduł, kompatybilność wsteczna. PHP zgodny z 7.4.
- Slug modułu: `qameraai`. Widoki: Smarty (`.tpl`). HTTP: cURL, nagłówek `X-Api-Key`, baza `/api/v1/plugin/*`.
- Async: **brak** — upload+submit synchronicznie w AJAX; wynik przez **polling** `GET /jobs/{id}`. Bez crona, bez kolejki, bez webhooka (MVP).
- Stan lokalny: **Thin-B** — Configuration + 2 tabele tylko z ID (`ps_qamera_order`, `ps_qamera_import`). Reszta z API.
- UI: zakładka `displayAdminProductsExtra`, settings w `getContent()`.
- **Nie proponuj zmiany stacku ani dodatkowych bibliotek bez pytania.**

### Dokumentacja API Qamera (kontrakt — źródło prawdy endpointów)
- OpenAPI 3.1 (YAML): https://qamera.ai/openapi/plugin-v1.yaml
- Interaktywna (Redoc): https://redocly.github.io/redoc/?url=https://qamera.ai/openapi/plugin-v1.yaml
- Lokalne źródło (najszybsze, czytaj stąd): `C:\Projects\saas-platform\apps\web\public\openapi\plugin-v1.yaml`
- Strona docs: `C:\Projects\saas-platform\apps\web\content\documentation\plugin-api\plugin-api.mdoc`
- Referencja implementacji (port): wtyczka WooCommerce `C:\Projects\qamera-woocommerce`.
- **Przy pracy z endpointami sprawdzaj kontrakt w OpenAPI, nie zgaduj kształtu payloadu/odpowiedzi.**

### Dokumentacja PrestaShop (dev docs — celuj w kompat 8 + 9)
- PS 8 (kompatybilność wsteczna — bazowa): https://devdocs.prestashop-project.org/8/
- PS 9 (najnowsza): https://devdocs.prestashop-project.org/9/
- Moduły: https://devdocs.prestashop-project.org/8/modules/ · Hooki: https://devdocs.prestashop-project.org/8/modules/concepts/hooks/
- **Sprawdzaj różnice 8↔9 w devdocs zanim użyjesz API, które mogło się zmienić (hooki, ObjectModel, kontrolery).**

### Brand (stosuj w KAŻDYM ekranie — pełny: `context/brand.md`)
- Kolory: grafit `#252b30` (nagłówki), **akcent teal `#83babc`** (przyciski primary, aktywna zakładka, plakietki ról), biały `#ffffff`. UI light: bg `#ffffff`, tekst `#1a1a1a`, border `#f3f4f6`, input `#e5e7eb`.
- Font: **Inter** (400/500/600/700). Tokeny: button 6px/36px, CTA 12px/48px, card 16px, input 8px.
- Zasady UI: zdjęcia produktów = bohater ekranu (pełna rozdzielczość + podgląd); workflow „wybierz i zatwierdź" bez żargonu AI; ton bezpośredni i konkretny.
- Zakazane: fioletowe gradienty / generic AI-slop; zwroty „game changer", „rewolucja" (bez danych).
- **Każdy nowy ekran/komponent używa tych wartości. Nie wymyślaj własnych kolorów ani fontów.**

### Zakres — twarde granice
Core Flow (§3 PRD): ze zdjęcia produktu → packshot → sesja → publikacja zatwierdzonych w galerii produktu,
w całości z karty produktu PrestaShop. Reguła twarda: **sesja zawsze z packshota, nigdy wprost ze zdjęcia.**

POZA zakresem (§6 PRD): webhook (HMAC/publiczny endpoint), własna kolejka/cron, bulk-generacja wielu produktów,
warianty produktu (combinations), multistore, pełne i18n (tylko PL+EN), edycja/regeneracja/klonowanie wyników,
pełna duplikacja stanu lokalnie (model Heavy).

**Nie dodawaj funkcji spoza Core Flow, nawet jeśli wydają się przydatne. Jeśli czegoś brakuje — zapytaj, nie buduj.**

### Protokół decyzji
- **ZATRZYMAJ SIĘ i zapytaj** przy tematach z §7 PRD: prefiks `external_ref` przy multistore; obsługa
  `analysis_status='described'` przed sesją; czy dopuszczać bezpośredni packshot z galerii; limit pollingu
  (default 3s/5min); środowisko dev (prod `https://qamera.ai` vs local override `QAMERA_API_BASE`).
- Decyzje techniczne w ramach stacka (struktura kodu, algorytmy, kolejność kroków w milestonie): podejmuj sam.
- Treści i copy: używaj języka z `prd.md` / `context/brand.md` (zwalidowany). Nie generuj własnego marketingu.

### Konwencje
- Język UI: **polski** (primary) + **angielski** (fallback) przez system tłumaczeń PrestaShop.
- Każdy widok z danymi: obsłuż **stan pusty** i **stan błędu** (błąd API = czytelny komunikat, nie biały ekran).
- Mutacje do API: `Idempotency-Key`. Błędy API w kopercie `{ error: { code, message_i18n } }`.
- Pracuj milestone'ami z `goals.md` (M1→M5); nie ruszaj następnego zanim DoD poprzedniego nie przejdzie.
- Dopisuj 1 linię decyzji na sesję do `goals.md` (log = pamięć projektu).

### Git
- **Commituj bezpośrednio na `main`. NIE twórz branchy** (nadpisuje domyślne zachowanie agenta). Bez PR-flow.

### Klucz API i sekrety
- Runtime: klucz API żyje w `ps_configuration` (ekran ustawień modułu), NIE w `.env`.
- `.env.example` = szablon dla dev/smoke-test; kopiujesz do `.env` (w `.gitignore`).
- **NIGDY nie commituj `.env` ani klucza `mk_live_...`** do repo.

### Środowisko dev / test (jak uruchomić, nie odkrywaj od nowa)
- **PrestaShop przez Docker** (`docker-compose.yml`, profile):
  - `docker compose --profile ps8 up -d` → PS8 na `http://localhost:8082` (8081 bywa zajęty).
  - `docker compose --profile ps9 up -d` → PS9 na `http://localhost:8091`.
  - Admin: `/admin-dev`, login `admin@qamera.test` / hasło `qameraadmin1`.
  - Moduł montowany live z `./qameraai` → zmiany w kodzie widać po odświeżeniu (czasem wyczyść cache PS).
  - Reset: `docker compose --profile ps8 down -v` (kasuje bazę + pliki sklepu).
- **PHP CLI** (winget, brak na PATH bieżącej sesji): `C:\Users\pawel\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.1_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe`.
  - `php -l <plik>` do lintu. CLI nie ma włączonego curl/openssl ani CA — dla skryptów sieciowych dodaj flagi:
    `-d extension_dir="<base>\ext" -d extension=curl -d extension=openssl -d curl.cainfo="tools\cacert.pem"`.
- **Smoke / probe API** (`tools/`, poza modułem, NIE shippowane): `tools/smoke-api.php` (`/me` + `/presets`),
  `tools/probe-m2.php` (`/jobs`, `/products/{ref}`). Czytają klucz z `.env`. `tools/cacert.pem` w `.gitignore`.
- `AGENTS.md` tylko wskazuje na ten plik (jedna prawda) — nie duplikuj tu treści tam.
