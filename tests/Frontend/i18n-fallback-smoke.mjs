import assert from 'node:assert/strict';
import { createAppI18n } from '../../resources/js/i18n/index.js';

const i18n = createAppI18n('it', {
    missingWarn: false,
    fallbackWarn: false,
});

i18n.global.locale.value = 'it';

assert.equal(i18n.global.t('shell.nav.accounts'), 'Conti');
assert.equal(i18n.global.t('localization.fallbackProbe'), 'English fallback probe');

console.log('Frontend i18n fallback smoke test passed.');
