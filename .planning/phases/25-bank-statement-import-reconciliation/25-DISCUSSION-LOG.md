# Phase 25: Bank statement import & reconciliation - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-09-17
**Phase:** 25-bank-statement-import-reconciliation
**Areas discussed:** File formats, Duplicate handling, Review flow, Format priority, Column mapping, Scope (accounts vs cards)

---

## File formats available to the user

**User's answer (free text):** CSV, PDF (estratto conto), forse anche Excel.

## Duplicate handling

| Option | Description | Selected |
|--------|-------------|----------|
| Rilevali e chiedi conferma prima di creare un duplicato | Match su data+importo+descrizione, mostrato per revisione | ✓ |
| Importa sempre tutto, deduplica a mano dopo | Più semplice, rischio doppioni | |

**User's choice:** Rileva e chiedi conferma.

## Review flow

| Option | Description | Selected |
|--------|-------------|----------|
| Stato "in bozza" da rivedere/categorizzare prima di confermare | Più sicuro, richiede UI di revisione | ✓ |
| Importa direttamente come Transaction confermate | Più veloce, rischio di sporcare saldi/budget | |

**User's choice:** Stato "in bozza" con revisione.

## Format priority (follow-up)

| Option | Description | Selected |
|--------|-------------|----------|
| Solo CSV/Excel per ora | Copre la maggior parte dei casi con export strutturato | ✓ |
| Includi anche il PDF da subito | Più completo ma più rischio/sforzo | |

**User's choice:** Solo CSV/Excel per ora; PDF rimandato.

## Column mapping

| Option | Description | Selected |
|--------|-------------|----------|
| Mapping configurabile al primo import, poi riutilizzato | Funziona con qualsiasi banca | ✓ |
| Formato fisso per una banca specifica | Più veloce ora, si rompe se cambia banca/formato | |

**User's choice:** Mapping configurabile.

## Scope: accounts vs credit cards

| Option | Description | Selected |
|--------|-------------|----------|
| Solo conti (Account → Transaction) | Ambito più contenuto | |
| Conti e carte di credito | Copre anche CreditCardExpense | ✓ |

**User's choice:** Conti e carte di credito.

---

## The agent's Discretion

- Modello dati esatto per lo staging (nuova entità dedicata, non uno status sul modello core)
- Algoritmo/soglia esatta di duplicate-matching
- Superficie UI (Filament vs SPA vs entrambi)
- Possibilità di modificare un mapping salvato in seguito

## Deferred Ideas

- Parsing PDF degli estratti conto
- Integrazione Open Banking/bank-feed live — resta esplicitamente fuori scope (ROADMAP.md, Deferred Longer-Term Product Ideas)

## Addendum (2026-09-17, durante il discuss-phase della Fase 27)

Durante lo scoping della riconciliazione automatica (Fase 27) è emerso che serve un dato di confronto esterno reale, non solo le transazioni importate stesse. Aggiunta D-06: campo opzionale "saldo finale estratto conto" per batch di import, inserito manualmente dall'utente leggendolo dal proprio estratto reale.
