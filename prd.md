# PRD — Qamera AI for PrestaShop

> Żywy dokument. Wracasz przy każdej decyzji. CO budujemy, JEDEN Core Flow (§3), twarde granice (§6).
> Wersja 1.0 · 2026-06-10

## §1 Problem i cel

Sklepy na PrestaShop potrzebują profesjonalnych zdjęć produktowych (packshoty na czystym tle + sesje
marketingowe w stylu), ale fotografia jest droga i wolna. Qamera AI generuje je z jednego zdjęcia.

**Cel wtyczki:** konektor (wrapper) między PrestaShop a Qamera AI. Merchant z panelu admina, na karcie
produktu, generuje packshoty i sesje przez Qamera AI i publikuje zatwierdzone wyniki w galerii produktu —
bez opuszczania PrestaShop.

**Zasada architektoniczna:** wtyczka jest cienkim wrapperem. Źródłem prawdy o stanie generacji
(role obrazów, status, akceptacja, lineage) jest **API Qamery**, nie baza wtyczki. Lokalnie trzymamy
tylko: konfigurację + minimalne mapowanie ID (Thin-B, §4).

**Punkt odniesienia:** istniejąca wtyczka WooCommerce (`C:\Projects\qamera-woocommerce`) realizuje ten sam
proces. Portujemy proces, NIE architekturę (WooCommerce duplikuje stan w `post_meta`; my tego nie robimy —
serwer Qamery eksponuje ten stan przez `voting` + lineage).

## §2 Użytkownik

Administrator / manager sklepu PrestaShop z aktywnym kontem Qamera AI (klucz API + kredyty). Pracuje na
ekranie edycji produktu. Nie jest programistą — konfiguracja musi być wklejeniem klucza, bez crona/devops.

## §3 Core Flow (dokładnie 1 user story)

**Jako merchant, ze zdjęcia produktu generuję zestaw zdjęć sesyjnych Qamera AI i publikuję zatwierdzone
w galerii produktu — w całości z karty produktu PrestaShop.**

Ścieżka end-to-end (dwuetapowy pipeline, jak w WooCommerce). **Źródłem są zdjęcia, które już
są w galerii produktu PrestaShop — wtyczka NIE przyjmuje uploadu własnego pliku.** Merchant
najpierw dodaje zdjęcia do karty produktu (standardowy mechanizm PS), potem operuje na nich w
zakładce Qamera AI:

```
Karta produktu → dodaj zdjęcia do galerii (standardowy PrestaShop)
Karta produktu → zakładka "Qamera AI"
  1. Wtyczka listuje zdjęcia NALEŻĄCE do produktu (galeria PS) jako wybieralne miniatury  [źródło]
  2. Dla każdego zdjęcia dwie opcje:
       a) „Dodaj jako zdjęcie produktu"  → rejestracja POST /images   (źródło do generacji)
       b) „Dodaj jako packshot"          → rejestracja POST /packshots (gotowy packshot, bez generacji)
  3. Na zdjęciu dodanym jako „zdjęcie produktu": „Generuj packshot"
       → bez parametrów: packshot to czyste tło, generuje się 1 sztuka,
         modelem AI ustawionym w konfiguracji modułu (nie w generatorze)
       → NATYCHMIAST pojawia się puste pole z placeholderem
       → po pollingu placeholder zamienia się na realnie wygenerowany packshot
  4. Wygenerowany packshot: zatwierdź lub odrzuć
       • odrzuć  → packshot usunięty (DELETE)
       • zatwierdź → zmiana statusu na „zatwierdzony"; packshot staje się wybieralny
  5. Ustaw parametry sesji w stałym panelu (lewa kolumna) — jedno źródło prawdy:
     preset, model (manekin), sceneria, proporcje, kontekst, count 1–10.
     (Model AI NIE jest tu — pochodzi z konfiguracji modułu, wspólny dla packshota i sesji.)
  6. Na zatwierdzonym (lub bezpośrednim) packshocie: „Generuj sesję zdjęciową"
       → zlecenie z parametrami ustawionymi w panelu po lewej
       → NATYCHMIAST pojawia się N placeholderów (wg count z ustawień sesji)
       → po pollingu każdy placeholder zamienia się na realne wygenerowane zdjęcie
  7. Każde wygenerowane zdjęcie sesji: zatwierdź lub odrzuć
       • zatwierdź → zdjęcie trafia do galerii produktu PrestaShop (ps_image)   [publikacja]
       • odrzuć    → zdjęcie usunięte (DELETE)
```

Reguła twarda: **sesja zawsze powstaje z packshota, nigdy wprost ze zdjęcia.**

### Kryteria akceptacji (binarne)

- [ ] Merchant wkleja klucz API w konfiguracji modułu i widzi status konta + saldo kredytów.
- [ ] Na karcie produktu jest zakładka "Qamera AI" z UI generatora.
- [ ] Wtyczka listuje zdjęcia należące do produktu (galeria PS) jako wybieralne miniatury — bez uploadu pliku do wtyczki.
- [ ] Każde zdjęcie ma dwie opcje: „dodaj jako zdjęcie produktu" (POST /images) i „dodaj jako packshot" (POST /packshots).
- [ ] Ze zdjęcia dodanego jako „zdjęcie produktu" można wygenerować packshot; natychmiast pojawia się placeholder, który po pollingu zamienia się na wynik (bez przeładowania).
- [ ] Packshot można zatwierdzić (accept → status „zatwierdzony", wybieralny) lub odrzucić (reject → usunięcie).
- [ ] Z zatwierdzonego (lub bezpośredniego) packshota można zlecić sesję z pełnym zestawem parametrów.
- [ ] Sesja pokazuje N placeholderów wg count; po pollingu każdy zamienia się na realne zdjęcie; każde można zatwierdzić lub odrzucić.
- [ ] Zatwierdzone zdjęcie sesji trafia do galerii produktu PrestaShop (ps_image, storefront); odrzucone jest usuwane.
- [ ] Stan (role, packshoty, sesje, akceptacje) odtwarza się po przeładowaniu strony — z API Qamery.
- [ ] Działa na PrestaShop 8.x oraz 9.x.

## §4 Data model (Thin-B)

**Configuration (ps_configuration):**
- `QAMERA_API_KEY` — klucz API (format `mk_live_<keyId>.<secret>`)
- `QAMERA_DEFAULT_PRESET_ID` — domyślny preset
- `QAMERA_AI_MODEL` — model AI (jeden, wspólny dla generacji packshota i sesji; wybierany w konfiguracji, nie w generatorze)
- `QAMERA_API_BASE` — base URL API (default prod; override przez konfig dla local/dev)
- (opcjonalnie później) `QAMERA_WEBHOOK_SECRET` — gdy dojdzie webhook

**Tabele lokalne (tylko ID, nie duplikacja stanu):**

`ps_qamera_order` — mapowanie produkt → sesja (bo `GET /jobs` nie ma filtra po `product_ref`):
| kolumna | opis |
|---|---|
| `id_qamera_order` | PK |
| `id_product` | produkt PrestaShop |
| `qamera_order_id` | UUID sesji (`order_id` z odpowiedzi submit) |
| `date_add` | timestamp |

`ps_qamera_import` — dedup importu zatwierdzonej sesji do galerii:
| kolumna | opis |
|---|---|
| `id_qamera_import` | PK |
| `id_product` | produkt |
| `id_image` | utworzony `ps_image` |
| `qamera_job_id` | job źródłowy outputu |
| `output_sha` | sha256/hash outputu (dedup) |
| `date_add` | timestamp |

Wszystko inne (role photo/packshot/session, voting/approved, lineage packshot→zdjęcie i sesja→packshot,
asset_id, sha256) pochodzi z API: `GET /products/{external_ref}` (embedded images+packshots z voting),
`GET /jobs/{id}` (outputs+voting+status), `GET /jobs` (lista).

**Mapowanie sklep ↔ Qamera** przez stabilny `external_ref`:
- produkt: `ps-{id_product}`
- zdjęcie źródłowe (z galerii PS): `ps-{id_product}-img-{id_image}` — wiąże rejestrację Qamery z konkretnym `ps_image`, pozwala odtworzyć miniaturę przez `getImageLink`.

## §5 Track / stack lock

- **Platforma:** PrestaShop **8.x (PHP 7.4+) ORAZ 9.x (PHP 8.1+)** — jeden moduł, kompatybilność wsteczna.
- **Język modułu:** PHP zgodny z 7.4 (bez składni 8.0+ wymuszonej). Smarty (`.tpl`) dla widoków.
- **Slug modułu:** `qameraai`.
- **Async:** brak — upload+submit **synchronicznie w AJAX** (kilka sekund, spinner). Generacja async po
  stronie Qamery, wynik przez **polling** `GET /jobs/{id}`. Bez crona, bez kolejki, bez konfiguracji devops
  u merchanta.
- **Webhook:** brak w MVP (polling). Dokładany później jako optymalizacja.
- **Komunikacja z API:** cURL (Guzzle jeśli dostępny w PS), nagłówek `X-Api-Key`, baza
  `/api/v1/plugin/*`, koperta błędu `{ error: { code, message_i18n } }`, `Idempotency-Key` na mutacjach.
- **UI:** zakładka na karcie produktu (`displayAdminProductsExtra`), settings w `getContent()` modułu.
- **Model AI:** wybierany w konfiguracji modułu (`getContent`), jeden dla packshota i sesji — generator bez dropdownu modelu AI (zero żargonu AI w „wybierz i zatwierdź").

### Esencja brandu (pełny: `context/brand.md`)

Wtyczka reprezentuje markę **Qamera AI** wewnątrz panelu PrestaShop. „Zero Prompting. Pure Business.",
archetyp Creator+Sage, ton profesjonalny/bezpośredni/ekspercki (bez hype, zakazane „game changer"/„rewolucja").
- **Kolory:** grafit `#252b30`, **akcent teal `#83babc`** (przyciski primary, aktywna zakładka, plakietki ról), biały `#ffffff`. UI light: bg `#ffffff`, tekst `#1a1a1a`, border `#f3f4f6`.
- **Font:** Inter (400/500/600/700). Bez serif/dekoracyjnych.
- **Tokeny:** button 6px/36px, CTA 12px/48px, card 16px, input 8px.
- **Zasada UI:** zdjęcia produktów = bohater ekranu (pełna rozdzielczość, podgląd — atom „wierność produktu"); workflow „wybierz i zatwierdź" bez żargonu AI; stany puste/błędu konkretne i bezpośrednie.

## §6 Out of scope (świadomie POZA MVP)

- ❌ **Webhook** (HMAC, publiczny endpoint, anti-replay) — polling wystarcza, webhook to późniejsza optymalizacja.
- ❌ **Własna kolejka / cron** — świadomie sync w AJAX.
- ❌ **Bulk-generacja** wielu produktów naraz — generator per produkt.
- ❌ **Warianty produktu (combinations)** — sesja per produkt, nie per wariant.
- ❌ **Multistore** — pojedynczy sklep na start (ścieżki obrazów per-shop później).
- ❌ **Pełne i18n** — tylko PL (primary) + EN (fallback) przez system tłumaczeń PS.
- ❌ **Edycja/regeneracja** istniejących wyników, klonowanie sesji (`/orders/{id}/clone`).
- ❌ **Pełna duplikacja stanu lokalnie** (model "Heavy" / WooCommerce post_meta).

## §7 Open Questions (bramki decyzyjne — decyzja należy do Ciebie)

1. **`external_ref` a multistore:** `ps-{id_product}` koliduje przy multistore (te same ID w innych
   sklepach). Gdy dojdzie multistore → prefiks `shop{id_shop}-`. Na MVP zakładamy 1 sklep. (potwierdzić)
2. **`analysis_status`:** API wymaga `analysis_status='described'` (Gemini) zanim można `submit photo_shoot`
   na zdjęciu. Wtyczka musi czekać/pollować ten stan przed sesją. (uwzględnione w buildzie M3)
3. **Bezpośredni packshot z galerii:** ✅ ROZSTRZYGNIĘTE — każde zdjęcie z galerii produktu ma dwie
   opcje: „dodaj jako zdjęcie produktu" (źródło do generacji, POST /images) ORAZ „dodaj jako packshot"
   (gotowy packshot bez generacji, POST /packshots). Źródłem jest galeria PS, nie upload do wtyczki.
4. **Limit pollingu:** 3s interwał / 5 min max (jak WooCommerce). (domyślnie tak)
5. **Środowisko dev:** czy testujemy na lokalnym SaaS (`QAMERA_API_BASE` override), czy od razu prod
   `https://qamera.ai`? (potrzebne do M1)

## §8 Definition of Done

- [ ] Moduł instaluje się na czystym PrestaShop 8 i 9 (zakładka + settings widoczne).
- [ ] Wszystkie kryteria akceptacji §3 przechodzą ręcznym smoke testem na realnym koncie Qamera.
- [ ] Brak duplikacji stanu generacji w bazie wtyczki (tylko Configuration + 2 tabele ID z §4).
- [ ] Błędy API (brak klucza, brak kredytów, 4xx/5xx) pokazują czytelny komunikat, nie wywalają strony.
- [ ] `goals.md` M1–M5 wszystkie ☑.
