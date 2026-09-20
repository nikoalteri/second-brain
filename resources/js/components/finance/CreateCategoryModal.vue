<script setup>
import { computed, ref, watch } from 'vue';
import { useMutation } from '@vue/apollo-composable';
import { gql } from 'graphql-tag';
import FormInput from '@/components/ui/FormInput.vue';
import FormModal from '@/components/ui/FormModal.vue';
import FormSelect from '@/components/ui/FormSelect.vue';
import { useToast } from '@/composables/useToast.js';

const props = defineProps({
    open: Boolean,
    scope: { type: String, required: true },
    parentOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'created']);

const { addToast } = useToast();

const CREATE_CATEGORY = gql`
    mutation CreateTransactionCategory($input: CreateTransactionCategoryInput!) {
        createTransactionCategory(input: $input) {
            id
            parent_id
            name
            scope
        }
    }
`;

const { mutate: createCategory, loading: saving } = useMutation(CREATE_CATEGORY);

const name = ref('');
const parentId = ref('');
const error = ref('');

const parentSelectOptions = computed(() => [
    { value: '', label: 'No parent (top-level category)' },
    ...props.parentOptions.map((category) => ({ value: String(category.id), label: category.name })),
]);

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            name.value = '';
            parentId.value = '';
            error.value = '';
        }
    }
);

async function handleSubmit() {
    if (!name.value.trim()) {
        error.value = 'Name is required';
        return;
    }

    error.value = '';

    try {
        const { data } = await createCategory({
            input: {
                name: name.value.trim(),
                parent_id: parentId.value || null,
                scope: props.scope,
            },
        });

        addToast('Category created.', 'success');
        emit('created', data.createTransactionCategory);
    } catch {
        addToast('Could not create the category. Please try again.', 'error');
    }
}
</script>

<template>
    <FormModal :open="open" title="New category" @close="$emit('close')">
        <form class="flex flex-col gap-4" @submit.prevent="handleSubmit">
            <FormInput label="Name *" v-model="name" placeholder="e.g. Groceries" :error="error" />
            <FormSelect label="Parent category" v-model="parentId" :options="parentSelectOptions" />

            <div class="mt-2 flex justify-end gap-3">
                <button
                    type="button"
                    class="h-10 rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 transition-colors duration-150 hover:bg-gray-50"
                    @click="$emit('close')"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    :disabled="saving"
                    class="h-10 rounded-lg bg-amber-500 px-4 text-sm font-medium text-white transition-colors hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-400 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{ saving ? 'Saving…' : 'Create category' }}
                </button>
            </div>
        </form>
    </FormModal>
</template>
