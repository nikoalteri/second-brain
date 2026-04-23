export default {
    localization: {
        fallbackProbe: 'English fallback probe',
    },
    shell: {
        nav: {
            dashboard: 'Dashboard',
            accounts: 'Accounts',
            transactions: 'Transactions',
            reports: 'Reports',
            loans: 'Loans',
            creditCards: 'Credit Cards',
            subscriptions: 'Subscriptions',
        },
        userMenu: {
            account: 'Account',
            privateAccount: 'Private account',
            signedIn: 'Signed in',
            userDetails: 'User details',
            settings: 'Settings',
            openAdmin: 'Open Admin',
            signOut: 'Sign out',
            toggleMenu: 'Toggle menu',
            closeMenu: 'Close menu',
        },
    },
    settings: {
        title: 'Settings',
        preferences: 'Preferences',
        preview: 'Preview',
        actions: {
            save: 'Save settings',
            saving: 'Saving...',
        },
        feedback: {
            saved: 'Settings saved.',
            updated: 'Settings updated.',
            saveError: 'Could not save your settings. Please try again.',
        },
        fields: {
            theme: 'Theme',
            language: 'Language',
            notifications: 'Notifications',
            privacy: 'Privacy',
        },
        options: {
            theme: {
                light: 'Light',
                dark: 'Dark',
                system: 'System',
            },
            language: {
                en: 'English',
                it: 'Italiano',
            },
            notifications: {
                all: 'All toasts',
                important_only: 'Errors only',
            },
            privacy: {
                visible: 'Show profile details',
                private: 'Hide email and user ID',
            },
        },
        previewRows: {
            theme: {
                light: 'The app stays in light mode.',
                dark: 'The app stays in dark mode.',
                system: 'The app follows your system light/dark preference.',
            },
            language: {
                en: 'Dates and currency use English formatting.',
                it: 'Dates and currency use Italian formatting.',
            },
            notifications: {
                all: 'Success and error toasts are both shown.',
                important_only: 'Success toasts are muted; errors still appear.',
            },
            privacy: {
                visible: 'Your profile shows email and user ID.',
                private: 'Your profile hides email and user ID.',
            },
        },
    },
};
