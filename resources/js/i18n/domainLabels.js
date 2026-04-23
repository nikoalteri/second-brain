export const accountTypeTranslationKeys = {
    bank: 'labels.accountTypes.bank',
    cash: 'labels.accountTypes.cash',
    investment: 'labels.accountTypes.investment',
    emergency_fund: 'labels.accountTypes.emergency_fund',
    debt: 'labels.accountTypes.debt',
};

export const transactionTypeTranslationKeys = {
    Earnings: 'labels.transactionTypes.earnings',
    Expenses: 'labels.transactionTypes.expenses',
    Expense: 'labels.transactionTypes.expense',
    Transfer: 'labels.transactionTypes.transfer',
    Cashback: 'labels.transactionTypes.cashback',
    Income: 'labels.transactionTypes.income',
    Payment: 'labels.transactionTypes.payment',
    'Credit Card payment': 'labels.transactionTypes.credit_card_payment',
};

export const categoryTranslationKeys = {
    Living: 'labels.categories.living',
    Rent: 'labels.categories.rent',
    Travel: 'labels.categories.travel',
    Housing: 'labels.categories.housing',
    Utilities: 'labels.categories.utilities',
    Groceries: 'labels.categories.groceries',
    Transport: 'labels.categories.transport',
    Health: 'labels.categories.health',
    Entertainment: 'labels.categories.entertainment',
    Shopping: 'labels.categories.shopping',
    Savings: 'labels.categories.savings',
    Insurance: 'labels.categories.insurance',
    Taxes: 'labels.categories.taxes',
    Salary: 'labels.categories.salary',
    'Credit card payments': 'labels.categories.credit_card_payments',
    'Credit Card payment': 'labels.categories.credit_card_payments',
    Uncategorized: 'labels.categories.uncategorized',
    Uncategorised: 'labels.categories.uncategorized',
};

export function translateMappedValue(value, keyMap, t, te) {
    if (!value) {
        return value;
    }

    const translationKey = keyMap[value];

    return translationKey && te(translationKey)
        ? t(translationKey)
        : value;
}
