export function useCurrency() {
    function formatCurrency(amount, currency = 'EUR') {
        return new Intl.NumberFormat('it-IT', {
            style: 'currency',
            currency,
            minimumFractionDigits: 2,
        }).format(amount ?? 0);
    }

    function colorClass(amount, context = 'signed') {
        if (context === 'balance') return 'text-blue-400';
        if (context === 'loan') return 'text-amber-400';
        if (context === 'card') return 'text-purple-400';
        if (context === 'subscription') return 'text-amber-400';
        if (amount > 0) return 'text-emerald-400';
        if (amount < 0) return 'text-red-400';
        return 'text-gray-400';
    }

    function formatSigned(amount, currency = 'EUR') {
        const formatted = formatCurrency(Math.abs(amount), currency);
        if (amount > 0) return `+${formatted}`;
        if (amount < 0) return `-${formatted}`;
        return formatted;
    }

    return { formatCurrency, colorClass, formatSigned };
}
