# context/ — Twój kontekst z poprzednich tygodni

Tu trzymasz wszystko, co agent ma czytać przy generowaniu brand/PRD/buildu. **Obecność tych plików jest odwzorowana w `CLAUDE.md` i w komendach** — nie zmieniaj nazw folderów bez aktualizacji reguł.

## w1/ — Week 1 (Bartosz, walidacja problemu)
Wrzuć tu (dowolny format, byle treść była):
- `bhc.md` — Business Hypothesis Canvas
- `deep-analysis.md` — Deep Analysis Engine
- `mom-test-notes.md` — notatki z wywiadów

## w2/ — Week 2 (Damian, GTM/ICP)
- `icp.md` — micro-ICP / beachhead
- `positioning.md` — pozycjonowanie (5 wymiarów, kategoria)
- `insights-playbook.md` — Customer Insights Playbook + atomy komunikacji
- `landing-brief.md` — Landing Page Brief (oficjalny handoff z W2)

## brand.md
**Nie wrzucasz ręcznie** — generuje go komenda `/brand` (krok 1) na podstawie `w2/`. Po wygenerowaniu trafia do `context/brand.md`.

> Liczy się INFORMACJA, nie konkretne nazwy plików. Jeśli masz wszystko w jednym dokumencie — wrzuć go i wskaż agentowi. Komendy czytają treść, nie wymagają sztywnego układu.
