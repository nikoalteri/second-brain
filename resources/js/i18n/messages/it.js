export default {
    shell: {
        nav: {
            dashboard: 'Dashboard',
            accounts: 'Conti',
            transactions: 'Movimenti',
            reports: 'Report',
            loans: 'Prestiti',
            creditCards: 'Carte di credito',
            subscriptions: 'Abbonamenti',
        },
        userMenu: {
            account: 'Account',
            privateAccount: 'Account privato',
            signedIn: 'Accesso effettuato',
            userDetails: 'Dettagli utente',
            settings: 'Impostazioni',
            openAdmin: 'Apri Admin',
            signOut: 'Esci',
            toggleMenu: 'Apri menu',
            closeMenu: 'Chiudi menu',
        },
    },
    settings: {
        title: 'Impostazioni',
        preferences: 'Preferenze',
        preview: 'Anteprima',
        actions: {
            save: 'Salva impostazioni',
            saving: 'Salvataggio...',
        },
        feedback: {
            saved: 'Impostazioni salvate.',
            updated: 'Impostazioni aggiornate.',
            saveError: 'Impossibile salvare le impostazioni. Riprova.',
        },
        fields: {
            theme: 'Tema',
            language: 'Lingua',
            notifications: 'Notifiche',
            privacy: 'Privacy',
        },
        options: {
            theme: {
                light: 'Chiaro',
                dark: 'Scuro',
                system: 'Sistema',
            },
            language: {
                en: 'English',
                it: 'Italiano',
            },
            notifications: {
                all: 'Tutti i toast',
                important_only: 'Solo errori',
            },
            privacy: {
                visible: 'Mostra dettagli profilo',
                private: 'Nascondi email e ID utente',
            },
        },
        previewRows: {
            theme: {
                light: "L'app resta in modalità chiara.",
                dark: "L'app resta in modalità scura.",
                system: "L'app segue la preferenza chiaro/scuro del sistema.",
            },
            language: {
                en: 'Date e valuta usano il formato inglese.',
                it: 'Date e valuta usano il formato italiano.',
            },
            notifications: {
                all: 'I toast di successo e di errore vengono mostrati.',
                important_only: 'I toast di successo sono silenziati; gli errori restano visibili.',
            },
            privacy: {
                visible: 'Il tuo profilo mostra email e ID utente.',
                private: 'Il tuo profilo nasconde email e ID utente.',
            },
        },
    },
    labels: {
        budget: {
            noBudget: 'Nessun budget',
        },
        status: {
            active: 'Attivo',
            inactive: 'Inattivo',
        },
        categories: {
            none: 'Nessuna categoria',
            uncategorized: 'Senza categoria',
            living: 'Vita quotidiana',
            rent: 'Affitto',
            travel: 'Viaggi',
            housing: 'Casa',
            utilities: 'Utenze',
            groceries: 'Spesa',
            transport: 'Trasporti',
            health: 'Salute',
            entertainment: 'Tempo libero',
            shopping: 'Shopping',
            savings: 'Risparmi',
            insurance: 'Assicurazione',
            taxes: 'Tasse',
            salary: 'Stipendio',
            credit_card_payments: 'Pagamenti carta di credito',
        },
        accountTypes: {
            bank: 'Banca',
            cash: 'Contanti',
            investment: 'Investimento',
            emergency_fund: 'Fondo emergenza',
            debt: 'Debito',
        },
        transactionTypes: {
            earnings: 'Guadagni',
            expenses: 'Spese',
            expense: 'Spesa',
            transfer: 'Trasferimento',
            cashback: 'Cashback',
            income: 'Entrata',
            payment: 'Pagamento',
            credit_card_payment: 'Pagamento carta di credito',
        },
    },
};
