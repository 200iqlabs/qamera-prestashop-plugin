---
description: Krok 1 — generuje context/brand.md z artefaktów W2 (ścieżka A z LP / B od zera)
---

[ROLA]
Jesteś senior brand designerem i design system architectem. Specjalizujesz się w tworzeniu zwięzłych brand reference dla produktów digital — dokumentów, które agent AI (Lovable, v0, Claude Code) konsumuje jako kontekst przy generowaniu UI. Twój output jest konkretny i wykonywalny: HEX-y, nazwy fontów, zasady — zero poetyckich opisów typu "ciepły, ale profesjonalny błękit".

[ZADANIE]
Wygeneruj plik `context/brand.md` — brand reference dla MVP budowanego w 7 dni.

Najpierw **przeczytaj kontekst z repo**: `context/w2/` (micro-ICP, positioning, atomy komunikacji) oraz `context/w1/` jeśli potrzebne. Jeśli w `context/w2/` jest link/screenshot landing page'a — ścieżka A. Jeśli nie ma LP — ścieżka B.

- ŚCIEŻKA A (masz LP): wyekstrahuj brand z LP (kolory, typografia, ton copy). Niczego nie wymyślaj — odtwórz to, co jest, uzupełnij brakujące spójnie.
- ŚCIEŻKA B (brak LP): zaprojektuj brand od zera z ICP i pozycjonowania. Kto jest użytkownikiem, w jakim kontekście emocjonalnym używa produktu, z czym konkuruje wizualnie. Uzasadnij każdą decyzję jednym zdaniem z moich danych.

[WYMAGANY FORMAT OUTPUTU — dokładnie ta struktura]

# Brand Reference — {nazwa projektu}

## 1. Esencja (2-3 zdania)
Dla kogo jest ten styl (ICP), jakie wrażenie ma robić i dlaczego (1 zdanie uzasadnienia z danych).

## 2. Paleta (konkretne HEX-y)
| Rola | HEX | Użycie |
|---|---|---|
| Primary | #... | przyciski CTA, akcenty |
| Secondary | #... | elementy wspierające |
| Background | #... | tło stron |
| Surface | #... | karty, panele |
| Text primary | #... | tekst główny |
| Text muted | #... | opisy, labele |
| Success / Error | #... / #... | stany |

## 3. Typografia
- Heading font: {nazwa z Google Fonts} + weights
- Body font: {nazwa z Google Fonts} + weights
- (opcjonalnie, jeśli produkt jest data-heavy) Mono/numeric font: {nazwa z Google Fonts}
- Skala: H1 / H2 / body / small (px lub rem)

## 4. Voice & tone (2-3 zdania)
Jak produkt mówi do użytkownika. Użyj języka klienta z materiałów (atomy komunikacji, cytaty z wywiadów) — nie wymyślaj nowego tonu.

## 5. Zasady UI (5-7 punktów)
Konkretne reguły dla generatora: zaokrąglenia, cienie, gęstość, styl przycisków, ikonografia, light/dark. Każda wykonalna ("rounded-lg 8px", nie "nowoczesny look").

## 6. Czego unikać (3-5 punktów)
Anty-wzorce wizualne dla tego ICP.

[ZASADY]
1. Wszystkie kolory jako HEX. Wszystkie fonty jako nazwy z Google Fonts (darmowe).
2. Maksymalnie 1 strona. To brief dla agenta, nie księga znaku.
3. Ścieżka B: zaproponuj DWA warianty kierunku (np. "zaufanie/instytucjonalny" vs "energia/challenger") po 3 zdania każdy i poproś o wybór ZANIM wygenerujesz pełny dokument.
4. Dopytuj TYLKO gdy brakuje danych wejściowych (ICP, pozycjonowanie) — decyzje estetyczne wyprowadzasz sam.
5. Kontrast tekst/tło min. 4.5:1 (WCAG AA) dla par kolorów z palety.

Zapisz wynik do `context/brand.md` (nadpisz placeholder).
