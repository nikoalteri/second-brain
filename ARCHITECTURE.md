# Second Brain — Architettura e Convenzioni

## Struttura del Progetto

- **app/Http/Controllers/**: Controller REST, orchestrano le richieste e delegano la logica ai service.
- **app/Services/**: Logica di business, aggregazioni, calcoli, validazioni avanzate.
- **app/Repositories/**: Accesso ai dati, query complesse, nessuna logica di business.
- **app/Observers/**: Gestione eventi model, delegano a service per side effect.
- **app/Enums/**: Enum PHP per stati, tipi, ruoli.
- **app/Livewire/**, **app/Filament/**: Componenti UI, solo orchestrazione e presentazione.
- **database/migrations/**: Migrazioni atomiche, senza side effect.
- **database/seeders/**: Seeders separati per test e produzione.
- **tests/**: Test unitari e di integrazione su controller, service, repository, componenti UI.

## Modularizzazione

- Ogni area funzionale (finance, health, productivity, ecc.) può essere isolata in un modulo.
- Valuta l’adozione di [nwidart/laravel-modules](https://github.com/nWidart/laravel-modules) per progetti di grandi dimensioni.
- I moduli devono avere controller, service, repository, policy, observer e test dedicati.

## Permessi e Ruoli

- Gestione tramite spatie/laravel-permission.
- Ruoli: superadmin (bypass), admin (tutto), finance_manager (solo finance), viewer (solo view).
- Permessi granulari per ogni risorsa e modulo.
- Policy dedicate per ogni modello.

## Best Practice

- Controller snelli, senza logica di business.
- Service e repository con responsabilità chiare.
- Observer minimali, delegano a service.
- Enum usate ovunque serva.
- Componenti UI solo per presentazione.
- Migrazioni atomiche e seeders puliti.
- Test coverage >80% sulle aree critiche.
- Configurazione centralizzata e sicura.

## Contributi

- Segui le convenzioni architetturali.
- Documenta ogni nuovo modulo, service, policy.
- Aggiorna README e ARCHITECTURE.md ad ogni modifica strutturale.

## Onboarding

- Installa le dipendenze con `composer install` e `npm install`.
- Copia `.env.example` in `.env` e configura le variabili.
- Esegui le migrazioni e i seeders.
- Avvia il server con `php artisan serve` e `npm run dev`.

## Documentazione

- Aggiorna questa guida per ogni nuova area o convenzione.
- Usa commenti chiari e docblock nei file critici.
