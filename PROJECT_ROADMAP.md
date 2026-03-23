# 🧠 Second Brain – Roadmap Progetto 2026

> **Stack:** Laravel 12 + Filament v3 (admin), Vue/Inertia (frontend), MySQL  
> **Focus iniziale:** modulo **Finance** completo, poi moduli **Second Brain**  
> **Ultimo aggiornamento:** 2026-03-23

---

## 📋 Legenda Status

| Badge | Significato |
|-------|-------------|
| ✅ | Completato e funzionante |
| 🟡 | In progress / Parzialmente fatto |
| ⏳ | Da fare |
| 🔧 | Richiede bug fix / refinement |

---

## 🌱 Fase 0 – Setup & Architettura

**Obiettivo:** avere una base solida di applicazione, ruoli e pannello admin.

### Implementazione

- ✅ Setup progetto Laravel 12, Filament v3, Vue/Inertia, MySQL, Auth
- ✅ Spatie Permissions integrato
- ✅ Definizione ruoli: `superadmin`, `admin`, `user`
- ✅ Pannello `admin` come unico panel Filament (gestione utente + configurazione)
- ✅ Struttura moduli (flag `enabled_modules` su `users` table)
- ✅ Trait condivisi:
  - `HasWorkdayCalculation` per skip weekend/festivi (riusato in Loans, CreditCards)
  - `HasUserScoping` per voci globali vs per utente

**Stato:** ✅ **COMPLETATA E FUNZIONANTE**

**File principali:**
- `app/Traits/HasWorkdayCalculation.php`
- `app/Traits/HasUserScoping.php`
- `app/Providers/AppServiceProvider.php`

---

## 💰 Fase 1 – Finance Core: Conti & Transazioni

**Obiettivo:** modello di base per la parte finanziaria giornaliera.

### Implementazione

- ✅ Tabelle principali:
  - `accounts` (tipi: bancario, contanti, investimento, fondo_emergenza, debito)
  - `transaction_types` (Entrate, Uscite, Trasferimento, Cashback)
  - `transaction_categories` con gerarchia (categorie + sottocategorie)
  - `transactions` con `is_transfer` e `transfer_pair_id`
  
- ✅ Logica trasferimenti:
  - doppia riga IN/OUT collegata da UUID
  - esclusi da cashflow/report categorie
  - inclusi in saldi e patrimonio netto

- ✅ CRUD + Filament Resources:
  - `AccountResource.php`
  - `TransactionResource.php`
  - `TransactionCategoryResource.php`
  - `TransactionTypeResource.php`

- ✅ Service layer:
  - `TransactionService.php` (creazione trasferimenti)

**Stato:** ✅ **IMPLEMENTATA E FUNZIONANTE**

**File principali:**
- Models: `app/Models/Account.php`, `Transaction.php`, `TransactionCategory.php`, `TransactionType.php`
- Resources: `app/Filament/Resources/Accounts/`, `Transactions/`, etc.
- Service: `app/Services/TransactionService.php`

---

## 📦 Fase 2 – Subscriptions

**Obiettivo:** gestire abbonamenti ricorrenti e costo mensile totale.

### Implementazione richiesta

- ⏳ Tabella `subscriptions`:
  - `name`, `monthly_cost`, `annual_cost` (calcolato)
  - `frequency` (mensile, annuale, trimestrale, semestrale)
  - `status` (attivo, non_attivo, non_si_riattiva)
  - `day_of_month` / `renewal_date`
  - `account_id`, `weight_percentage` sul totale attivi

- ⏳ Logica:
  - calcolo costo mensile totale
  - prossime scadenze abbonamenti
  - eventuale generazione automatica di movimenti su `transactions`

- ⏳ Collegamento dashboard:
  - widget "Costo Mensile Abbonamenti"
  - widget "Prossimi Rinnovi"

**Stato:** ⏳ **DA FARE**

**Stima:** 2–3 giorni effettivi

**Task:**
- [ ] Creare migration `create_subscriptions_table`
- [ ] Creare Model `Subscription.php`
- [ ] Creare SubscriptionResource (Filament)
- [ ] Creare SubscriptionService per calcoli
- [ ] Aggiungere widget dashboard

---

## 🧾 Fase 3 – Loans (Finanziamenti)

**Obiettivo:** gestire prestiti, piano rate, interessi e residuo.

### Implementazione

#### ✅ Già fatto:
- ✅ Tabelle:
  - `loans` con campi: `name`, `account_id`, `total_amount`, `monthly_payment`, `withdrawal_day`, `skip_weekends`, `start_date`, `end_date`, `total_installments`, `paid_installments`, `remaining_amount`, `status`
  - `loan_payments` con: `loan_id`, `due_date`, `actual_date`, `amount`, `status`, `notes`
  - `interest_rate`, `variable_rate_columns` (per supporto rate variabili)
  - `loan_payment_id` in `transactions` (collegamento)

- ✅ CRUD + Filament Resources:
  - `LoanResource.php` con relation manager `LoanPaymentsRelationManager`
  - Creazione piano rate automatico al salvataggio

- ✅ Logica:
  - Generazione piano rate con skip weekend via `HasWorkdayCalculation`
  - Sync automatico `paid_installments` + `remaining_amount`
  - Support rate variabili (importi diversi per rata)

- ✅ Service layer:
  - `LoanPaymentService.php` (gestione pagamenti)
  - Generazione transazioni automatiche

#### 🔧 Da raffinare:
- 🔧 Gestione rigenerazione piano rate (solo future / solo mancanti / full rebuild)
- 🔧 UX: refresh automatico pagina Loan quando cambi una rata (evento Livewire dal relation manager)

#### ⏳ Da implementare:
- ⏳ Supporto **prestiti revolving** (es. Amex):
  - tasso di interesse per loan
  - rata fissa (es. 250€)
  - calcolo interessi su residuo, split capitale/interessi
  - possibilità di aggiungere nuove spese che ricalcolano il piano futuro

**Stato:** 🟡 **IN PROGRESS – 80% COMPLETATA**

**File principali:**
- Models: `app/Models/Loan.php`, `LoanPayment.php`
- Resources: `app/Filament/Resources/Loans/`
- Service: `app/Services/LoanPaymentService.php`
- Database: Migrations `2026_03_17_*` e successive

**Stima refinement:** 1–2 giorni

---

## 💳 Fase 4 – Credit Cards

**Obiettivo:** gestire carte di credito, spese per mese competenza e addebiti futuri.

### Implementazione

#### ✅ Già fatto:
- ✅ Tabelle:
  - `credit_cards` con: `user_id`, `name`, `account_id`, `type` (charge/revolving), `credit_limit`, `current_balance`, `fixed_payment`, `interest_rate`, `stamp_duty_amount`, `status`, `start_date`, `statement_day`, `due_day`, `skip_weekends`
  - `credit_card_expenses` con: `credit_card_id`, `amount`, `description`, `date`, `category_id`
  - `credit_card_cycles` con: `credit_card_id`, `period_start_date`, `statement_date`, `due_date`, `total_spent`, `total_due`, `interest_amount`, `paid_amount`, `status`
  - `credit_card_payments` con: `credit_card_id`, `cycle_id`, `principal_amount`, `interest_amount`, `stamp_duty_amount`, `total_amount`, `status`, `due_date`
  - `credit_card_payment_id` in `transactions`

- ✅ CRUD + Filament Resources:
  - `CreditCardResource.php` con relation managers: `CyclesRelationManager`, `ExpensesRelationManager`, `PaymentsRelationManager`
  - Creazione form con validazione date cicli

- ✅ Service layer:
  - `CreditCardCycleService.php` (issueCycle, calcoli cicli)
  - `CreditCardExpenseService.php` (gestione spese)
  - `RevolvingCreditCalculator.php` (calcoli interessi daily balance)
  - `CreditCardBalanceService.php` (tracking debito)

- ✅ Logica:
  - Calcolo `withdrawal_date` con `HasWorkdayCalculation`
  - Residuo carta = somma importi non pagati
  - Support **REVOLVING**:
    - spesa → aumenta current_balance
    - ciclo issue → calcola interest + payment con rata fissa
    - payment PAID → riduce balance via observer
  - Support **CHARGE**:
    - spesa → residuo
    - ciclo issue → crea pagamento full amount
    - payment PAID → riduce balance

- ✅ Observers:
  - `CreditCardCycleObserver.php` (auto-crea payment quando cycle PAID)
  - `CreditCardPaymentObserver.php` (riduce balance quando payment PAID)

#### 🔧 Fissato di recente:
- 🔧 Validazione card config prima di issue cycle (fixed_payment + interest_rate richiesti)
- 🔧 Form fields sempre visibili (removed problematic conditional visibility)
- 🔧 Live form updates quando cambi il type

#### ⏳ Da testare / refinare:
- ⏳ Validazione cross-field date ciclo (period_start_date <= statement_date <= due_date)
- ⏳ Test completo con dati reali dell'utente

**Stato:** 🟡 **IN PROGRESS – 85% COMPLETATA**

**File principali:**
- Models: `app/Models/CreditCard.php`, `CreditCardCycle.php`, `CreditCardExpense.php`, `CreditCardPayment.php`
- Resources: `app/Filament/Resources/CreditCards/`
- Services: `app/Services/CreditCardCycleService.php`, `CreditCardExpenseService.php`, `RevolvingCreditCalculator.php`, `CreditCardBalanceService.php`
- Observers: `app/Observers/CreditCardCycleObserver.php`, `CreditCardPaymentObserver.php`
- Database: Migrations `2026_03_18_*` e successive

**Stima test + refinement:** 1–2 giorni

---

## 📊 Fase 5 – Dashboard Finance & Report

**Obiettivo:** avere una vista sintetica e storica di tutta la finanza.

### Widget dashboard previsti

#### ⏳ Da implementare:

- ⏳ **Patrimonio Netto Totale**  
  Somma conti `is_active`, esclusi `is_debt`

- ⏳ **Debiti Totali**  
  Somma residuo Loans (`remaining_amount`) + residuo carte (spese non pagate + revolving)

- ⏳ **Costo Mensile Abbonamenti**  
  Somma `subscriptions` attive (collegato a Fase 2)

- ⏳ **Prossimi Rinnovi**  
  Abbonamenti + carte (rinnovi) con badge: danger (≤3g), warning (≤7g), success

- ⏳ **Rate in Scadenza**  
  Loans + Credit Cards prossimi 7 giorni

- ⏳ **Cashflow Mese Corrente**  
  Entrate vs Uscite vs Rate, esclusi trasferimenti

- ⏳ **Uscite per Categoria**  
  Torta con drill-down sottocategorie

- ⏳ **Patrimonio nel Tempo**  
  Linea mese per mese

### Report / Pivot

- ⏳ Tabella pivot mensile:
  - Entrate, Uscite, Rate, Uscite totali, Differenza
  - Confronto mese su mese
  - Filtro per anno

- ⏳ Export CSV/PDF

- ⏳ Import da Excel/CSV con mapping colonne e preview

**Stato:** ⏳ **DA FARE**

**Stima:** 3–5 giorni

**Task:**
- [ ] Creare DashboardPage in Filament
- [ ] Implementare Widget base (Filament Widget)
- [ ] Creare Stat cards per KPI
- [ ] Creare Chart widgets (Apex Charts / Chart.js)
- [ ] Implementare Report page con pivot table
- [ ] Aggiungere export CSV/PDF

---

## 🏋️ Fase 6 – Modulo Salute & Fitness

**Obiettivo:** tracking salute fisica, visite e parametri fondamentali.

### Implementazione richiesta

- ⏳ Tabelle:
  - `health_metrics` (peso, altezza, BMI, % grasso, data)
  - `workouts` (tipo dinamico, durata, calorie, note, data)
  - `medical_records` (tipo visita, dottore, note, allegato PDF, data)
  - `medications` (nome, dosaggio, frequenza, date inizio/fine)
  - `blood_tests` (esame, valore, unità, range, data)

- ⏳ UI:
  - pannelli registrazione
  - panoramica salute (weight trend, BMI, workout streak)

**Stato:** ⏳ **DA FARE**

**Stima:** 5–7 giorni

---

## 🎯 Fase 7 – Produttività & Habit Tracker

**Obiettivo:** produrre un mini-Second Brain produttività integrato.

### Implementazione richiesta

- ⏳ Tabelle:
  - `habits` (nome, frequenza, streak, heatmap)
  - `goals` (titolo, descrizione, scadenza, % completamento, area)
  - `projects` (nome, milestone, stato, scadenza)
  - `journal` (testo, mood 1–5, data)
  - `notes` (titolo, corpo, tag dinamici, data)

**Stato:** ⏳ **DA FARE**

**Stima:** 5–7 giorni

---

## 👥🏠🍝✈️ Fase 8 – Relazioni, Casa, Cucina, Viaggi

**Obiettivo:** completare il lato "Second Brain" non finanziario.

### Implementazione richiesta

- ⏳ **Relazioni & Casa**:
  - `contacts` (anagrafiche, compleanni, gruppi, gift ideas, ultima interazione)
  - `documents` (documenti casa, scadenze, allegati)
  - `vehicles` (veicoli, scadenze bollo/assicurazione/tagliando)
  - `home_expenses` (spese casa ricorrenti)

- ⏳ **Cucina**:
  - `recipes` (ingredienti JSON, dosi, procedimento, foto, rating, tag)
  - `meal_plans` (settimana, giorni, lista spesa)

- ⏳ **Viaggi**:
  - `trips` (destinazione, date, budget, stato)
  - `trip_wishlist` (destinazioni, priorità, note)

**Stato:** ⏳ **DA FARE**

**Stima:** 8–12 giorni

---

## ⚙️ Fase 9 – Settings, Notifiche, Backup, Import

**Obiettivo:** rifinitura e operatività "da prodotto".

### Implementazione richiesta

- ⏳ Settings dinamici:
  - tipi documento, tipi veicolo, categorie globali, tag per contesto

- ⏳ Notifiche:
  - scadenze finanziarie, abbonamenti, visite, documenti

- ⏳ Backup e restore:
  - strategie backup DB/app
  - eventuale export JSON/YAML impostazioni

- ⏳ Import Excel/CSV:
  - wizard import per Budget_2026.xlsx e altri template

**Stato:** ⏳ **DA FARE**

**Stima:** 3–5 giorni

---

## 🎯 Milestone suggerite

| Milestone | Fasi | Status | Stima |
|-----------|------|--------|-------|
| **Finance Core completo** | 1–5 | 🟡 70% | 7–10 gg |
| **Health + Productivity** | 6–7 | ⏳ 0% | 10–14 gg |
| **Full Second Brain** | 8 | ⏳ 0% | 8–12 gg |
| **Hardening & SaaS-ready** | 9 | ⏳ 0% | 3–5 gg |

---

## 📊 Riepilogo Stato Progetto

| Fase | Descrizione | Status | % Completamento | Prossimi step |
|------|-------------|--------|-----------------|---------------|
| 0 | Setup & Architettura | ✅ | 100% | N/A |
| 1 | Finance Core: Conti & Transazioni | ✅ | 100% | N/A |
| 2 | Subscriptions | ⏳ | 0% | Creare migration + model |
| 3 | Loans | 🟡 | 80% | Raffinare rate variabili + revolving |
| 4 | Credit Cards | 🟡 | 85% | Test con dati reali + refinement |
| 5 | Dashboard Finance | ⏳ | 0% | Creare widgets + reports |
| 6 | Salute & Fitness | ⏳ | 0% | Progettazione DB + UI |
| 7 | Produttività & Habit | ⏳ | 0% | Progettazione DB + UI |
| 8 | Relazioni, Casa, Cucina, Viaggi | ⏳ | 0% | Progettazione DB + UI |
| 9 | Settings, Notifiche, Backup | ⏳ | 0% | Progettazione + UI |

---

## 🚀 Prossime azioni consigliate

### Breve termine (1–2 settimane)
1. **Completare Credit Cards** (Fase 4):
   - Test con dati reali dell'utente
   - Bug fix sui calcoli interessi
   - Validazione cicli corretta
   - Possibile: implementare revolving avanzato (rate variabili)

2. **Completare Loans** (Fase 3):
   - Aggiungere support prestiti revolving
   - Raffinare rigenerazione piano rate
   - UX improvements (refresh Livewire)

### Medio termine (2–4 settimane)
3. **Fase 2 – Subscriptions** (complemento a Finance)
4. **Fase 5 – Dashboard Finance** (consolidamento Finance Core)

### Lungo termine (post-Finance)
5. **Fase 6–7 – Health + Productivity** (Second Brain produttività)
6. **Fase 8–9 – Full integration** (Relazioni, Settings, Backup)

---

## 🔗 Risorse

**Git commits recenti (Credit Cards refactoring):**
- `6d2933e` - fix: validate card configuration before allowing cycle issue
- `166a20b` - fix: add live update trigger for conditional fields in credit card form
- `248f0c2` - fix: use correct callable Set signature for Filament Schemas
- `2799c4d` - fix: add live() to conditional fields for proper reactivity
- `9f84ef2` - fix: make conditional fields always visible for revolving cards

**Test suite:**
- `tests/Unit/RevolvingCreditCalculatorTest.php` (8 tests)
- `tests/Unit/CreditCardBalanceServiceTest.php` (15 tests)
- `tests/Unit/CreditCardCycleServiceTest.php` (6+ tests)
- `scripts/validate-credit-card-calculation.php` (validation tool)

---

**Ultimo aggiornamento:** 2026-03-23 19:40  
**Responsabile:** Copilot AI  
**Contatto:** [user feedback]
