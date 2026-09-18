# Phase 24: Cash-flow forecast ("safe to spend") - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-09-17
**Phase:** 24-cash-flow-forecast-safe-to-spend
**Areas discussed:** Horizon, Included commitments, Balance baseline, Surface

---

## Horizon

| Option | Description | Selected |
|--------|-------------|----------|
| Prossimi 30 giorni, fisso | Copre un ciclo mensile tipico | ✓ |
| Configurabile (7/14/30/60 giorni) | Più flessibile ma richiede un parametro | |

**User's choice:** Prossimi 30 giorni, fisso.

---

## Included commitments

| Option | Description | Selected |
|--------|-------------|----------|
| Rate prestiti in scadenza | LoanPayment, già tracciate | ✓ |
| Rinnovi subscription in scadenza | next_renewal_date | ✓ |
| Pagamento carta di credito del ciclo corrente | fixed_payment del ciclo attivo | ✓ |

**User's choice:** Tutte e tre.
**Note (scouting):** `UpcomingPaymentsService::forUser()` già restituisce esattamente questi tre tipi con `amount`/`due_date` — riuso diretto invece di nuova logica di aggregazione.

---

## Balance baseline

| Option | Description | Selected |
|--------|-------------|----------|
| Tutti i conti attivi | Semplice, nessuna distinzione | |
| Escludi i conti collegati a un SavingGoal attivo | Più accurato come "disponibile davvero" | ✓ |

**User's choice:** Escludi i conti collegati a un SavingGoal attivo.

---

## Surface

| Option | Description | Selected |
|--------|-------------|----------|
| Nuovo dato sulla Dashboard esistente (SPA) | Coerente col resto dell'app | ✓ |
| Nuovo comando Artisan | Solo CLI | |
| Entrambi | Widget + comando dettagliato | |

**User's choice:** Nuovo dato sulla Dashboard esistente.

---

## The agent's Discretion

- Forma esatta della risposta API (estendere charts() vs nuovo endpoint)
- Se mostrare anche il breakdown dei 30 giorni oltre al numero netto
- Dettagli di posizionamento nel componente SPA

## Deferred Ideas

- Orizzonte configurabile (7/14/60 giorni)
- Gestione di conti parzialmente accantonati per un obiettivo di risparmio
- Comando Artisan dedicato per un breakdown dettagliato
