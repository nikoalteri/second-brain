# Phase 27: Automated statement reconciliation - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-09-17
**Phase:** 27-automated-statement-reconciliation
**Areas discussed:** Comparison mechanism

---

## Comparison mechanism

| Option | Description | Selected |
|--------|-------------|----------|
| Aggiungi un campo opzionale "saldo finale estratto conto" all'import (Fase 25) | Vera riconciliazione bancaria contro un dato esterno reale | ✓ |
| Solo controllo interno: nessun movimento sparisce durante l'import | Più semplice, non cattura movimenti mai importati | |

**User's choice:** Campo opzionale "saldo finale estratto conto" per batch di import.
**Notes:** Esempio fornito dall'utente: import settembre, saldo dichiarato 2.450,00€, saldo calcolato 2.410,00€ → discrepanza di 40€ notificata. Questa decisione ha richiesto un addendum retroattivo alla Fase 25 (D-06), già registrato in `25-CONTEXT.md` e `25-DISCUSSION-LOG.md`.

---

## The agent's Discretion

- Tolleranza per "mismatch" (confronto esatto vs banda di arrotondamento)
- Se persistere uno stato riconciliato/non riconciliato sul batch di import
- Payload esatto della notifica

## Deferred Ideas

- Controllo di sola coerenza interna (senza dato esterno) — scartato esplicitamente in favore del confronto con saldo reale dichiarato
