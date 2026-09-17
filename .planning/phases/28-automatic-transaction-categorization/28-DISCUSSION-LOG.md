# Phase 28: Automatic transaction categorization - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-09-17
**Phase:** 28-automatic-transaction-categorization
**Areas discussed:** Categorization source, Auto-assign vs suggest, Retroactive backfill

---

## Categorization source

| Option | Description | Selected |
|--------|-------------|----------|
| Impara dalle categorizzazioni passate | Cronologia, zero configurazione | |
| Regole esplicite | Definite dall'utente, precedenza garantita | |
| Entrambe | Regole hanno precedenza, cronologia come fallback | ✓ |

**User's choice:** Entrambe (regole esplicite hanno precedenza, cronologia come fallback).

---

## Auto-assign vs suggest

| Option | Description | Selected |
|--------|-------------|----------|
| Assegna in silenzio per inserimento manuale; suggerimento da confermare nelle bozze import (Fase 25) | Comportamento differenziato per contesto | ✓ |
| Sempre solo suggerimento, mai automatico | Più sicuro ma richiede sempre conferma | |

**User's choice:** Comportamento differenziato (silenzioso per inserimento manuale, suggerito per import).

---

## Retroactive backfill

| Option | Description | Selected |
|--------|-------------|----------|
| Solo da ora in poi | Non tocca dati storici | ✓ |
| Comando manuale per ricategorizzare lo storico | Opzionale, su richiesta esplicita | |

**User's choice:** Solo da ora in poi.

---

## The agent's Discretion

- Schema esatto di CategorizationRule
- Soglia di confidenza per il match storico
- Ambito del match storico (stesso conto/carta vs tutte le transazioni dell'utente)
- UI Filament per la gestione delle regole

## Deferred Ideas

- Comando opzionale per ricategorizzare retroattivamente le transazioni storiche non categorizzate
