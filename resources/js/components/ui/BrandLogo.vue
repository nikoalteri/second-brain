<script setup>
import { computed } from 'vue';
import { CreditCardIcon } from '@heroicons/vue/24/outline';
import { siAmericanexpress, siMastercard, siVisa } from 'simple-icons';

const props = defineProps({
    // 'visa' | 'mastercard' | 'amex' | null — unrecognized/missing falls back to a generic card icon.
    brand: { type: String, default: null },
});

const BRANDS = {
    visa: siVisa,
    mastercard: siMastercard,
    amex: siAmericanexpress,
};

const logo = computed(() => (props.brand ? BRANDS[props.brand] : null) ?? null);
</script>

<template>
    <CreditCardIcon v-if="!logo" class="h-5 w-5 text-gray-400" />
    <svg
        v-else
        viewBox="0 0 24 24"
        class="h-5 w-5"
        :fill="`#${logo.hex}`"
        role="img"
        :aria-label="logo.title"
    >
        <path :d="logo.path" />
    </svg>
</template>
