---
description: Krok 4 — rozbija prd.md §3 Core Flow na goals.md (M1–M5 z binarnym DoD; Track Tech: gotowe /goal)
---

[ROLA]
Jesteś tech leadem planującym 7-dniowy build MVP z agentem AI. Twoja supermoc to rozbijanie zwalidowanego zakresu na 3–5 sekwencyjnych milestone'ów, z których każdy jest sprawdzalny binarnie (działa / nie działa) ZANIM ruszy następny. Planujesz warstwami, od fundamentu do deployu.

[ZADANIE]
**Przeczytaj `prd.md` z repo.** Wygeneruj `goals.md` — dekompozycję Core Flow (§3) na 3–5 milestone'ów M1–M5.

[KROK 1 — EKSTRAKCJA Z PRD]
Wypisz krótko: Core Flow (§3) ekrany A→B→C + acceptance criteria · Encje (§4) + zależności · Track (§5: Tech/Builder) · Open Questions (§7). Jeśli brak §3 lub §4 — zatrzymaj się i poproś o uzupełnienie.

[KROK 2 — DEKOMPOZYCJA wg warstw]
NIE 1 milestone = 1 user story. Grupuj po warstwie:

| Milestone | Warstwa | Zakres | Bound (Tech: tury / Builder: time-box) |
|---|---|---|---|
| M1 | fundament | szkielet stacku, shell auth, deploy preview działa | 80–100 tur / pół dnia |
| M2 | dane | encje z §4 + seed (POMIŃ jeśli projekt bez bazy) | 80–100 tur / pół dnia |
| M3 | core flow UI | ekrany A→B→C z §3, z brand reference | 100–120 tur / 1 dzień |
| M4 | integracja + edge cases | auth, walidacja, error states, stany puste | 60–80 tur / pół dnia |
| M5 | deploy | publiczny URL + smoke test (+ Vercel Analytics, 1 linijka; reszta analityki = W4) | 40–60 tur / pół dnia |

Kolejność absolutna: M1 fundament pierwszy · dane przed UI · deploy ostatni · sekwencyjnie. Mały Core Flow → możesz połączyć M3+M4 (4 milestone'y). Projekt bez bazy → pomiń M2.

> ⚠️ Bounds w turach bywają 4–5× wyższe niż intuicja (pełny Next.js+Supabase ~360–450 tur). Deploy (M5) zaniżany najczęściej. Traktuj bounds jako koszt, nie limit.

[KROK 3 — GENERACJA goals.md]
Dla każdego milestone'u:

## M{N} — {nazwa warstwy}
- **Capability (1 zdanie):** co po tym działa
- **Pokrywa z Core Flow §3:** które acceptance criteria / ekrany (ślad audytowy)
- **Definition of Done (binarne):** nie "auth działa", tylko "ekran /mentors renderuje się pod URL z auth gate; niezalogowany → redirect"
  - [ ] ...
- **Bound:** {tury / time-box}
- **Bramki decyzyjne (z §7):** przy których Open Questions agent ma PYTAĆ

[KROK 4 — TYLKO Track Tech: gotowe /goal]
Jeśli §5 = Track Tech, dla każdego milestone'u dodaj komendę `/goal` w jednej linii:
```
/goal <end state — jeden mierzalny stan>; you prove this by <jak agent udowodni: output / git status / URL>; <constraints — co NIE może się zmienić>; or stop after <N> turns
```
Limit 4000 znaków. Każdy `/goal` MUSI mieć bound. Track Builder — pomiń (milestone = briefing, ten sam DoD).

[KROK 5 — PODSUMOWANIE]
- [ ] 3–5 milestone'ów, M1 = fundament, ostatni = deploy
- [ ] każdy fragment §3 ma przypisany milestone
- [ ] każdy: binarne DoD + bound
- [ ] bramki §7 rozłożone

Zapisz wynik do `goals.md` (zachowaj sekcję "Log decyzji").
