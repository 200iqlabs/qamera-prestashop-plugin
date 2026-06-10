# Reguły projektu — {NAZWA PROJEKTU}

> Ten plik czyta agent przy KAŻDEJ sesji. Dwie warstwy:
> 1. **Struktura repo** (poniżej — zostaw, opisuje gdzie co leży)
> 2. **Konstytucja projektu** (sekcja na dole — wygenerujesz ją komendą `/reguly` z `prd.md` + `brand.md`)

## Struktura repozytorium (gdzie szukać kontekstu)

- `context/w1/` — artefakty Week 1: walidacja problemu (BHC, deep analysis, notatki z wywiadów Mom Test)
- `context/w2/` — artefakty Week 2: micro-ICP, positioning, Customer Insights Playbook, atomy komunikacji, Landing Page Brief
- `context/brand.md` — brand reference (HEX-y, fonty, voice & tone). Stosuj w KAŻDYM ekranie.
- `prd.md` — żywy dokument: CO budujemy, JEDEN Core Flow (§3). Wracasz przy każdej decyzji.
- `goals.md` — milestone'y M1–M5 + log decyzji.

Gdy budujesz UI — czytaj `context/brand.md` i stosuj wartości stamtąd. Gdy decydujesz o zakresie — czytaj `prd.md` §3 (Core Flow) i §6 (Out of scope). Nie buduj nic spoza Core Flow.

## Konwencje techniczne (domyślne — nadpisze je `/reguly`)

- Język UI: {polski / angielski — ustal}
- Responsywność: od 375px wzwyż
- Każdy widok z danymi: obsłuż stan pusty i stan błędu
- Nie proponuj zmiany stacku ani nowych bibliotek bez pytania

---

<!-- KONSTYTUCJA PROJEKTU — wygeneruj komendą /reguly i wklej poniżej tę linię -->

## Konstytucja projektu

_(pusto — uruchom `/reguly`, output trafia tutaj: stack lock, brand, twarde granice zakresu, protokół decyzji)_
