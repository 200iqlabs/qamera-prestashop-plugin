# Qamera AI for PrestaShop — kompletny opis procesu

> Jeden plik = pełny obraz tego, co moduł realizuje **dziś** (zaimplementowane M1–M4).
> Źródło prawdy o regułach: `CLAUDE.md` + `prd.md` §3. Milestone'y + log decyzji: `goals.md`.
> Kontrakt API: OpenAPI `C:\Projects\saas-platform\apps\web\public\openapi\plugin-v1.yaml`.

---

## 1. Idea w jednym zdaniu

Cienki wrapper na API Qamera AI: merchant z karty produktu PrestaShop generuje **packshoty**
i **sesje produktowe**, zatwierdza je i publikuje w galerii produktu — bez opuszczania panelu.
**Stan generacji (role, status, akceptacja, lineage) żyje w API Qamery, nie w bazie wtyczki** (Thin-B).

## 2. Zasada twarda (§3 prd.md)

```
zdjęcie produktu → packshot → sesja → publikacja
```

**Sesja zawsze powstaje z packshota, nigdy wprost ze zdjęcia.** Packshot bezpośredni
(„Dodaj jako packshot") jest auto-zatwierdzony i też może być źródłem sesji. Z packshota nie
generuje się kolejnego packshota.

## 3. Konfiguracja modułu (`getContent()`)

Ekran: Moduły → Qamera AI → Konfiguruj. Pola (zapis do `ps_configuration`):

| Klucz | Opis |
|---|---|
| `QAMERA_API_KEY` | Klucz API (`mk_live_<keyId>.<secret>`). Po zapisaniu → status konta + saldo z `GET /me`. |
| `QAMERA_API_BASE` | Base URL API. Domyślnie `https://qamera.ai`; override dla dev/local. |
| `QAMERA_DEFAULT_PRESET_ID` | Domyślny preset (lista z `GET /presets`). |
| `QAMERA_AI_MODEL` | **Model AI** — jeden, wspólny dla packshota i sesji. Lista z `GET /ai-models` filtrowana do `output_type=image`. Wymagany do generacji — bez niego generacja zablokowana komunikatem. |

Klucz API **nigdy** nie trafia do przeglądarki — wszystkie wywołania API idą przez serwerowy
proxy `AdminQameraAjaxController`.

## 4. Układ zakładki produktu (`displayAdminProductsExtra`)

Trzy sekcje pod sobą (pionowo):

```
1. Zdjęcia produktu     ← galeria PS = źródła (wybieralne miniatury)
2. Ustawienia sesji     ← preset / model / sceneria / proporcje / liczba / kontekst
3. Zdjęcia platformy     ← stan z API: zdjęcie → packshoty → sesje
       a) Zdjęcie (źródło zarejestrowane na platformie) + [Generuj packshot]
          b) Packshot (wiersz: podgląd+akcje po lewej, zdjęcia sesji rzędem po prawej)
             c) Zdjęcie sesji (Zatwierdź → galeria / Odrzuć → usuń)
```

- **Sekcja 1** pokazuje wszystkie zdjęcia galerii produktu **oprócz** zdjęć zaimportowanych
  przez moduł z sesji (te są w `ps_qamera_import` — publikacje, nie kandydaci źródła).
  Każde niezarejestrowane zdjęcie ma dwie opcje: „Dodaj jako zdjęcie produktu" /
  „Dodaj jako packshot". Bez przycisku generacji.
- **Sekcja 3** to obraz stanu z API. „Generuj packshot" jest tylko tutaj, na kafelku zdjęcia
  zarejestrowanego jako źródło (3a). Packshot bezpośredni pojawia się jako 3a+3b bez przycisku
  generacji.
- Klik w dowolną miniaturę → **lightbox** z pełnym zdjęciem (zamknięcie klik/Esc).
- Stan pusty i stan błędu katalogu/API obsłużone (czytelny komunikat, nie biały ekran).

## 5. Flow A — zdjęcie → packshot

Akcja AJAX `generatePackshot` (`AdminQameraAjaxController::ajaxProcessGeneratePackshot`):

1. JS wysyła **tylko** `id_product` + `id_image` (źródło z galerii PS; bez parametrów stylu).
2. Serwer: model AI z `QAMERA_AI_MODEL`; jeśli pusty → błąd „Skonfiguruj model AI…".
3. **Ensure asset w katalogu**: upload pliku galerii (`POST /assets/upload`), dedup po sha256
   (cache `asset_id` w Configuration), rejestracja `POST /packshots` (`auto_register_packshot`).
4. **Czekaj na analizę** zdjęcia źródłowego: poll `GET /products/{ref}` aż
   `analysis_status='described'` (Gemini). Gdy okno requestu za krótkie → JS retry na
   `code=analysis_pending` (asset cached, re-check tani).
5. **Submit** `POST /jobs` z `job_type=packshot`, `images_count=1`, pusty `session_config`,
   `Idempotency-Key`. Zwraca `job_id`.
6. JS: natychmiast placeholder w kafelku tego zdjęcia (3b), polling `GET /jobs/{id}` co 3 s
   (max 5 min) → po `completed` render packshota z podpisanym URL.
7. **Accept/Reject** (`/jobs/{id}/accept|reject`): accept zatwierdza packshot (odblokowuje sesje)
   i chowa przyciski Zatwierdź/Odrzuć, pokazuje „Generuj sesję"; reject usuwa packshot
   (`DELETE /packshots/{idOrRef}`).

## 6. Flow B — packshot → sesja → publikacja

### 6a. Zlecenie sesji — `generateSession`

1. JS z packshota (3b) wysyła `packshot_asset_id` + parametry z panelu „Ustawienia sesji"
   (`preset_id`, `model_id`, `scenery_id`, `aspect_ratio`, `suggestions`) + `count` (1-10).
2. Serwer: model AI z `QAMERA_AI_MODEL`. **Walidacja twardej reguły** — `assertProductPackshot`:
   gdy `packshot_asset_id` podany, musi być packshotem tego produktu (inaczej blokada
   „Sesję można zlecić tylko z packshotu…"). Gdy pominięty → backend resolwuje ostatni
   zatwierdzony packshot produktu.
3. **Submit** `POST /jobs` z `job_type=photo_shoot`, `session_config` (z panelu) + jeden
   `subject` (`product_ref`, `product_label`, `images_count=count`, `ai_model`,
   `packshot_asset_id`), `Idempotency-Key`. Odpowiedź: `order_id` + `job_ids[]` (jeden job na zdjęcie).
4. `order_id` zapisany do `ps_qamera_order` (mapowanie produkt → sesja).
5. JS: N placeholderów (wg `count`) w wierszu packshota; polling każdego `job_id`
   (`GET /jobs/{id}`) → po `completed` realne zdjęcie. Każdy job = 1 zdjęcie z własnym `voting`.

### 6b. Akceptacja + publikacja — `acceptSession`

Na zdjęciu sesji:

- **Zatwierdź** (`ajaxProcessAcceptSession`): `POST /jobs/{id}/accept` → `GET /jobs/{id}`
  (URL outputu) → **import do galerii**: pobranie pliku (podpisany URL), dedup po sha256
  w `ps_qamera_import`, utworzenie `ps_image` (`ImageManager::resize` + wszystkie thumbnaile
  + `associateTo` sklep = storefront). Zwraca `id_image` + `duplicate`.
- **Odrzuć** (`/jobs/{id}/reject`): zapis odrzucenia, kafelek znika (nic nie było w galerii).

Dedup po sha gwarantuje: ponowna akceptacja / odświeżenie strony **nie tworzy duplikatu**.

## 7. Model danych (Thin-B, §4 prd.md)

**Configuration** (`ps_configuration`): klucze z sekcji 3 + cache katalogu
(`QAMERA_CACHE_*`, 15 min: presets/models/sceneries/ai-models) + cache `asset_id`
(`QAMERA_ASSET_*`, dedup uploadu po sha256).

**Tabele lokalne** (tylko ID, zero duplikacji stanu):

| Tabela | Po co | Kolumny |
|---|---|---|
| `ps_qamera_order` | mapowanie produkt → sesja (`GET /jobs` nie filtruje po `product_ref`) | `id_product`, `qamera_order_id`, `date_add` |
| `ps_qamera_import` | dedup importu sesji do galerii + wykluczenie outputów z listy źródeł | `id_product`, `id_image`, `qamera_job_id`, `output_sha` (unikat), `date_add` |

**Mapowanie sklep ↔ Qamera** (stabilny `external_ref`):
- produkt: `ps-{id_product}`
- zdjęcie źródłowe: `ps-{id_product}-img-{id_image}` (wiąże rejestrację z `ps_image`, pozwala
  odtworzyć miniaturę przez `getImageLink`).

Wszystko inne (role, voting/approved, lineage packshot→zdjęcie i sesja→packshot, asset_id, sha)
pochodzi z API: `GET /products/{ref}` (embedded images+packshots z voting),
`GET /jobs/{id}` (outputs+voting+status), `GET /jobs` (lista).

## 8. Endpointy API (kontrakt: OpenAPI plugin-v1)

| Endpoint | Użycie w module |
|---|---|
| `GET /me` | status konta + saldo (ekran konfiguracji) |
| `GET /presets` `/models` `/sceneries` `/ai-models` | katalog dropdownów (cache 15 min); ai-models → `output_type=image` |
| `POST /assets/upload` | upload bajtów zdjęcia (multipart), zwraca `asset_id` |
| `POST /images` | rejestracja zdjęcia jako źródło |
| `POST /packshots` | rejestracja packshota (bezpośrednio lub `auto_register_packshot` z joba) |
| `DELETE /packshots/{idOrRef}` | usunięcie packshota (reject / „Usuń") |
| `GET /products/{ref}` | stan produktu: images[]+packshots[] z voting/lineage; analiza zdjęcia |
| `POST /jobs` | submit `packshot` (Flow A) / `photo_shoot` (Flow B); `Idempotency-Key` |
| `GET /jobs` / `GET /jobs/{id}` | lista sesji (scope po `product_ref`) / polling pojedynczego joba |
| `POST /jobs/{id}/accept` `/reject` | voting (accept packshota = zatwierdzenie; accept sesji = + import) |

Auth: nagłówek `X-Api-Key`. Koperta błędu: `{ error: { code, message_i18n } }`.
Mutacje: `Idempotency-Key` (bezpieczny retry, brak podwójnych obciążeń).

## 9. Pliki

```
qameraai/
├─ qameraai.php                          Module: install/uninstall, getContent (config),
│                                        hook displayAdminProductsExtra, build widoku z API,
│                                        loadGalleryImages (wyklucza importy), loadCatalog (cache)
├─ classes/
│  ├─ QameraApiClient.php                HTTP wrapper (X-Api-Key, error envelope, multipart)
│  └─ QameraCatalogCache.php             cache katalogu 15 min (Configuration-backed)
├─ controllers/admin/
│  └─ AdminQameraAjaxController.php       proxy AJAX: generatePackshot, generateSession,
│                                        getJob, acceptJob/rejectJob, acceptSession (+import),
│                                        registerImage/registerPackshot, deletePackshot
└─ views/
   ├─ templates/hook/product-tab.tpl     3 sekcje (źródła / ustawienia / platforma)
   ├─ templates/hook/_packshot.tpl       kafelek packshota (wiersz: __main + sesje)
   ├─ js/qamera-product.js               polling, live injection, accept/reject, lightbox
   └─ css/qamera-admin.css               brand: grafit #252b30, teal #83babc, Inter
```

## 10. Async / limity

- **Brak** crona/kolejki/webhooka (MVP). Upload+submit synchronicznie w AJAX; wynik przez polling.
- Polling: 3 s interwał / 5 min max (JS). Analiza zdjęcia: retry na `analysis_pending`.
- Każdy job `photo_shoot` = 1 zdjęcie z własnym votingiem (voting na poziomie joba, nie outputu).

## 11. Poza zakresem (§6 prd.md)

Webhook, własna kolejka/cron, bulk wielu produktów, warianty (combinations), multistore,
pełne i18n (tylko PL+EN), edycja/regeneracja/klonowanie wyników, pełna duplikacja stanu (Heavy).

## 12. Stan / do zrobienia

- M1–M4 zaimplementowane; Flow A i Flow B zwalidowane **live na PS8**.
- **Do zrobienia:** kompletne testy e2e przebudowanego UI; **M5** — PS9 (`:8091`),
  hardening błędów API (4xx/5xx, brak kredytów, rate limit), i18n PL+EN, README, ZIP dystrybucyjny.
