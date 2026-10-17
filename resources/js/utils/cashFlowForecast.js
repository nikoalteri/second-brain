// Copy for the coverage flags the forecast API can return; unknown codes fall back to the raw code.
const FLAG_LABELS = {
    overdue_obligations: 'Overdue payments that are not settled are not part of the figure.',
    posting_soft_deleted: 'Some payments have a deleted posting; they are counted but may change.',
    paid_without_posting: 'Some payments are marked paid but have no account movement; the balance may not reflect them.',
    card_charge_projected: 'Card-billed renewals are card charges, not bank debits, and are not deducted.',
    source_account_unavailable: 'Some payments have no usable source account and are not deducted.',
    unknown_currency: 'Some accounts or payments have no valid currency and are left out.',
    bank_data_not_reconciled: 'Bank balances are not reconciled against statements.',
};

const REASON_LABELS = {
    already_in_balance: 'Already in the balance',
    posting_soft_deleted: 'Posting was deleted',
    paid_without_posting: 'Marked paid, no account movement',
    card_charge_recorded: 'Card charge, not a bank debit',
    card_charge_projected: 'Projected card charge, not a bank debit',
    source_account_unavailable: 'No usable source account',
    unknown_currency: 'Unknown currency',
    overdue_unsettled: 'Overdue, not settled',
    source_account_excluded: 'Paid from an excluded account',
};

export function flagLabel(code) {
    return FLAG_LABELS[code] ?? code;
}

export function reasonLabel(reason) {
    return reason ? (REASON_LABELS[reason] ?? reason) : null;
}

// One row per currency, exactly as the API returned them: amounts are never summed across currencies.
export function currencyRows(forecast) {
    return (forecast?.currencies ?? []).map((entry) => ({
        currency: entry.currency,
        available: Number(entry.available_balance),
        committed: Number(entry.counted_commitments),
        remaining: Number(entry.estimated_remaining),
        includedAccounts: entry.included_account_count,
        excludedAccounts: entry.excluded_account_count,
        obligations: (entry.obligations ?? []).map((item) => ({
            ...item,
            counted: Number(item.cash_outflow_amount),
            face: Number(item.face_amount),
            reasonLabel: reasonLabel(item.reason),
        })),
        overdue: (entry.overdue_obligations ?? []).map((item) => ({
            ...item,
            face: Number(item.face_amount),
            reasonLabel: reasonLabel(item.reason),
        })),
        complete: entry.complete === true,
    }));
}

export function coverageMessages(forecast) {
    return (forecast?.coverage?.flags ?? []).map((flag) => ({
        code: flag.code,
        count: flag.count,
        text: flagLabel(flag.code),
    }));
}

// With no usable balance in any currency there is nothing honest to show as a remainder.
export function hasEstimate(forecast) {
    return currencyRows(forecast).some((row) => row.includedAccounts > 0);
}

export function isIncomplete(forecast) {
    return forecast?.coverage?.complete !== true;
}
