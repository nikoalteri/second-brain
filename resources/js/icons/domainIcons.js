import {
    ArchiveBoxIcon,
    ArrowPathIcon,
    ArrowsRightLeftIcon,
    ArrowTrendingDownIcon,
    ArrowTrendingUpIcon,
    BanknotesIcon,
    BoltIcon,
    BriefcaseIcon,
    BuildingLibraryIcon,
    BuildingOfficeIcon,
    CheckBadgeIcon,
    CheckCircleIcon,
    CircleStackIcon,
    ClockIcon,
    CreditCardIcon,
    ExclamationCircleIcon,
    ExclamationTriangleIcon,
    FilmIcon,
    FireIcon,
    FlagIcon,
    GiftIcon,
    HeartIcon,
    HomeModernIcon,
    LifebuoyIcon,
    MinusCircleIcon,
    PaperAirplaneIcon,
    PauseCircleIcon,
    ReceiptPercentIcon,
    ScaleIcon,
    ShieldCheckIcon,
    ShoppingBagIcon,
    ShoppingCartIcon,
    Square3Stack3DIcon,
    TagIcon,
    TrophyIcon,
    TruckIcon,
    WalletIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline';

export const accountTypeIcons = {
    bank: BuildingLibraryIcon,
    cash: BanknotesIcon,
    investment: ArrowTrendingUpIcon,
    emergency_fund: LifebuoyIcon,
    debt: ScaleIcon,
};

export const creditCardBillingTypeIcons = {
    charge: CheckBadgeIcon,
    revolving: ArrowPathIcon,
};

export const vaultCardTypeIcons = {
    debit: CreditCardIcon,
    prepaid: WalletIcon,
};

export const transactionTypeIcons = {
    Earnings: ArrowTrendingUpIcon,
    Income: ArrowTrendingUpIcon,
    Expenses: ArrowTrendingDownIcon,
    Expense: ArrowTrendingDownIcon,
    Transfer: ArrowsRightLeftIcon,
    Cashback: GiftIcon,
    Payment: BanknotesIcon,
    'Credit Card payment': CreditCardIcon,
};

export const CATEGORY_FALLBACK_ICON = TagIcon;

export const categoryIcons = {
    Living: Square3Stack3DIcon,
    Rent: HomeModernIcon,
    Travel: PaperAirplaneIcon,
    Housing: BuildingOfficeIcon,
    Utilities: BoltIcon,
    Groceries: ShoppingCartIcon,
    Transport: TruckIcon,
    Health: HeartIcon,
    Entertainment: FilmIcon,
    Shopping: ShoppingBagIcon,
    Savings: CircleStackIcon,
    Insurance: ShieldCheckIcon,
    Taxes: ReceiptPercentIcon,
    Salary: BriefcaseIcon,
    'Credit card payments': CreditCardIcon,
    'Credit Card payment': CreditCardIcon,
    Uncategorized: CATEGORY_FALLBACK_ICON,
    Uncategorised: CATEGORY_FALLBACK_ICON,
};

export function categoryIcon(name) {
    return categoryIcons[name] ?? CATEGORY_FALLBACK_ICON;
}

export const creditCardStatusIcons = {
    active: CheckCircleIcon,
    suspended: PauseCircleIcon,
    closed: XCircleIcon,
};

export const loanStatusIcons = {
    active: CheckCircleIcon,
    completed: TrophyIcon,
    defaulted: ExclamationCircleIcon,
};

export const savingGoalStatusIcons = {
    active: FlagIcon,
    archived: ArchiveBoxIcon,
};

export const subscriptionStatusIcons = {
    active: CheckCircleIcon,
    inactive: PauseCircleIcon,
    cancelled: XCircleIcon,
};

export const paymentStatusIcons = {
    pending: ClockIcon,
    paid: CheckCircleIcon,
};

export const budgetAlertStatusIcons = {
    none: MinusCircleIcon,
    ok: CheckCircleIcon,
    warning: ExclamationTriangleIcon,
    exceeded: ExclamationCircleIcon,
    critical: FireIcon,
};
