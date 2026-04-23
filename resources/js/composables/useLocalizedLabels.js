import { useI18n } from 'vue-i18n';
import {
    accountTypeTranslationKeys,
    categoryTranslationKeys,
    transactionTypeTranslationKeys,
    translateMappedValue,
} from '@/i18n/domainLabels.js';

export function useLocalizedLabels() {
    const { t, te } = useI18n();

    function translateAccountType(value) {
        return translateMappedValue(value, accountTypeTranslationKeys, t, te);
    }

    function translateTransactionType(value) {
        return translateMappedValue(value, transactionTypeTranslationKeys, t, te);
    }

    function translateCategoryName(value) {
        return translateMappedValue(value, categoryTranslationKeys, t, te);
    }

    function translateOptionalCategory(value) {
        return value
            ? translateCategoryName(value)
            : t('labels.categories.uncategorized');
    }

    function translateCategoryPath(parent, child) {
        if (! parent) {
            return translateCategoryName(child);
        }

        if (! child) {
            return translateCategoryName(parent);
        }

        return `${translateCategoryName(parent)} › ${translateCategoryName(child)}`;
    }

    return {
        translateAccountType,
        translateTransactionType,
        translateCategoryName,
        translateOptionalCategory,
        translateCategoryPath,
    };
}
