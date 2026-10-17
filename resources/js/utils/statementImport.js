// Pure helpers for the statement import flow: mapping guesses, copy and row rules.

export const DATE_FORMATS = [
    { value: 'Y-m-d', label: '2026-10-31 (year-month-day)' },
    { value: 'd/m/Y', label: '31/10/2026 (day/month/year)' },
    { value: 'm/d/Y', label: '10/31/2026 (month/day/year)' },
    { value: 'd-m-Y', label: '31-10-2026' },
    { value: 'd.m.Y', label: '31.10.2026' },
    { value: 'Y/m/d', label: '2026/10/31' },
    { value: 'd/m/y', label: '31/10/26' },
    { value: 'm/d/y', label: '10/31/26' },
];

const FLAG_LABELS = {
    already_imported: 'Already imported from an earlier file',
    within_file_duplicate: 'Identical line earlier in this file',
    currency_mismatch: 'Currency differs from the account',
    card_credit: 'Card credit or payment: not posted automatically',
    possible_transfer: 'Looks like a transfer between your accounts',
    possible_card_payment: 'Looks like a credit card payment',
    matches_generated_renewal: 'Matches a subscription renewal already generated',
};

const DUPLICATE_LABELS = {
    exact: 'Likely duplicate of an existing entry',
    probable: 'Possible duplicate of an existing entry',
};

// Same set the server skips on "confirm all ready".
export const REVIEW_FLAGS = [
    'already_imported', 'currency_mismatch', 'card_credit',
    'possible_transfer', 'possible_card_payment', 'matches_generated_renewal',
];

export function flagLabel(code) {
    return FLAG_LABELS[code] ?? code;
}

export function duplicateLabel(duplicateClass) {
    return DUPLICATE_LABELS[duplicateClass] ?? null;
}

// Human-readable notes for one row: duplicate class first, then each review flag.
export function rowNotes(row) {
    const notes = [];
    const duplicate = duplicateLabel(row.duplicate_class);

    if (duplicate) {
        notes.push(duplicate);
    }

    for (const code of row.flags?.codes ?? []) {
        notes.push(flagLabel(code));
    }

    return notes;
}

// A row "needs a decision" when "confirm all ready" would leave it alone.
export function needsDecision(row) {
    if (row.duplicate_class === 'exact' || row.duplicate_class === 'probable') {
        return true;
    }

    return (row.flags?.codes ?? []).some((code) => REVIEW_FLAGS.includes(code));
}

export function isPostable(row) {
    return row.status === 'draft' && row.normalized != null && !row.error;
}

export function minorToAmount(minor) {
    return Number(minor) / 100;
}

// Minor units from an editable decimal string; null when it is not a plain two-decimal amount.
export function amountToMinor(text) {
    const value = String(text ?? '').trim();

    if (!/^-?\d+(\.\d{1,2})?$/.test(value)) {
        return null;
    }

    return Math.round(Number(value) * 100);
}

const HEADER_PATTERNS = {
    date_column: /^(date|data|booking|fecha|datum)/i,
    amount_column: /(amount|importo|betrag|monto)/i,
    debit_column: /(debit|dare|addebit|uscite|withdraw)/i,
    credit_column: /(credit|avere|accredit|entrate|deposit)/i,
    description_column: /(desc|causale|memo|details|merchant|payee|narrative|reference)/i,
    category_column: /categor/i,
    currency_column: /(currency|divisa)/i,
};

function guessDateFormat(sample) {
    const value = String(sample ?? '').trim().split(/\s+/)[0];

    if (/^\d{4}-\d{2}-\d{2}$/.test(value)) return 'Y-m-d';
    if (/^\d{4}\/\d{2}\/\d{2}$/.test(value)) return 'Y/m/d';
    if (/^\d{2}\.\d{2}\.\d{4}$/.test(value)) return 'd.m.Y';
    if (/^\d{2}-\d{2}-\d{4}$/.test(value)) return 'd-m-Y';

    const parts = value.match(/^(\d{2})\/(\d{2})\/(\d{2,4})$/);

    if (parts) {
        const short = parts[3].length === 2;

        // A first part above 12 can only be a day; a second part above 12 can only be a day.
        if (Number(parts[2]) > 12) return short ? 'm/d/y' : 'm/d/Y';

        return short ? 'd/m/y' : 'd/m/Y';
    }

    return 'Y-m-d';
}

function guessSeparators(sample) {
    const value = String(sample ?? '').replace(/[^\d.,]/g, '');
    const lastComma = value.lastIndexOf(',');
    const lastDot = value.lastIndexOf('.');

    if (lastComma > lastDot) return { decimal_separator: ',', thousands_separator: '.' };

    return { decimal_separator: '.', thousands_separator: ',' };
}

// Suggests a mapping from the preview rows (header names and the first data line). It is only a
// starting point: the user confirms every field before it is applied.
export function guessMapping(previewRows) {
    const rows = (previewRows ?? []).map((row) => row.cells ?? []);
    const header = rows[0] ?? [];
    const first = rows[1] ?? [];
    // A header names its columns: it has letters and no cell that reads as a date or an amount.
    const dataLike = (cell) => /^\d{1,4}[-/.]\d{1,2}[-/.]\d{1,4}/.test(cell ?? '') || /^[-+(]?[€$£]?\s*\d[\d.,\s]*\)?-?$/.test(cell ?? '');
    const looksLikeHeader = header.some((cell) => /[A-Za-z]/.test(cell ?? '')) && !header.some(dataLike);

    const mapping = {
        header_row: looksLikeHeader ? 1 : 0,
        date_column: 0,
        date_format: 'Y-m-d',
        amount_mode: 'single',
        amount_column: 1,
        debit_column: null,
        credit_column: null,
        decimal_separator: '.',
        thousands_separator: ',',
        invert_sign: false,
        description_column: 2,
        category_column: null,
        currency_column: null,
    };

    if (looksLikeHeader) {
        for (const [field, pattern] of Object.entries(HEADER_PATTERNS)) {
            const index = header.findIndex((cell) => pattern.test(cell ?? ''));

            if (index !== -1) {
                mapping[field] = index;
            }
        }

        if (mapping.debit_column !== null && mapping.credit_column !== null && !HEADER_PATTERNS.amount_column.test(header.join(' '))) {
            mapping.amount_mode = 'split';
        }
    }

    const dataRow = looksLikeHeader ? first : rows[0] ?? [];
    mapping.date_format = guessDateFormat(dataRow[mapping.date_column]);

    const amountSample = mapping.amount_mode === 'split'
        ? (dataRow[mapping.debit_column] || dataRow[mapping.credit_column])
        : dataRow[mapping.amount_column];

    Object.assign(mapping, guessSeparators(amountSample));

    return mapping;
}

// Column choices for the mapping selects: "A: Date" style labels from the header or sample line.
export function columnOptions(previewRows, headerRow) {
    const rows = (previewRows ?? []).map((row) => row.cells ?? []);
    const width = Math.max(0, ...rows.map((cells) => cells.length));
    const labelRow = headerRow > 0 ? rows[headerRow - 1] ?? [] : rows[0] ?? [];

    return Array.from({ length: width }, (_, index) => {
        const letter = String.fromCharCode(65 + (index % 26));
        const label = (labelRow[index] ?? '').trim();

        return { value: index, label: label ? `${letter}: ${label}` : `Column ${letter}` };
    });
}
