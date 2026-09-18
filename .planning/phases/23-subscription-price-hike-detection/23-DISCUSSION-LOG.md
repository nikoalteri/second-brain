# Phase 23: Subscription price-hike detection - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-09-17
**Phase:** 23-subscription-price-hike-detection
**Areas discussed:** Detection method, Threshold, Subscriptions without tracked history

---

## Detection method

| Option | Description | Selected |
|--------|-------------|----------|
| Confronto tra addebiti reali consecutivi | Confronta l'importo appena addebitato con quello del rinnovo precedente | ✓ |
| Confronto tra costo configurato e ultimo addebito | Confronta monthly_cost/annual_cost attuale con l'ultimo addebito registrato | |

**User's choice:** Confronto tra addebiti reali consecutivi.
**Notes:** Esempio fornito dall'utente: Netflix 15.99€ dopo un rinnovo precedente a 13.99€ → aumento di 2.00€ (+14.3%) rilevato.

---

## Threshold

| Option | Description | Selected |
|--------|-------------|----------|
| Qualsiasi aumento (> 0) | Nessuna soglia da tarare | ✓ |
| Solo sopra una soglia minima | Evita rumore da arrotondamenti | |

**User's choice:** Qualsiasi aumento (> 0).

---

## Subscriptions without tracked history

| Option | Description | Selected |
|--------|-------------|----------|
| Escludile dalla Fase 23 | Coerente coi dati disponibili | ✓ |
| Notificale come promemoria di controllo manuale | Comportamento diverso dal resto della fase | |

**User's choice:** Escludile dalla Fase 23.

---

## The agent's Discretion

- Punto esatto di integrazione nel codice (dentro processRenewal() o come step separato nel digest di Fase 22)
- Payload esatto della Notification (related_model/related_id)
- Se calcolare/mostrare anche la percentuale di aumento oltre al delta assoluto

## Deferred Ideas

- "Ultimo prezzo noto" inserito manualmente per le subscription senza auto_create_transaction
- Soglia minima di rumore, se in pratica risultasse troppo rumoroso con "qualsiasi aumento"
