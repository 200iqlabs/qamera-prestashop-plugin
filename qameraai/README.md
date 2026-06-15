# Qamera AI for PrestaShop

Cienki moduł-wrapper na API [Qamera AI](https://qamera.ai). Z karty produktu w panelu
PrestaShop generujesz **packshoty** i **sesje produktowe**, a zatwierdzone wyniki publikujesz
wprost w galerii produktu — bez opuszczania back office.

Źródłem prawdy o stanie generacji (role, status, akceptacja, lineage) jest API Qamery, **nie**
baza modułu. Lokalnie trzymane są wyłącznie mapowania ID (model „Thin-B").

- **Kompatybilność:** PrestaShop **8.x** (PHP 7.4+) oraz **9.x** (PHP 8.1+) — jeden moduł.
- **Slug modułu:** `qameraai`
- **Języki UI:** polski (podstawowy) + angielski (`translations/pl.php`, `translations/en.php`).

---

## Wymagania

- PrestaShop 8.0+ lub 9.x.
- PHP 7.4 lub nowszy z rozszerzeniem **cURL** (wymagane do komunikacji z API).
- Konto Qamera AI z aktywnym kluczem API (`mk_live_...`).

---

## Instalacja

1. Pobierz paczkę `qameraai.zip` (patrz sekcja *Budowanie paczki ZIP*) lub spakuj katalog `qameraai/`.
2. W panelu PrestaShop: **Moduły → Menedżer modułów → Wgraj moduł** i wskaż `qameraai.zip`.
   - Alternatywnie skopiuj katalog `qameraai/` do `modules/` w katalogu sklepu i kliknij **Zainstaluj**.
3. Po instalacji moduł:
   - tworzy dwie tabele mapujące ID (`ps_qamera_order`, `ps_qamera_import`),
   - rejestruje ukrytą zakładkę kontrolera AJAX,
   - podpina się pod hook `displayAdminProductsExtra` (zakładka na karcie produktu).

---

## Skąd wziąć klucz API

1. Zaloguj się na [https://qamera.ai](https://qamera.ai).
2. Wejdź w ustawienia konta / integracji i wygeneruj **klucz API wtyczki**.
3. Klucz ma format `mk_live_<keyId>.<secret>`. Skopiuj go w całości.
4. **Nie udostępniaj** klucza ani nie commituj go do repozytorium — żyje wyłącznie w konfiguracji sklepu
   (`ps_configuration`), nie w plikach modułu.

---

## Konfiguracja

Przejdź do **Moduły → Menedżer modułów → Qamera AI → Konfiguruj**:

1. **Klucz API** — wklej klucz `mk_live_...`. Po zapisaniu moduł pobierze status konta i saldo kredytów.
2. **Adres API (base URL)** — domyślnie produkcyjny `https://qamera.ai`. Zmień **tylko** dla środowiska
   dev/local.
3. **Domyślny preset** — opcjonalny preset sesji (lista pojawia się po zapisaniu poprawnego klucza).
4. **Model AI** — wspólny model dla packshotów i sesji. **Wymagany** — bez niego generacja jest zablokowana.

Po zapisaniu poprawnego klucza panel pokazuje **Konto**, **Plan** i **Saldo kredytów**. Każdy błąd
(brak/niepoprawny klucz, brak kredytów, limit zapytań, błąd serwera, brak połączenia) jest wyświetlany
jako czytelny komunikat — moduł nigdy nie pokazuje białego ekranu.

---

## Przepływ end-to-end (Core Flow)

Cała praca odbywa się na karcie produktu, w zakładce **Qamera AI**:

1. **Dodaj zdjęcia do produktu** standardowo (zakładka *Zdjęcia* w PrestaShop). To one są źródłem.
2. **Wybierz zdjęcie** z galerii i dodaj je jako **źródło** (do generacji) albo bezpośrednio jako
   gotowy **packshot**.
3. **Generuj packshot** z dodanego zdjęcia źródłowego. Moduł wysyła plik do Qamery, czeka na analizę
   i zleca generację (synchronicznie, wynik przez polling — bez crona i kolejek).
4. **Zatwierdź lub odrzuć** wygenerowany packshot. Odrzucenie usuwa go z katalogu.
5. **Generuj sesję** z zatwierdzonego packshota, używając ustawień z panelu (preset, model/manekin,
   sceneria, proporcje, liczba zdjęć, kontekst).
   - Reguła twarda: **sesja zawsze powstaje z packshota, nigdy wprost ze zdjęcia źródłowego.**
6. **Zatwierdź** wybrane wyniki sesji — zatwierdzone zdjęcia są **publikowane w galerii produktu**
   (z deduplikacją po SHA-256, więc ponowne zatwierdzenie nie tworzy duplikatu) i widoczne na sklepie.

---

## Obsługa błędów

Każda ścieżka wywołania API mapuje błędy na czytelny komunikat administratora:

| Sytuacja | Komunikat |
|---|---|
| Brak / niepoprawny klucz API | „Brak klucza API…" / „Nieprawidłowy lub nieautoryzowany klucz API…" |
| Brak kredytów (402) | „Brak kredytów na koncie Qamera AI." |
| Limit zapytań (429) | „Zbyt wiele żądań do Qamera AI. Spróbuj ponownie za chwilę." |
| Błąd serwera (5xx) | „Błąd serwera Qamera AI. Spróbuj ponownie później." |
| Brak połączenia / timeout | „Nie można połączyć się z Qamera AI: …" |

Wszystkie te komunikaty mają tłumaczenia PL i EN (`translations/`).

---

## Tłumaczenia

- `translations/pl.php` — polski (język podstawowy).
- `translations/en.php` — angielski (fallback).

Pliki używają klasycznego formatu `$_MODULE` PrestaShop i pokrywają stronę ustawień, obie szablony
Smarty, odpowiedzi błędów kontrolera AJAX, komunikaty klienta API **oraz stringi renderowane po stronie
przeglądarki** (etykiety przycisków, komunikaty postępu, potwierdzenia) — patrz niżej.

---

## Budowanie paczki ZIP

Z katalogu nadrzędnego względem `qameraai/`:

```bash
zip -r qameraai.zip qameraai -x 'qameraai/config_*.xml'
```

Paczka musi zawierać katalog `qameraai/` na najwyższym poziomie (PrestaShop wymaga, by nazwa katalogu
w archiwum odpowiadała slugowi modułu). Wykluczamy `config_*.xml` — to cache tłumaczeń opisu modułu
generowany per-instalacja przez PrestaShop, nie kod źródłowy (`config.xml` bez sufiksu zostaje).

---

## Zakres (świadome granice MVP)

W zakresie: zdjęcie → packshot → sesja → publikacja zatwierdzonych w galerii, w całości z karty produktu.

Poza zakresem: webhooki, własna kolejka/cron, generacja zbiorcza wielu produktów, warianty (combinations),
multistore, pełne i18n (tylko PL+EN), edycja/regeneracja/klonowanie wyników.

---

## Stringi JavaScript (i18n)

Etykiety, komunikaty postępu i potwierdzenia renderowane po stronie przeglądarki
(`views/js/qamera-product.js`) **są przetłumaczone PL/EN**. Szablon `product-tab.tpl` emituje payload
JSON (`<script type="application/json" id="qamera-i18n">`), w którym każdy string przechodzi przez
system tłumaczeń PrestaShop (`{l s='…' mod='qameraai' js=1}`, domena `product-tab`). Skrypt czyta ten
payload (`JSON.parse`) i podmienia teksty; przy braku payloadu funkcja `t()` używa polskiego źródła jako
fallbacku, więc UI nigdy nie renderuje pustych etykiet. Dodając nowy string w JS: dodaj klucz do payloadu
w `product-tab.tpl` i wpis do `translations/pl.php` + `translations/en.php` (domena `product-tab`,
klucz = `md5` polskiego źródła).
