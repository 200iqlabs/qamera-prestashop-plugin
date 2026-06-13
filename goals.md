# goals.md — Qamera AI for PrestaShop

> Rozbicie `prd.md` §3 (Core Flow) na M1–M5. Każdy milestone ma binarny DoD. Nie ruszasz następnego,
> zanim poprzedni nie przejdzie. Wersja 1.0 · 2026-06-10

## Milestone'y

### M1 — Fundament modułu
**Obejmuje:** szkielet modułu `qameraai` (klasa `Module`, `install`/`uninstall`, `config.xml`),
2 tabele z §4 (`ps_qamera_order`, `ps_qamera_import`), Configuration (klucz API, base URL, default preset),
strona settings (`getContent`), `QameraApiClient` z `GET /me` + `GET /presets`. Kompatybilność PS8/PS9.

**Zrobione, gdy:**
- [ ] Moduł instaluje się i odinstalowuje czysto na PS8 i PS9 (tabele tworzone/usuwane).
- [ ] Konfiguracja: wklejenie klucza API zapisuje się; po zapisaniu widać status konta + saldo kredytów z `GET /me`.
- [ ] Dropdown "domyślny preset" zaciąga listę z `GET /presets`.
- [ ] Błąd (zły klucz) pokazuje komunikat zamiast wywalać stronę.

### M2 — Zakładka produktu + odczyt stanu z API
**Obejmuje:** hook `displayAdminProductsExtra` (zakładka "Qamera AI" na karcie produktu), enqueue JS/CSS,
render UI (port `render_dashboard` → Smarty), odczyt stanu produktu z `GET /products/{external_ref}`
(embedded images+packshots z voting) + sesje z `ps_qamera_order` → `GET /jobs`. Cache katalogu 15 min
(presets/models/sceneries/ai-models).

**Zrobione, gdy:**
- [ ] Zakładka "Qamera AI" widoczna na edycji produktu (PS8 i PS9).
- [ ] UI pokazuje zdjęcia produktu, istniejące packshoty i sesje pogrupowane (zdjęcie→packshoty→sesje), w pełni z API.
- [ ] Stan packshota (pending/accepted) i sesji odzwierciedla `voting` z API.
- [ ] Pusty stan (produkt bez nic w Qamerze) i stan błędu katalogu obsłużone.

### M3 — Flow A: zdjęcie → packshot (sync + polling)
**Obejmuje:** `AdminQameraAjaxController` akcja `generatePackshot`: ensure source w katalogu
(upload asset + register, cache asset_id, dedup sha256) → submit `job_type=packshot` (sync) → zwrot job_id.
Polling `GET /jobs/{id}` w JS. Obsługa `analysis_status` (czekaj na `described`). Accept/reject packshota
przez `/jobs/{id}/accept|reject`.

**Zrobione, gdy:**
- [ ] Klik "Generuj packshot" na zdjęciu → spinner → po pollingu pojawia się packshot bez przeładowania.
- [ ] Packshot można zatwierdzić (accept) i odrzucić (reject); stan zmienia się w UI i w API.
- [ ] Błąd uploadu/submitu/braku kredytów pokazuje czytelny komunikat od razu (sync).
- [ ] Packshot generuje się BEZ parametrów: 1 sztuka, model AI z konfiguracji modułu (decyzja 2026-06-11). Count i parametry stylu należą do sesji, nie packshota.

### M4 — Flow B: packshot → sesja + publikacja do galerii
**Obejmuje:** akcja `generate` (sesja): przycisk "Generuj sesję" na każdym packshocie
(zatwierdzonym i bezpośrednim) → zlecenie z parametrami ze stałego panelu po lewej
(preset/model/sceneria/proporcje/kontekst/count; model AI z konfiguracji modułu, NIE z panelu),
submit `job_type=photo_shoot` (`session_config`+`subjects`), zapis `order_id` do `ps_qamera_order`,
polling, accept/reject sesji, import zatwierdzonej sesji do `ps_image` galerii (z dedup `ps_qamera_import`).
Suggestions z tagów+kategorii+tekstu.
**Trim M3 (przy okazji):** zdjąć z generacji packshota parametry stylu + count + model AI z POST
(`generatePackshot`, `buildSessionConfig`, `qamera-product.js`); packshot bierze tylko źródło + model AI z config.
**Konfiguracja:** dodać pole "Model AI" w `getContent()` (`QAMERA_AI_MODEL`), lista z cache ai-models (output_type=image).

**Zrobione, gdy:**
- [x] Z zatwierdzonego/bezpośredniego packshota można zlecić sesję z parametrami z lewego panelu.
- [x] Próba sesji wprost ze zdjęcia (nie packshota) jest blokowana z komunikatem.
- [x] Wyniki sesji pojawiają się po pollingu; accept/reject działa.
- [x] Zatwierdzona sesja pojawia się jako obraz produktu w galerii (storefront), bez duplikatów po odświeżeniu.

### M5 — Hardening + pakiet dystrybucyjny
**Obejmuje:** spójna obsługa błędów API (4xx/5xx, brak klucza, brak kredytów, rate limit 429),
i18n PL+EN, smoke test end-to-end na realnym koncie Qamera na PS8 i PS9, README (instalacja + konfiguracja),
ZIP instalacyjny modułu.

**Zrobione, gdy:**
- [ ] Cały Core Flow §3 przechodzi smoke test na PS8 i PS9 (realne konto).
- [ ] Wszystkie kryteria akceptacji §3 i DoD §8 z `prd.md` ☑.
- [ ] ZIP instaluje się przez Menedżer modułów PrestaShop bez błędów.
- [ ] README opisuje: skąd klucz API, jak skonfigurować, jak wygląda flow.

## Mapowanie milestone → Core Flow (§3 prd.md)

| Milestone | Fragment Core Flow |
|---|---|
| M1 | Konfiguracja integracji (klucz API, settings) |
| M2 | Karta produktu + odczyt stanu (kroki: widok źródeł/packshotów/sesji) |
| M3 | Kroki 1–3: zdjęcie → packshot → akceptacja |
| M4 | Kroki 4–6: parametry → sesja → akceptacja → publikacja |
| M5 | Definition of Done (§8) + dystrybucja |

## Log decyzji

Dopisuj 1 linię na sesję. Format: `[data] M{N}: decyzja — dlaczego (odwołanie do §X prd.md)`.

- [2026-06-10] Plan: wrapper Thin-B (Configuration + 2 tabele ID), sync w AJAX + polling, bez webhooka w MVP, PS8+9 — bo serwer Qamery eksponuje stan przez voting+lineage, więc lokalna duplikacja zbędna (§1, §4, §5 prd.md).
- [2026-06-10] M1: szkielet modułu + QameraApiClient gotowe i zlintowane (php 8.1). Smoke test realnego API przeszedł — `GET /me` zwraca `credits_balance`/`account_name` (poprawiono parsowanie renderAccountStatus pod realny kontrakt), `GET /presets` zwraca `{presets:[{id,slug,name,...}]}`. Dodano env testowy: docker-compose (PS8 :8082, PS9 :8091, profile ps8/ps9, moduł montowany live) + tools/smoke-api.php. PS8 wstaje, moduł zamontowany, admin /admin-dev dostępny.
- [2026-06-10] M2: product-tab UI + odczyt stanu z API gotowe i zlintowane. Mapowanie zweryfikowane przeciw OpenAPI (`saas-platform/.../plugin-v1.yaml`), nie zgadywane: `GET /products/{ref}` → `ProductDetailResponse{images[],packshots[],*_truncated}`; `ProductPackshot.voting`=PackshotVoting(pending|accepted|rejected), lineage `source_image_id`/`generated_by_job_id`; `Job.voting`=Voting(accepted|rejected|null) NA POZIOMIE JOBA (nie outputu) + `product_ref` (scope sesji do produktu). Wnioski wpływające na kod: catalog rows nie mają URL — źródłowe foto z lokalnego `ps_image` (external_ref `ps-img-{id}`), packshot z `generated_by_job_id`→output.url, sesja=job→outputs[0].url; ai-models filtrowane do `output_type=image`; katalog cache 15 min (QameraCatalogCache). Probe na pustym koncie potwierdził empty-state (404 `/products`) + envelope `/jobs`. Realny render z danymi do potwierdzenia w M3 (§3, §4 prd.md).
- [2026-06-11] Doprecyzowanie M3/M4: parametry stylu (preset/model/sceneria/proporcje/kontekst) + count należą TYLKO do sesji, nie packshota; packshot = 1 sztuka bez parametrów; model AI jeden, w konfiguracji modułu (`QAMERA_AI_MODEL`), wspólny packshot+sesja, generator bez dropdownu AI; sesję odpala przycisk "Generuj sesję" na packshocie z params ze stałego panelu po lewej (jedno źródło prawdy). Skutek: trim shipped M3 + budowa M4 jednym przebiegiem (§3, §4, §5 prd.md).
- [2026-06-12] M4 + trim M3: zbudowane jednym przebiegiem. Trim M3: `generatePackshot` nie czyta już count/ai_model/style z POST — packshot=1 sztuka, model AI z `Configuration::get('QAMERA_AI_MODEL')`, pusty session_config; `qamera-product.js` wysyła tylko id_product+id_image. Konfiguracja: pole „Model AI" w `getContent()` (`QAMERA_AI_MODEL`), lista z cache ai-models filtrowana output_type=image, save+walidacja (warning gdy puste). M4 Flow B: akcja `generateSession` (submit `job_type=photo_shoot`, session_config z lewego panelu + subject z opcjonalnym packshot_asset_id, Idempotency-Key, twarda reguła §3 — `assertProductPackshot` blokuje sesję spoza packshota, zapis order_id→ps_qamera_order); JS N placeholderów wg count→poll per-job→accept/reject per zdjęcie (job-level voting); akcja `acceptSession` = accept_job + import outputu do `ps_image` galerii (download podpisanego URL, ImageManager::resize + thumbnaile + associateTo shop = storefront), dedup po sha256 outputu w `ps_qamera_import`. Kontrakt zweryfikowany w OpenAPI (SessionConfig{model_id,scenery_id,preset_id,aspect_ratio,suggestions}; Subject{product_ref,product_label,images_count,ai_model,packshot_asset_id?}; photo_shoot bez packshot_asset_id→backend resolwuje ostatni accepted; response subjects[].job_ids[] = 1 job/zdjęcie). Render-smoke na PS8 (`tools/smoke-m4-render.py`): config Model AI select (3 modele image, brak fatala), tab bez `#qamera-ai-model`, przycisk „Generuj sesję" obecny, asset css/js 200. php -l + node --check czyste. Live Flow B (§3, §4 prd.md).
- [2026-06-13] M4 UX rework po testach manualnych (produkt 20): output sesji wykluczony z listy źródeł (`importedImageIds` z `ps_qamera_import`); układ pionowy 3 sekcje (Zdjęcia produktu → Ustawienia sesji → Zdjęcia platformy); „Generuj packshot" tylko na kafelku platformy (3a), nie w galerii; live injection kontenera 3a/3b po rejestracji bez reload (direct packshot bez „Generuj packshot" — packshot z packshota bez sensu); placeholder nowego packshota w kontenerze źródła (usunięta sekcja „Nowy packshot"); przyciski packshota pionowo + szerszy kafel; po accept packshota znikają Zatwierdź/Odrzuć; pending packshoty mają accept/reject też po reload (`mapPackshot.job_id`); każdy packshot = wiersz (podgląd lewo, zdjęcia sesji rzędem prawo via `.qamera-packshot__main`); lightbox pełnego zdjęcia po kliknięciu miniatury. Render-smoke zielony (`tools/smoke-m4-ui.py`). Kompletne e2e do zrobienia w następnej sesji (§3 prd.md).
- [2026-06-12] M4 zwalidowany live na PS8 (`tools/flow-b.py`, count=1, model byteplus/seedream-4.5): z zatwierdzonego packshota „Generuj sesję" → `generateSession` ok (order_id 6d1027a8…, 1 job_id) → polling ~2 min do `completed` → „Zatwierdź" → `acceptSession` ok (id_image=26, duplicate=false). DB potwierdza: `ps_qamera_order` (order→produkt 20), `ps_qamera_import` (id_image 26 + output_sha + job_id), `ps_image`#26 produkt 20 poz.3, `ps_image_shop` assoc shop 1 (storefront). Pliki na dysku: `img/p/2/6/26.jpg` + wszystkie thumbnaile (cart/home/large/medium/small). Dedup po output_sha gwarantuje brak duplikatu po odświeżeniu. Bug złapany przy 1. próbie: generacja blokowana `Skonfiguruj model AI` gdy `QAMERA_AI_MODEL` puste (oczekiwane — trzeba zapisać model w konfiguracji). M4 DoD ☑. Zostaje M5 (PS9 + hardening + ZIP + README).
- [2026-06-10] M3: Flow A (zdjęcie→packshot) zwalidowany live na PS8 (realna generacja, count=1): upload→register source→czekaj `analysis_status=described`→submit `job_type=packshot`→poll `in_progress`→`completed` z podpisanym URL→render packshota z accept/reject, bez przeładowania. AdminQameraAjaxController proxuje API server-side (klucz nigdy w przeglądarce). Bugi złapane przez smoke (nie lint): (1) `jsonError()` private kolidował z public `AdminControllerCore::jsonError()` → PHP fatal, każda akcja 500 → rename `apiError()`; (2) analiza Gemini > 12s okna controllera → JS retry na `code=analysis_pending` (asset cached, re-check tani) do SUBMIT_RETRY_MAX. Fix M2 znaleziony przy okazji: strona produktu PS8/9 to Symfony route (`/sell/catalog/products-v2/{id}/edit`, brak `controller=`) → `actionAdminControllerSetMedia` nie odpalał, tab bez stylu/JS → CSS/JS inline w tpl (+guard double-init). Smoke przez Playwright (`tools/flow-a.py`, `tools/smoke-ui.py`). PS9 jeszcze nietestowany. Accept→galeria w M4 (§3 prd.md).
