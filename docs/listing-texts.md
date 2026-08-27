# Teksty na stronę produktu w PrestaShop Addons

> Do skopiowania w formularz listingu. Dwie wersje językowe — decyzja z 2026-08-27:
> startujemy z angielskim i polskim naraz.
>
> **Twarda zasada, której trzymają się te teksty:** nigdzie nie pada adres naszej strony
> ani adres e-mail. Regulamin Addons zabrania podawania jednego i drugiego zarówno na
> stronie produktu, jak i w dokumentacji. Nazwa usługi zostaje, odsyłacz nie.
>
> Ton z `context/brand.md`: konkretnie, bez hype'u, kolejność „efekt → zastosowanie →
> technologia". Pozycjonujemy jako **wirtualne studio zdjęciowe**, nie „generator obrazów AI".
> Zakazane zwroty: „game changer", „rewolucja".

---

## English

### Short description

Turn a product photo you already have into gallery-ready packshots and photo sessions —
without leaving the PrestaShop back office.

### Long description

Qamera AI adds one tab to the product page. From it you take a photo already in the product
gallery, turn it into a clean packshot, and generate a photo session from that packshot —
a model, a setting, the proportions you need. You review the results and publish the ones
you approve straight into the product gallery.

Nothing leaves your control on the way: no image is added to your shop until you accept it,
and accepting the same result twice never creates a duplicate. The state of every generation
lives in your Qamera AI account rather than in the module's database, so reloading the product
page is safe at any point and never loses a result.

The module works on PrestaShop 8.x and 9.x, with a Polish and English interface.

**Requires an active Qamera AI account with credits.** Generation runs on that account and
consumes its credits; the module is the interface, not the studio.

### Features

- A **Qamera AI** tab on the product page — the whole flow happens there.
- Turn a product photo into a clean **packshot**.
- Generate a **photo session** from an approved packshot: style preset, model, scenery,
  aspect ratio, number of images, and a free-text note to steer the result.
- **Accept or reject** every result before anything reaches your shop.
- Approved images are **published into the product gallery**, deduplicated, and appear on the
  storefront.
- Account status and credit balance visible on the module's configuration screen.
- Readable error messages for every failure — no blank screens.
- Polish and English interface.

### Benefit for the merchant

Product photography without booking a studio: from a photo you already have to gallery-ready
images, in the back office you work in every day. No shipping products to a photographer,
no waiting for a session slot, no separate tool to learn.

### Benefit for your customers

Every product shown the same way — a consistent packshot, and where it helps, the product on
a model or in a setting. A shopper who can see the product properly spends less time guessing
and sends back fewer parcels.

### Keywords

`product photography`, `packshot`, `product images`, `photo studio`, `photo shoot`,
`catalog images`, `model photos`, `product visuals`

### What are the main steps to install your module?

1. Upload `qameraai.zip` in **Modules → Module Manager → Upload a module**, or copy the
   `qameraai` folder into your shop's `modules/` directory and install it from the list.
2. Open **Configure** on the module.
3. Paste your Qamera AI plugin API key and save. The page then shows your account name, plan
   and credit balance — that is how you know the key works.
4. Choose an **AI model** and save. This step is required; generation stays blocked until a
   model is selected.
5. Open any product and go to its **Qamera AI** tab. That is where the whole flow happens.

A short illustrated guide ships inside the module, in `docs/`, in English and Polish.

### What are your module prerequisites?

- PrestaShop 8.0 or newer, or 9.x.
- PHP 7.4 or newer with the **cURL** extension enabled.
- Your shop must be able to make **outgoing HTTPS requests** — the module talks to the
  Qamera AI API from the server, never from the browser.
- An **active Qamera AI account with credits**, and a plugin API key generated on it.
  Generation runs on that account and consumes its credits.

No cron job, no queue and no publicly reachable callback address are needed. The module
collects results by polling, so it works on shops behind a firewall.

### Anything to add?

**Nothing reaches your shop until you accept it.** Every packshot and every session image is
reviewed by you first; rejecting one removes it, accepting one publishes it into the product
gallery. Accepting the same result twice never creates a duplicate.

**Your shop's database stays thin.** The module stores only two small mapping tables of
identifiers. The state of every generation — status, approval, which packshot a session came
from — lives in your Qamera AI account, so reloading the product page is safe at any moment
and never loses a result.

**Deliberate limits of this version**, stated plainly so nobody buys the wrong thing: no
generation across many products at once, no per-combination images, no multistore, and no
editing or regenerating a finished result. Interface in Polish and English.

Support runs through the PrestaShop Addons marketplace.

---

## Polski

### Krótki opis

Zamień zdjęcie, które już masz, w packshoty i sesje produktowe gotowe do galerii — bez
wychodzenia z panelu PrestaShop.

### Opis długi

Qamera AI dokłada jedną zakładkę na karcie produktu. Bierzesz z niej zdjęcie leżące już
w galerii produktu, robisz z niego czysty packshot, a z packshota generujesz sesję — modelka,
sceneria, proporcje, których potrzebujesz. Przeglądasz wyniki i publikujesz w galerii te,
które zatwierdzisz.

Po drodze nic nie wymyka się spod kontroli: żadne zdjęcie nie trafia do sklepu, dopóki go nie
zaakceptujesz, a ponowne zatwierdzenie tego samego wyniku nigdy nie utworzy duplikatu. Stan
każdej generacji żyje na koncie Qamera AI, nie w bazie modułu — odświeżenie karty produktu
jest bezpieczne w dowolnym momencie i nigdy nie gubi wyniku.

Moduł działa na PrestaShop 8.x i 9.x, interfejs po polsku i angielsku.

**Wymaga aktywnego konta Qamera AI z kredytami.** Generacja odbywa się na tym koncie i zużywa
jego kredyty; moduł jest interfejsem, nie studiem.

### Funkcje

- Zakładka **Qamera AI** na karcie produktu — cały przebieg dzieje się w niej.
- Zamiana zdjęcia produktu w czysty **packshot**.
- Generowanie **sesji zdjęciowej** z zatwierdzonego packshota: preset stylu, modelka, sceneria,
  proporcje, liczba zdjęć i pole na własne wskazówki.
- **Zatwierdzasz albo odrzucasz** każdy wynik, zanim cokolwiek trafi do sklepu.
- Zatwierdzone zdjęcia **lądują w galerii produktu**, bez duplikatów, i są widoczne w sklepie.
- Status konta i saldo kredytów na ekranie konfiguracji modułu.
- Czytelny komunikat przy każdym błędzie — żadnych białych ekranów.
- Interfejs polski i angielski.

### Korzyść dla sklepikarza

Zdjęcia produktowe bez rezerwowania studia: od zdjęcia, które już masz, do materiału gotowego
do galerii — w panelu, w którym i tak pracujesz codziennie. Bez wysyłania produktów do
fotografa, bez czekania na termin sesji, bez uczenia się osobnego narzędzia.

### Korzyść dla klientów sklepu

Każdy produkt pokazany tak samo — spójny packshot, a tam gdzie to pomaga, produkt na modelce
albo w scenerii. Kupujący, który dobrze widzi produkt, mniej się domyśla i rzadziej odsyła
paczkę.

### Słowa kluczowe

`zdjęcia produktowe`, `packshot`, `sesja zdjęciowa`, `fotografia produktowa`,
`zdjęcia do sklepu`, `katalog produktów`, `zdjęcia na modelce`, `wizualizacje produktu`

### Jak zainstalować moduł — główne kroki

1. Wgraj `qameraai.zip` w **Moduły → Menedżer modułów → Wgraj moduł**, albo skopiuj katalog
   `qameraai` do `modules/` w sklepie i zainstaluj z listy.
2. Kliknij **Konfiguruj** przy module.
3. Wklej klucz API wtyczki Qamera AI i zapisz. Strona pokaże wtedy nazwę konta, plan i saldo
   kredytów — po tym poznajesz, że klucz działa.
4. Wybierz **model AI** i zapisz. Ten krok jest wymagany; bez wybranego modelu generacja
   pozostaje zablokowana.
5. Wejdź na dowolny produkt i otwórz zakładkę **Qamera AI**. Tam dzieje się cały przebieg.

Krótka instrukcja jedzie w module, w katalogu `docs/`, po polsku i po angielsku.

### Czego moduł wymaga

- PrestaShop 8.0 lub nowszy, albo 9.x.
- PHP 7.4 lub nowszy z włączonym rozszerzeniem **cURL**.
- Sklep musi móc wykonywać **połączenia wychodzące HTTPS** — moduł rozmawia z API Qamera AI
  z serwera, nigdy z przeglądarki.
- **Aktywne konto Qamera AI z kredytami** i wygenerowany na nim klucz API wtyczki. Generacja
  odbywa się na tym koncie i zużywa jego kredyty.

Nie trzeba crona, kolejki ani publicznie dostępnego adresu zwrotnego. Moduł odbiera wyniki
odpytywaniem, więc działa też w sklepie schowanym za zaporą.

### Co jeszcze warto wiedzieć

**Nic nie trafia do sklepu, dopóki tego nie zatwierdzisz.** Każdy packshot i każde zdjęcie
z sesji najpierw oglądasz: odrzucenie usuwa je, zatwierdzenie publikuje w galerii produktu.
Ponowne zatwierdzenie tego samego wyniku nigdy nie utworzy duplikatu.

**Baza sklepu zostaje lekka.** Moduł trzyma u siebie tylko dwie niewielkie tabele z
identyfikatorami. Stan każdej generacji — status, akceptacja, z którego packshota powstała
sesja — żyje na koncie Qamera AI, więc odświeżenie karty produktu jest bezpieczne w dowolnym
momencie i nigdy nie gubi wyniku.

**Świadome granice tej wersji**, powiedziane wprost, żeby nikt nie kupił nie tego, czego
szukał: brak generacji dla wielu produktów naraz, brak zdjęć dla poszczególnych wariantów,
brak obsługi wielu sklepów, brak edycji i ponownej generacji gotowego wyniku. Interfejs po
polsku i po angielsku.

Wsparcie prowadzimy przez marketplace PrestaShop Addons.

---

## Pozostałe pola formularza

| Pole | Wartość |
|---|---|
| Ikona 57×57 | `docs/listing-assets/icon-57.png` |
| Zrzuty ekranu | **`docs/listing-assets/upload/`** — przeskalowane do zakresu, którego wymaga formularz (1000×1000 – 2000×2000). Oryginały w pełnej rozdzielczości leżą katalog wyżej |
| Instrukcja PDF | `qameraai/docs/user-guide.pdf` (EN) i `user-guide-pl.pdf` (PL) — jedzie też w paczce |
| Tytuł listingu | EN `Qamera AI — product photos and packshots` · PL `Qamera AI — zdjęcia produktowe i packshoty` |
| Kraje | wszystkie — usługa nie jest ograniczona terytorialnie |
| Kompatybilność | PrestaShop 8.0.0 – 9.99.99 |
| Kategoria | **Content management** — jedyna pasująca w ich liście (Product Page nie było w ofercie) |

## Wideo — świadomie odpuszczone przy pierwszym zgłoszeniu

Pole „Any video demonstration of your product to share?" zostaje puste.

Da się je zrobić: przebieg jest sterowalny skryptem, więc nagranie ekranu z prawdziwej sesji
jest wykonalne. Problem jest w treści, nie w narzędziu — **generacja packshota i sesji trwa
po kilka minut**, więc surowe nagranie to w większości ekran, na którym nic się nie dzieje.
Użyteczne wideo wymaga wycięcia oczekiwania i przyspieszenia, czyli osobnej roboty
montażowej, a przy okazji kolejnych kredytów na ponowną generację.

Wobec ustalenia „publikujemy w obecnym stanie" nie warto tym blokować startu. **Uwaga na
koszt dołożenia go później:** każda zmiana treści listingu to kolejna runda przeglądu
marketingowego, do 10 dni roboczych na język. Jeśli wideo ma być w pierwszym zgłoszeniu,
trzeba je zrobić teraz — inaczej wchodzi razem z jakąś następną aktualizacją, kiedy listing
już żyje i widać, czy w ogóle łapie ruch.

## Czego świadomie nie ma w tych tekstach

- **Adresu strony i e-maila** — zakaz regulaminowy, sprawdzany w przeglądzie marketingowym.
- **Obietnic jakości bez pokrycia** („studyjna jakość", „nie do odróżnienia od zdjęć") —
  recenzent może zażądać dowodów, a my ich w listingu nie mamy.
- **Ceny za wygenerowane zdjęcie** — model kredytowy żyje po stronie konta Qamery i zmiana
  cennika unieważniłaby opis, a każda zmiana tekstu to kolejny przegląd.
