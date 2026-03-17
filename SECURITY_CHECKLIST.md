# Security Checklist

- [ ] Tutte le password sono hashate (Hash::make)
- [ ] Nessuna password hardcoded in seeders di produzione
- [ ] Policy e gate corretti per ogni modello
- [ ] Permessi granulari tramite spatie/laravel-permission
- [ ] Aggiornamento periodico dipendenze (`composer update`, `npm update`)
- [ ] Audit di sicurezza (`composer audit`, `npm audit`)
- [ ] Variabili sensibili solo in .env (mai committate)
- [ ] Protezione endpoint critici con middleware auth e policy
- [ ] Logging e monitoraggio errori (es. Sentry, Telescope)
- [ ] Aggiornamento documentazione di sicurezza
