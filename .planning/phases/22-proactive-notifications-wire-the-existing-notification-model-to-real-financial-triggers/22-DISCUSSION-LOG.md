# Phase 22: Proactive notifications - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-09-17
**Phase:** 22-proactive-notifications-wire-the-existing-notification-model-to-real-financial-triggers
**Areas discussed:** Trigger scope, Delivery channel, Timing, Thresholds, Read/unread lifecycle, Deduplication

---

## Trigger scope

| Option | Description | Selected |
|--------|-------------|----------|
| Budget superato/vicino a soglia | BudgetAlertService calcola già alert_status per categoria — va solo collegato a una scrittura Notification | ✓ |
| Carta di credito: limite vicino o rata scaduta | Usa i dati già presenti in CreditCard/CreditCardCycle | ✓ |
| Rata prestito in scadenza | Dati già presenti in Loan/LoanPayment | ✓ |
| Rinnovo subscription imminente | Dati già presenti in Subscription; base anche per Fase 23 | ✓ |

**User's choice:** All four triggers in scope.

---

## Delivery channel

| Option | Description | Selected |
|--------|-------------|----------|
| Solo in-app (SPA + Filament) | Nessuna infrastruttura email — consigliato per uso personale | ✓ |
| In-app + email | Richiede configurare un mailer | |
| Solo email/digest | Nessuna UI in-app | |

**User's choice:** Solo in-app.

---

## Timing

| Option | Description | Selected |
|--------|-------------|----------|
| Digest giornaliero | Nuovo comando schedulato, coerente col pattern esistente (01:50/01:55/02:00) | ✓ |
| Tempo reale sugli eventi critici + digest per il resto | Es. carta oltre il limite via observer, resto in digest | |
| Solo tempo reale | Ogni evento genera notifica immediata via observer | |

**User's choice:** Digest giornaliero.

---

## Thresholds

| Option | Description | Selected |
|--------|-------------|----------|
| Fisse per ora (90% limite carta, 3 giorni prima scadenza) | Valori ragionevoli hardcoded | ✓ |
| Configurabili per utente da subito | Riusa pattern UserSetting (Fase 20) | |

**User's choice:** Soglie fisse per ora. Configurabilità per utente notata come idea futura.

---

## Read/unread lifecycle

| Option | Description | Selected |
|--------|-------------|----------|
| Segna come letta al click, nessuna pulizia automatica | Comportamento minimo | |
| Come sopra + pulizia automatica delle vecchie notifiche lette | Job di retention per non accumulare rumore | ✓ |

**User's choice:** Mark-as-read al click + pulizia automatica delle notifiche lette vecchie (finestra di retention lasciata a discrezione).

---

## Deduplication

| Option | Description | Selected |
|--------|-------------|----------|
| Una sola volta per condizione attiva (no ripetizioni) | Meno rumore; richiede una chiave di idempotenza | ✓ |
| Una al giorno finché la condizione resta vera | Più semplice ma riempie rapidamente il centro notifiche | |

**User's choice:** Una sola notifica per condizione attiva finché non si risolve.

---

## The agent's Discretion

- Meccanismo esatto della chiave di idempotenza (dedup)
- Finestra di retention per la pulizia delle notifiche lette
- Se la pulizia gira nello stesso comando del digest o in uno separato
- Presentazione esatta in Filament (resource dedicata vs widget)
- Struttura esatta del componente bell/badge nella SPA

## Deferred Ideas

- Soglie configurabili per utente (via UserSetting, pattern Fase 20)
- Consegna via email
- Generazione in tempo reale via observer per eventi critici
- Fase 23 (rilevamento aumento prezzo subscription) riuserà la consegna di questa fase, ma resta una fase separata
