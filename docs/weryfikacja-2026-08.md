# Weryfikacja modułu — 2026-08-26

> Cel sesji: potwierdzić, że moduł nadal działa end-to-end po dwóch miesiącach przerwy
> (ostatnie żywe uruchomienie: 2026-06-15), zamknąć otwarte pytanie o zakres danych
> zwracanych przez API, i przygotować grunt pod walidację techniczną PrestaShop Addons.
>
> Kontrakt API (źródło prawdy): `../saas-platform/apps/web/public/openapi/plugin-v1.yaml`
> na `origin/main` (HEAD `224a706c7`). Repozytorium platformy czytane wyłącznie do odczytu.

## Środowisko, w którym to uruchomiono

| | |
|---|---|
| Sklep | PrestaShop **9.1.4**, `docker-compose.yml` tego repo, profil `ps9`, `http://localhost:8091` |
| Moduł | `qameraai` 1.0.0, aktywny, montowany na żywo z `./qameraai` |
| API | `https://qamera.ai` (produkcja), konto testowe `PL` (`0b5d8195-24a8-4c27-9ed3-5b4fd891b293`) |
| Kredyty | 6160 na starcie; sesja zużyła ~30 (3 zadania po 10) |
| PHP CLI | lokalne `php.exe` jest zablokowane przez politykę Application Control tej maszyny — wszystkie skrypty pomocnicze uruchamiane w kontenerze `php:8.1-cli` z zamontowanym repo |

**Uwaga o harnessie.** Katalog `../qamera-prestashop` (Makefile, `smoke/`, docker-compose)
montuje pod `modules/qameraai` **inny moduł** — klon `github.com/200iqlabs/prestashop-qamera`,
ostatni commit 31 maja. To nie jest moduł z tego repo. Cały przebieg wykonano więc na
`docker-compose.yml` z tego repozytorium (profile `ps8` / `ps9`), czyli na środowisku, które
opisuje `CLAUDE.md`. Katalog `modules/qameraai;C`, o którym mowa w zleceniu, nie istnieje —
nie było czego usuwać. `make validate` w tamtym harnessie wywołuje
`prestashop:module:validate`, komendę, której PrestaShop 9 nie ma.

---

## 1. Pytanie o wyciek — odpowiedź: **wyniki są poprawnie zawężone**

`GET /api/v1/plugin/products/{external_ref}` zwraca wyłącznie zdjęcia i packshoty
należące do wskazanego produktu. Materiał innego produktu nie może pod nim wyjść.

### Co to wymusza (kod platformy, `origin/main`)

1. `apps/web/app/api/v1/plugin/products/[idOrRef]/route.ts` — `authenticatePluginRequest`
   → `requirePluginInstallation` wiąże klucz API z jedną instalacją;
   `assertNoActingAccountTarget` odrzuca próbę wskazania innego konta nagłówkiem.
2. `ProductsRepository.findByIdOrRef(installationId, idOrRef)` —
   `.eq('installation_id', …)` + `.eq('id'|'external_ref', …)`.
3. `ProductImagesRepository.listByProduct(installationId, productId, limit)` i
   `ProductPackshotsRepository.listByProduct(…)` — `.eq('installation_id', …)`
   **oraz** `.eq('product_id', product.id)`.

Dodatkowo `registerImages` przy kolizji SHA-256 w obrębie instalacji zgłasza
`CatalogConflictError` zamiast po cichu podpiąć cudzy wiersz — dwa produkty nie mogą
współdzielić jednego assetu.

### Co to potwierdza empirycznie

`tools/probe-ref.php` (przepisany, patrz niżej) na koncie z **czterema** produktami,
z których każdy ma własne zdjęcia:

| ref | product.id | images | packshots |
|---|---|---|---|
| `ps-1` | `70d1c740-…` | 2 | 3 |
| `ps-19` | `ce1fe09b-…` | 1 | 2 |
| `ps-20` | `c23a367d-…` | 4 | 3 |
| `ps-21` | `15c46623-…` | 1 | 1 |

- każdy zagnieżdżony wiersz niesie `product_id` **równy** id żądanego produktu — 0 odstępstw;
- żadne id zdjęcia, id packshotu ani `asset_id` nie wystąpiło pod dwoma produktami;
- `GET /products/ps-nie-istnieje` → 404 „Zasób nie istnieje lub nie jest dostępny dla tej
  instalacji" — istnienie cudzego zasobu nie wycieka.

**VIOLATIONS: 0.**

### To samo pytanie dla `/packshots` i `/images`

- `GET /packshots` — zawężony do instalacji, nie do produktu. Bez parametru zwrócił
  6 packshotów z 3 różnych produktów (zachowanie zgodne z kontraktem: to endpoint listujący).
  Z `?product_ref=ps-19` zwrócił wyłącznie packshoty `ps-19`. **Moduł go nie woła.**
- `GET /images` — **nie istnieje**, HTTP 405. Zapis w zleceniu, że moduł go woła, jest
  nieaktualny: klasa `QameraApiClient` nie ma takiej metody. **Moduł go nie woła.**

### Czego moduł faktycznie czyta ponad produkt

Jedyny odczyt obejmujący całe konto to `GET /jobs?limit=100`
(`qameraai.php::loadJobsForProduct`), potrzebny bo `/products` nie zawiera lineage sesji.
Zwrócił 19 zadań ze wszystkich produktów; **każde** niosło niepuste `product_ref`.
Moduł filtruje je po stronie klienta, a złączenie do kafelków idzie po `packshot_asset_id`
równym `asset_id` packshotu tego produktu — asset id są rozłączne między produktami, więc
sesja innego produktu i tak nie miała gdzie się pokazać.

Mimo to filtr był **fail-open**: zadanie z `product_ref` równym `null` przechodziło dalej
zamiast wypaść. Na koncie nie było ani jednego takiego zadania, więc nic nie wyciekło —
ale to jedyna rzecz, która trzyma cudzą sesję poza tą zakładką, więc została zacieśniona
(patrz „Co naprawiono").

### Los sondy

`tools/probe-ref.php` przestał być jednorazowym zrzutem i **wszedł do repo** jako narzędzie,
które samo orzeka: sprawdza równość `product_id`, rozłączność id i assetów między produktami
oraz zawężanie `GET /packshots?product_ref=`, i kończy się kodem wyjścia 1 przy jakimkolwiek
naruszeniu. Nagłówek pliku mówi wprost, że narzędzie nie jest shippowane.

Wykluczenie z pakietu jest **strukturalne, nie regułowe**: `tools/build-zip.php` przechodzi
wyłącznie katalog `qameraai/`, więc nic z `tools/` (ani z `context/`, `docs/`, `prd.md`,
`goals.md`, `.env*`, plików dockera) nie ma jak do ZIP-a trafić. Potwierdzone listingiem
zawartości pakietu — patrz sekcja 5.

---

## 2. Core Flow — przebieg end-to-end

Uruchomione z karty produktu w panelu PrestaShop 9 (Playwright sterujący prawdziwym back
office, nie wywołania API z boku). Produkt: **`Hummingbird printed t-shirt`** (id 1, ref `ps-1`),
wybrany bo nie miał żadnego stanu po stronie Qamery — cały przebieg widać od zera.

| # | Krok | Wywołanie | Wynik |
|---|---|---|---|
| 1 | zdjęcie produktu | `POST /assets/upload` + `POST /images` | **OK** — asset `529f56c9-…`, `external_ref` `ps-1-img-1` |
| 2 | generacja packshotu | `POST /jobs` (`job_type=packshot`) | **OK** — job `c392d0fe-…`, order `d6052c68-…`, packshot ref `ps-1-pk-87a5df523d4b` |
| 3 | dostarczenie wyniku | **polling** `GET /jobs/{id}` co 3 s, limit 5 min | **OK** — wynik przyszedł przez odpytywanie |
| 4 | akceptacja | `POST /jobs/{id}/accept` | **OK** — job czyta się jako `completed` / `voting=accepted`; po przeładowaniu karty zakładka odtworzyła stan z API i pokazała przyciski sesji |
| 5 | sesja z packshotu | `POST /jobs` (`job_type=photo_shoot`, preset/model/sceneria z panelu) | **OK po ponowieniu** — order `e695285c-…`, job `256229d4-…`; pierwsza próba generacji padła po stronie platformy (`internal_error` / `INTERNAL_BUG`, status `retry_pending`), automatyczne ponowienie się powiodło |
| 6 | publikacja w galerii | `POST /jobs/{id}/accept` + pobranie wyniku + `Image` (ORM PrestaShop) | **OK** — `id_image=28` na produkcie 1, wszystkie miniatury wygenerowane, wiersz w `ps_qamera_import`, wiersz mapowania w `ps_qamera_order` |

**Mechanizm dostarczenia wyniku to polling, nie callback** — potwierdzone i w kodzie
(`views/js/qamera-product.js`: 3 s / 5 min, `action=getJob` → `GET /jobs/{id}`), i w przebiegu.
W całym module nie ma ani jednego odwołania do webhooka, HMAC-a czy publicznego endpointu.
To celowa różnica względem wtyczki WooCommerce i zgadza się z `CLAUDE.md` oraz §6 PRD.

### Czego **nie** uruchomiono

- **Core Flow na PrestaShop 8** — kontener wstał i sklep odpowiadał, ale pełnego przebiegu na
  nim nie wykonano (zatrzymany, by nie obciążać maszyny w trakcie generacji na PS9).
  Ostatni żywy przebieg na PS8: 2026-06-13 (log w `goals.md`). Deklaracja zgodności z 8.x
  opiera się więc na czerwcowym teście, nie na dzisiejszym.
- **Ścieżka `reject`** (`POST /jobs/{id}/reject`) — endpoint istnieje i jest wołany przez ten sam
  kod co `accept`, ale w tej sesji nie kliknięto go w panelu.
- **`DELETE /packshots/{idOrRef}`** — nie uruchomiony.

---

## 3. Zgodność kształtów z kontraktem

Metoda: strukturalny walidator OpenAPI 3.1 (wymagane pola, typy, enumy, pola
niezadeklarowane) puszczony na **żywe** odpowiedzi i na **rzeczywiste** ciała żądań, które
buduje moduł. Walidator sprawdzony kontrolnie na celowo zepsutych payloadach — wykrywa braki
pól wymaganych, złe typy i pola spoza schematu.

### Odpowiedzi (żywe)

| Wywołanie | Błędy schematu | Pola spoza schematu |
|---|---|---|
| `GET /me` | 0 | 0 |
| `GET /presets` | 0 | 0 |
| `GET /models` | 0 | 0 |
| `GET /sceneries` | 0 | 0 |
| `GET /ai-models` | 0 | 0 |
| `GET /jobs` | 0 | 0 |
| `GET /jobs/{id}` | 0 | 0 |
| `GET /products/{ref}` (×2) | 0 | 0 |
| `POST /assets/upload` | 0 | 0 |
| `POST /images` | 0 | 0 |
| `POST /packshots` | 0 | 0 |
| `POST /jobs` | 0 | 0 |
| koperta błędu (404) | zgodna | — |

### Żądania (te, które moduł faktycznie wysyła)

`SubmitJobRequest` (packshot i photo_shoot), `RegisterImagesBody`, `RegisterPackshotsBody` —
**0 odstępstw**. Wszystkie pola wymagane obecne, żadnego pola spoza schematu, `session_config`
rzutowany na obiekt (pusty `{}` zamiast `[]`), `Idempotency-Key` wyliczany z kanonicznego
payloadu.

### Rozbieżności

1. **Moduł brał poprawną odpowiedź `Job` za kopertę błędu.** `QameraApiClient::request()`
   traktował **każde** ciało zawierające obiekt `error` jako błąd API. Tymczasem `Job` ma
   `error` jako **pole wymagane** (`null`, gdy nic nie padło), a `retry_pending` jest legalnym
   statusem. Skutek: gdy generacja padnie, moduł zamiast pokazać „generacja nie powiodła się"
   rzucał ogólnym „nieoczekiwany błąd serwera", poller się zatrzymywał i zakładka kręciła się
   do wyczerpania 5 minut. Ujawnione dopiero dziś, bo w czerwcu żadne zadanie nie padło.
   **Naprawione.**
2. **`GET /images` nie istnieje** (405). Zapis, że moduł go woła, jest nieaktualny.
3. **Brakująca proporcja w UI.** Kontrakt dopuszcza `1:1`, `4:5`, `9:16`, `16:9`, `3:4`;
   lista w `product-tab.tpl` oferuje cztery — **bez `9:16`**, czyli bez formatu pionowego pod
   social. Nie łamie kontraktu, ale odcina wspieraną opcję. Nie naprawione — to zmiana w UI,
   nie w warstwie zgodności.
4. **Filtr `product_ref` był fail-open** — opisane w sekcji 1. **Naprawione.**
5. Moduł nie korzysta z: `GET /aspect-ratios`, `GET /pricing`, `GET /products`,
   `/orders/*`, `/jobs/batch`, `/jobs/{id}/refresh-url`, `/webhooks/*`, `/installations/*`.
   To świadomy zakres (Thin-B, brak webhooków), nie brak.

### Obserwacja wydajnościowa (nie błąd)

`ajaxProcessGeneratePackshot` czeka synchronicznie na `analysis_status='described'`
(do 6 prób × 2 s) trzymając w tym czasie proces Apache. Przy jednoczesnym pollingu z kilku
kart na małym hostingu to realne ryzyko wyczerpania puli procesów — w trakcie tej sesji
kontener PS9 raz przestał przyjmować połączenia i wymagał restartu.

---

## 4. Co naprawiono

| Plik | Zmiana |
|---|---|
| `qameraai/classes/QameraApiClient.php` | Nowa metoda `isErrorEnvelope()`: ciało jest kopertą błędu tylko wtedy, gdy status to 4xx/5xx **albo** `error` jest jedynym kluczem najwyższego poziomu (tak wygląda `ErrorEnvelope` w kontrakcie). Użyta w `request()` i `requestMultipart()`. Dzięki temu `Job` ze statusem `failed` / `retry_pending` wraca jako dane, a nie jako wyjątek — kontroler i poller mają już gotową obsługę takiego stanu. |
| `qameraai/qameraai.php` | `loadJobsForProduct()`: dopasowanie `product_ref` jest teraz ścisłe — zadanie bez `product_ref` wypada, zamiast przechodzić dalej. |
| `tools/probe-ref.php` | Przepisana z jednorazowego zrzutu na narzędzie orzekające, z kodem wyjścia. |

Weryfikacja poprawki koperty: osiem przypadków (job z błędem na 200, job bez błędu, koperta
na 401/404/500, samotny obiekt `error` na 200, zwykły payload, puste ciało) — wszystkie
przechodzą. Na żywo: `GET /jobs/{id}` dla zadania w stanie `retry_pending` z niepustym
`error` zwraca teraz obiekt zadania; przed poprawką rzucał wyjątkiem.

---

## 5. Pakiet dystrybucyjny

`qameraai.zip` przebudowany z bieżącego drzewa (`php tools/build-zip.php`): **22 pliki**,
62 748 bajtów. Wszystkie wpisy zaczynają się od `qameraai/`. Wykluczone: `config_pl.xml`.

Sprawdzone i **nieobecne** w pakiecie: `tools/`, `context/`, `docs/`, `prd.md`, `goals.md`,
`.env*`, `docker-compose.yml`, `Makefile`, `smoke/`, `.git`.

Pakiet trzeba przebudować ponownie po dołożeniu nagłówków licencyjnych.

---

## 6. Stan przygotowania do walidacji Addons

Zebrane z listy kontrolnej PrestaShop, nie z listy własnej.

### Spełnione

- `index.php` w każdym katalogu modułu (wzorzec „silence is golden", identyczny jak w modułach
  rdzenia PrestaShop).
- SQL: wyłącznie przez ORM albo z `pSQL()` / rzutowaniem `(int)`; nazwy tabel przez `_DB_PREFIX_`.
- Escaping na wyjściu: **każda** zmienna w szablonach Smarty ma `|escape:'htmlall':'UTF-8'`
  albo `|intval`.
- Ustawienia przez `Configuration::get`, klucze prefiksowane `QAMERA_`; klucz API nie żyje
  w kodzie ani w `.env` shippowanym.
- AJAX w kontrolerze (`AdminQameraAjaxController`), adres budowany przez
  `getAdminLink('AdminQameraAjax')`, czyli z tokenem pracownika.
- Brak `var_dump` / `console.log` / `serialize()`; brak zakomentowanego kodu.
- Nazwa archiwum zgodna z nazwą modułu, bez numeru wersji; jeden moduł w archiwum.

### Braki

| Brak | Waga |
|---|---|
| **Nagłówek licencyjny w żadnym z 16 plików PHP** i brak pliku licencji w module | odrzucenie automatyczne — czeka na decyzję, którą licencję przyjmujemy |
| **Brak `logo.png`** w katalogu modułu | wymagane przez Addons |
| **Brak `module_key`** w konstruktorze | wymagane, żeby sprzedawcy dostawali powiadomienia o aktualizacji |
| **Brak `docs/`** wewnątrz modułu (lista kontrolna oczekuje dokumentacji tam, najlepiej PDF) | zgłaszane przy walidacji ręcznej |
| **Teksty źródłowe po polsku** w `config.xml` i w `$this->displayName` / `$this->description` — lista kontrolna wymaga kodu po angielsku (tłumaczenia PL wchodzą przez system tłumaczeń) | ryzyko odrzucenia |

### `ps_versions_compliancy`

Zadeklarowane `8.0.0` – `9.99.99`. Dolna granica ma pokrycie w teście z czerwca (PS 8.x),
górna nie: dziś moduł przeszedł pełny przebieg na **9.1.4**. `9.99.99` to konwencja
PrestaShopowa i nie wywoła błędu walidatora, ale jest deklaracją na przyszłe wydania 9.x,
a nie stwierdzeniem faktu. Zostawiam bez zmiany — obniżanie górnej granicy przy każdym
wydaniu 9.x oznaczałoby wypuszczanie aktualizacji modułu tylko po to, żeby ją podnieść.

### Walidator PrestaShop

`validator.prestashop.com` przyjmuje ZIP albo **publiczne** repozytorium GitHub. Nasze jest
prywatne, więc w grę wchodzi tylko wysyłka pakietu. To wysłanie modułu na zewnątrz —
i sensowne dopiero po dołożeniu nagłówków, żeby raport nie był w całości o nich.
Nie uruchomione w tej sesji.

---

## 7. Otwarte

- Decyzja o licencji (blokuje nagłówki, plik licencji i kolejny build pakietu).
- Wysyłka pakietu do walidatora PrestaShop.
- `logo.png`, `module_key`, `docs/` w module, angielskie teksty źródłowe.
- `9:16` na liście proporcji.
- Pełny Core Flow na PrestaShop 8 (dziś tylko PS9).
- Synchroniczne czekanie na analizę zdjęcia trzyma proces Apache — do przemyślenia przy
  większym ruchu.
