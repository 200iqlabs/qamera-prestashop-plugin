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

Swimwear and lingerie: turn a photo you already have into packshots and on-model sessions,
published straight into the product gallery.

### Long description

Qamera AI adds one tab to the product page. From it you take a photo already in the product
gallery, turn it into a clean packshot, and generate a photo session from that packshot —
a model, a setting, the proportions you need. You review the results and publish the ones
you approve straight into the product gallery.

Nothing leaves your control on the way: no image is added to your shop until you accept it,
and accepting the same result twice never creates a duplicate. The state of every generation
lives in your Qamera AI account rather than in the module's database, so reloading the product
page is safe at any point and never loses a result.

**Built for swimwear and lingerie.** That is the category the models, sceneries and presets
were tuned on, and where the results are strongest — a specialist studio rather than a
general-purpose image generator. Products from other categories generally work too, but
expect to spend a few attempts adjusting the session settings before the result is right.

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

`swimwear photography`, `lingerie photography`, `product photography`, `packshot`,
`fashion product images`, `photo studio`, `photo shoot`, `model photos`

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

Stroje kąpielowe i bielizna: zamień zdjęcie, które już masz, w packshoty i sesje na modelce,
publikowane wprost w galerii produktu.

### Opis długi

Qamera AI dokłada jedną zakładkę na karcie produktu. Bierzesz z niej zdjęcie leżące już
w galerii produktu, robisz z niego czysty packshot, a z packshota generujesz sesję — modelka,
sceneria, proporcje, których potrzebujesz. Przeglądasz wyniki i publikujesz w galerii te,
które zatwierdzisz.

Po drodze nic nie wymyka się spod kontroli: żadne zdjęcie nie trafia do sklepu, dopóki go nie
zaakceptujesz, a ponowne zatwierdzenie tego samego wyniku nigdy nie utworzy duplikatu. Stan
każdej generacji żyje na koncie Qamera AI, nie w bazie modułu — odświeżenie karty produktu
jest bezpieczne w dowolnym momencie i nigdy nie gubi wyniku.

**Zbudowane pod stroje kąpielowe i bieliznę.** To na tej kategorii dostrojone są modelki,
scenerie i presety i tam wyniki są najmocniejsze — to studio wyspecjalizowane, nie ogólny
generator obrazów. Produkty z innych kategorii zwykle też działają, ale liczy się z kilkoma
podejściami i dostrojeniem ustawień sesji, zanim wynik będzie taki, jak trzeba.

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

`zdjęcia strojów kąpielowych`, `zdjęcia bielizny`, `zdjęcia produktowe`, `packshot`,
`sesja zdjęciowa`, `fotografia produktowa`, `zdjęcia na modelce`, `moda`

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
| --- | --- |
| Ikona 57×57 | `docs/listing-assets/icon-57.png` |
| Zrzuty ekranu | **`docs/listing-assets/upload/`** — przeskalowane do zakresu, którego wymaga formularz (1000×1000 – 2000×2000). Oryginały w pełnej rozdzielczości leżą katalog wyżej |
| Instrukcja PDF | `qameraai/docs/readme_en.pdf` i `readme_pl.pdf` — nazwy zgodne z konwencją PrestaShopa, jadą też w paczce |
| Tytuł listingu | EN `Qamera AI — product photos and packshots` · PL `Qamera AI — zdjęcia produktowe i packshoty` |
| Kraje | wszystkie — usługa nie jest ograniczona terytorialnie |
| Kompatybilność | zaznacz **8.0.0 – 9.1.x**, czyli to, co faktycznie uruchomiliśmy. **Nie zaznaczaj 9.2.0** — patrz niżej |
| Product Key | `d0fdc39ec02d6b92a80752639ccf2868` — wpisany w konstruktorze modułu, paczka przebudowana |
| Changelog | `1.0.0 — pierwsze wydanie.` Jedna linijka zamiast pustego pola |
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

## Message to Addons Team

> Tak, mamy im co powiedzieć — i to jest najważniejsze pole w całym formularzu, bo bez niego
> recenzent **nie ma jak przetestować modułu**. Wtyczka bez konta z kredytami pokazuje pustą
> zakładkę; recenzent zobaczy ekran ustawień i nic więcej, a to prosta droga do odrzucenia
> albo do rundy pytań.
>
> Wstaw klucz w miejsce `<API KEY>` przed wysłaniem.

```text
Hello,

This module is a client for Qamera AI, an external image-generation service. It cannot be
tested without an account, so we have prepared one for you:

  API key: <API KEY>
  Credits loaded: 300 (enough for roughly 30 generated images)

Paste it in Modules → Qamera AI → Configure, pick any AI model from the dropdown, save, then
open any product and use its "Qamera AI" tab.

Three things that may look like faults but are intended:

1. Generation takes time. A packshot needs about one to three minutes, a session longer. The
   tab polls for the result and updates itself — there is no cron task and no public callback
   endpoint to configure, which is deliberate: the module works on shops behind a firewall.

2. A session can only be generated from an accepted packshot, never straight from a source
   photo. Trying the latter is refused on purpose — the packshot is what keeps results
   consistent across a catalogue.

3. The service is tuned for swimwear and lingerie. Other product categories generally work,
   but the visual quality is at its best in that category, so please judge the output with
   that in mind.

Nothing is written to the shop until the merchant accepts a result, and the module stores no
generated content locally — only two small tables mapping identifiers.

Documentation in English and Polish ships in the module, in docs/.

Thank you for your time.
```

**Do zrobienia przed wysłaniem:** wygenerować klucz na **osobnym koncie**, nie na naszym
produkcyjnym. Aktywność recenzenta zostaje wtedy poza naszym katalogiem, a klucz można
unieważnić po zakończeniu walidacji. 300 kredytów wystarczy na przebieg z zapasem na kilka
podejść.

## Kompatybilność — dlaczego nie 9.2.0

Deklarujemy **8.0.0 – 9.1.x**, bo tyle naprawdę sprawdziliśmy: pełny przebieg generacji na
**9.1.4**, a na **8.2.7** kontrola odczytowa plus czerwcowy przebieg z generacją. PrestaShop
9.2 **jeszcze się nie ukazał** — w lipcu 2026 był w fazie zamrożenia funkcji, a na Docker Hubie
nie ma żadnego obrazu `9.2`, więc nie da się go przetestować nawet gdybyśmy chcieli.

Zaznaczenie wersji, której nie uruchomiliśmy, to obietnica złożona kupującemu. Jeśli na 9.2
coś nie zadziała, wraca to jako zgłoszenie i jedna gwiazdka — a recenzent techniczny
PrestaShopa ma prawo to sprawdzić.

`ps_versions_compliancy` w kodzie zostaje szerokie (`8.0.0` – `9.99.99`) i **to nie jest
niespójność**: ono decyduje, czy PrestaShop w ogóle pozwoli zainstalować moduł, a nie co
obiecujemy w sklepie. Sklep na 9.2 zainstaluje wtyczkę i najprawdopodobniej będzie działać —
po prostu tego nie obiecujemy, dopóki nie sprawdzimy.

**Do zrobienia, gdy 9.2 wyjdzie:** przepuścić Core Flow na obrazie 9.2, dopisać wersję na
listingu. Przy okazji przypomnienie: od 1 lutego 2026 aktualizacja modułu jest odrzucana,
jeśli produkt nie jest zgodny z najnowszym PrestaShopem — więc to nie jest zadanie „kiedyś",
tylko warunek następnej aktualizacji.
