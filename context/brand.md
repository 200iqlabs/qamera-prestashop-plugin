# Brand Reference — Qamera AI for PrestaShop

> Adopcja kanonicznego brandu Qamera AI (źródło: agentic-ai-system `context/brand/brand-quick-reference.md`
> + `tone-of-voice.md`, saas-platform `apps/web/styles`). Wtyczka żyje w panelu admina PrestaShop, ale
> wizualnie i tonalnie reprezentuje markę Qamera AI. Stosuj te wartości w KAŻDYM ekranie wtyczki.
> Aktualizacja kanonu: 2026-03-17. Wersja brand.md: 1.0 · 2026-06-10.

## Esencja

Wirtualne studio fotograficzne AI. **„Zero Prompting. Pure Business."** Profesjonalny, ekspercki konektor —
nie zabawka AI. Wizualnie: czysto, minimalistycznie, treść (zdjęcia produktów) na pierwszym planem.
Archetyp: **Creator + Sage** (budowniczy, którego efekty mówią same za siebie; głębokie zrozumienie technologii).

- **Tagline PL:** „Twoje produkty. Każda sceneria. Zero logistyki."
- **Tagline EN:** „AI-powered virtual photo studio for e-commerce"
- **Nazwa:** Qamera AI (poprzednio Shorts Lab — nie używać).

## Paleta kolorów (HEX)

### Brand (nie zmieniaj)
| Rola | HEX | Użycie |
|---|---|---|
| Grafitowy | `#252b30` | Logo, nagłówki na jasnym tle |
| Teal | `#83babc` | Akcent, „AI", aktywne elementy, dekoracje — **kolor stały logo** |
| Biały | `#ffffff` | Logo na ciemnym tle, tło |

### UI — jasny tryb (domyślny w adminie PrestaShop)
| Rola | HEX |
|---|---|
| Background | `#ffffff` |
| Foreground (tekst) | `#1a1a1a` |
| Border | `#f3f4f6` |
| Input | `#e5e7eb` |

### UI — ciemny tryb (jeśli dotyczy)
| Rola | HEX |
|---|---|
| Background | `#111111` |
| Foreground | `#ffffff` |
| Border | `#1f2937` |
| Input | `#374151` |

**Akcent akcji (przyciski primary / aktywny stan):** teal `#83babc`. Stany pozytywne (zatwierdzone): zieleń
systemowa PrestaShop lub `#1a7f37`. Destrukcyjne (odrzuć/usuń): czerwień systemowa PrestaShop.

## Typografia

| Rola | Font | Waga |
|---|---|---|
| Interfejs | **Inter** | 400 (regular), 500 (medium) |
| Nagłówki | **Inter** | 600 (semibold), 700 (bold) |
| Hero | **Inter** | 700, tracking-tighter |

Google Fonts: Inter. **Nie używać** serif ani krojów dekoracyjnych. W adminie PrestaShop: dziedzicz font
systemowy panelu, ale dąż do Inter dla elementów Qamery, jeśli możliwe bez konfliktu.

## Komponenty UI (tokeny)

| Element | Zaokrąglenie | Wysokość | Cień |
|---|---|---|---|
| Button (default) | 6px | 36px | shadow-xs |
| Button CTA | 12px | 48px | shadow-xs → shadow-2xl hover |
| Card | 16px | auto | shadow-sm |
| Input | 8px | 36px | shadow-2xs |
| Badge | 6px | auto | — |

## Voice & tone

| Cecha | Tak | Nie |
|---|---|---|
| Profesjonalny | dane, fakty, konkrety | luźny slang |
| Bezpośredni | krótko, na temat | padding, filler |
| Transparentny | wyzwania, ograniczenia | hype |
| Ekspercki | wartość w każdym zdaniu | puste frazesy |

**Kolejność komunikacji:** efekt (wizualny wow) → zastosowanie (biznes) → technologia.

## Trzy filary
1. **ILLUSION** — AI = jakość High Fashion.
2. **ECONOMY** — koszt i czas vs tradycyjna sesja.
3. **BACKSTAGE** — building in public.

## Czego unikać
- Zakazane zwroty: „Game changer", „Rewolucja" (bez danych), „Pociąg odjechał", „Era X się skończyła".
- Fioletowe gradienty, „Inter wszędzie bez hierarchii", karty w kartach, generic AI-slop look.
- Pozycjonowanie jako „generator obrazów AI" — to **wirtualne studio**, język biznesu nie nowości AI.

## Zastosowanie w UI wtyczki
- Akcent teal `#83babc` na: aktywna zakładka „Qamera AI", przyciski primary („Generuj packshot", „Stwórz sesję"),
  plakietki ról.
- Zdjęcia produktów (packshoty/sesje) = bohater ekranu, pełna rozdzielczość, link do podglądu (atom „wierność produktu").
- Workflow „wybierz i zatwierdź" (approve/reject) bez żargonu AI.
- Stany puste/błędu: ton bezpośredni, konkret („Brak packshotów — kliknij Generuj packshot"), bez hype.
