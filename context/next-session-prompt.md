# Prompt — następna sesja (M4 + trim M3)

> Wygenerowany 2026-06-11. Skopiuj treść poniżej do nowej sesji Claude Code.

---

Pracujemy nad modułem PrestaShop `qameraai` (Qamera AI). Przeczytaj `CLAUDE.md`, `prd.md` §3–§5, `goals.md` (M3/M4 + log decyzji z 2026-06-11). M1–M3 puszczone, robimy M4 + trim M3 jednym przebiegiem.

**Decyzje z poprzedniej sesji (już w prd.md/goals.md):**
1. Parametry stylu (preset/model/sceneria/proporcje/kontekst) + `count` należą TYLKO do sesji, nie packshota.
2. Packshot generuje się bez parametrów: 1 sztuka.
3. Model AI: jeden, w konfiguracji modułu (`QAMERA_AI_MODEL`), wspólny dla packshota i sesji. Generator bez dropdownu AI.
4. Sesję odpala przycisk „Generuj sesję" na każdym packshocie (zatwierdzonym i bezpośrednim), zlecając z parametrami ze stałego panelu po lewej (jedno źródło prawdy).

**Zadanie (jeden przebieg):**

A. **Trim M3** — zdejmij z generacji packshota parametry stylu + count + ai_model z POST:
   - `qamera-product.js` `handleGeneratePackshot` (~184-194): wysyłaj tylko `id_product` + `id_image`.
   - `AdminQameraAjaxController::ajaxProcessGeneratePackshot`: usuń odczyt count/ai_model/style z POST; `count=1` na sztywno; model AI czytaj z `Configuration::get('QAMERA_AI_MODEL')`; `buildSessionConfig()` nie stosuj do packshota.

B. **Konfiguracja** — dodaj pole „Model AI" w `getContent()` modułu (`QAMERA_AI_MODEL`), lista z cache ai-models filtrowana `output_type=image`. Zapis + walidacja.

C. **M4 Flow B** — sesja + publikacja:
   - Przycisk „Generuj sesję" na każdym packshocie w `_packshot.tpl` (accepted i bezpośrednim).
   - Akcja AJAX `generateSession`: submit `job_type=photo_shoot` z `session_config` (preset/model/scenery/aspect/count/suggestions z lewego panelu) + `subjects` (packshot_asset_id), model AI z config. `Idempotency-Key`. Walidacja: źródło to packshot (reguła twarda §3).
   - Zapis `order_id` → `ps_qamera_order`.
   - Polling N placeholderów (count) → realne zdjęcia, accept/reject per zdjęcie (job-level voting).
   - Import zatwierdzonej sesji → `ps_image` galerii produktu, dedup przez `ps_qamera_import` (sha output). Storefront.

D. Lewy panel `product-tab.tpl`: usuń dropdown „Model AI". Popraw stale copy empty-state (linia ~164 „Wgraj zdjęcie" → galeria PS).

**Twarde reguły:** sprawdzaj kontrakt w OpenAPI (`C:\Projects\saas-platform\apps\web\public\openapi\plugin-v1.yaml`) — nie zgaduj payloadu. PHP 7.4-compat. Thin-B (bez duplikacji stanu — stan z API). Lint `php -l` (ścieżka w CLAUDE.md). Smoke na PS8 (docker `--profile ps8`, :8082) gdy gotowe. Commit bezpośrednio na `main`, dopisz linię decyzji do `goals.md`. Stan pusty + błędu obsłuż. Brand teal `#83babc`, Inter.

Zacznij od przeczytania OpenAPI (kształt `photo_shoot` submit + outputs) i obecnego `qameraai.php` `getContent()` + `QameraApiClient`, potem plan, potem kod.
