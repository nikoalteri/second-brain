# Phase 26: Debt & subscription totals (chatbot + dashboard) - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-09-17
**Phase:** 26-debt-and-subscription-totals-chatbot-and-dashboard
**Areas discussed:** Surface (chatbot vs dashboard), Subscription normalization, Debt scope, Chatbot intent organization

---

## Surface

**Question:** Le somme (abbonamenti attivi, prestiti da restituire, debito carte, totale debiti) le vuoi solo nel chatbot, o anche come nuovi numeri in Dashboard?

**User's choice:** Chatbot e Dashboard insieme.

---

## Subscription normalization

| Option | Description | Selected |
|--------|-------------|----------|
| Equivalente mensile normalizzato | Riusa calculateMonthlyCost() — totale comparabile | ✓ (as highlight) |
| Somma grezza per frequenza originale | Nessuna conversione | ✓ (as per-item detail) |

**User's answer (free text):** "perché non entrambe le opzioni?"
**Resolution:** Highlight = normalized monthly-equivalent total (D-01). Per-subscription items keep their original amount/frequency (D-02) — both pieces of information delivered together, nothing mutually exclusive here since the ChatIntent shape already separates aggregate (highlight) from detail (items).

---

## Debt scope

**Question:** La "somma dei debiti" combinata: solo prestiti + carte di credito, o includere altro?

**User's answer:** "perché non entrambe le opzioni?" (interpreted as: compute all three requested figures — loans total, cards total, and their combination — rather than picking just the combined one)
**Resolution:** All three figures computed (D-03). No other debt source exists in the current data model to add as a fourth.

---

## Chatbot intent organization

| Option | Description | Selected |
|--------|-------------|----------|
| Un intent per ciascuno | Coerente col pattern esistente | ✓ |
| Un unico intent "riepilogo" | Tutto in una risposta | ✓ |

**User's answer:** "perché non entrambe le opzioni?"
**Resolution:** Both — 4 single-topic intents plus a 5th `debt_overview` combined intent (D-04).

---

## The agent's Discretion

- Se debt_overview riusa internamente gli altri 4 intent o duplica la query (nessuna duplicazione SQL comunque)
- Forma esatta della risposta DashboardController (nuovo metodo vs estensione di charts())
- Convenzione valuta lato Dashboard

## Deferred Ideas

Nessuna oltre lo scope di questa fase.
