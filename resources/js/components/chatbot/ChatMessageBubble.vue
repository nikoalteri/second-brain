<script setup>
defineProps({
    message: { type: Object, required: true },
});

const formatMoney = (value, currency) =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency: currency || 'EUR' }).format(Number(value) || 0);
</script>

<template>
    <div class="flex" :class="message.role === 'user' ? 'justify-end' : 'justify-start'">
        <div
            v-if="message.role === 'user'"
            class="max-w-[85%] rounded-2xl bg-amber-500 p-4 text-sm font-normal text-white"
        >
            {{ message.text }}
        </div>

        <div
            v-else-if="message.kind === 'error'"
            class="max-w-[85%] rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-normal text-red-600"
        >
            {{ message.text }}
            <p v-if="message.subtext" class="mt-1 text-xs font-normal text-red-600">{{ message.subtext }}</p>
        </div>

        <div
            v-else-if="message.kind === 'out_of_scope'"
            class="max-w-[85%] rounded-2xl bg-gray-100 p-4 text-sm font-normal text-gray-900"
        >
            {{ message.text }}
            <p v-if="message.subtext" class="mt-1 text-xs font-normal text-gray-500">{{ message.subtext }}</p>
        </div>

        <div
            v-else-if="message.kind === 'answer'"
            class="max-w-[85%] rounded-2xl bg-gray-100 p-4 text-sm font-normal text-gray-900"
        >
            <p class="text-base font-semibold text-gray-900">{{ message.text }}</p>

            <div v-if="message.answer?.highlight" class="mt-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                    {{ message.answer.highlight.label }}
                </p>
                <p class="text-xl font-semibold text-gray-900">
                    {{ formatMoney(message.answer.highlight.value, message.answer.highlight.currency) }}
                </p>
            </div>

            <div v-if="message.answer?.items?.length > 0" class="mt-4 space-y-2">
                <div
                    v-for="(item, index) in message.answer.items"
                    :key="index"
                    class="flex items-start justify-between gap-2"
                >
                    <div>
                        <p class="text-sm font-normal text-gray-900">{{ item.label }}</p>
                        <p v-if="item.detail" class="text-xs font-normal text-gray-500">{{ item.detail }}</p>
                    </div>
                    <p class="text-sm font-semibold text-gray-900">{{ formatMoney(item.value, item.currency) }}</p>
                </div>
            </div>

            <p
                v-else-if="message.answer?.items?.length === 0 && message.answer?.empty_message"
                class="mt-2 text-sm font-normal text-gray-500"
            >
                {{ message.answer.empty_message }}
            </p>
        </div>

        <div v-else class="max-w-[85%] rounded-2xl bg-gray-100 p-4 text-sm font-normal text-gray-900">
            {{ message.text }}
        </div>
    </div>
</template>
