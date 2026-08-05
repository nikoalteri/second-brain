<script setup>
import { ref, nextTick, watch } from 'vue';
import { ChatBubbleLeftRightIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { useChatbotStore } from '@/stores/chatbot.js';
import { useAuthStore } from '@/stores/auth.js';
import ChatMessageBubble from '@/components/chatbot/ChatMessageBubble.vue';
import ChatQuickReplies from '@/components/chatbot/ChatQuickReplies.vue';
import ChatFreeTextInput from '@/components/chatbot/ChatFreeTextInput.vue';

const chat = useChatbotStore();
const auth = useAuthStore();
const scrollArea = ref(null);

watch(() => chat.messages.length, async () => {
    await nextTick();
    if (scrollArea.value) {
        scrollArea.value.scrollTop = scrollArea.value.scrollHeight;
    }
});
</script>

<template>
    <button
        v-if="auth.isAuthenticated"
        type="button"
        class="fixed bottom-6 right-6 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-amber-500 text-white shadow-lg hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-300"
        aria-label="Ask Fluxa"
        title="Ask Fluxa"
        @click="chat.toggle()"
    >
        <ChatBubbleLeftRightIcon class="h-6 w-6" />
    </button>

    <Teleport to="body">
        <div
            v-if="chat.isOpen"
            class="fixed inset-0 z-50 flex flex-col bg-white sm:inset-auto sm:bottom-24 sm:right-6 sm:h-[600px] sm:max-h-[calc(100vh-8rem)] sm:w-[380px] sm:rounded-2xl sm:border sm:border-gray-200 sm:shadow-xl"
            role="dialog"
            aria-modal="false"
            aria-label="Ask Fluxa about your finances"
        >
            <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 p-6">
                <h2 class="text-base font-semibold text-gray-900">Ask Fluxa about your finances</h2>
                <button
                    type="button"
                    aria-label="Close"
                    class="rounded-full p-1 text-gray-500 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-amber-300"
                    @click="chat.close()"
                >
                    <XMarkIcon class="h-5 w-5" />
                </button>
            </div>

            <div ref="scrollArea" class="flex-1 overflow-y-auto p-4">
                <div v-if="chat.messages.length === 0" class="flex h-full flex-col items-center justify-center text-center">
                    <p class="text-base font-semibold text-gray-900">What would you like to check?</p>
                    <p class="mt-1 text-sm font-normal text-gray-500">Pick a question below to get started.</p>
                </div>
                <div v-else class="space-y-2">
                    <ChatMessageBubble v-for="message in chat.messages" :key="message.id" :message="message" />
                </div>
            </div>

            <div class="mt-8 border-t border-gray-200 p-4">
                <ChatQuickReplies :replies="chat.quickReplies" :disabled="chat.loading" @select="chat.selectQuickReply($event)" />
                <ChatFreeTextInput
                    v-if="chat.freeTextEnabled"
                    class="mt-2"
                    :placeholder="chat.freeTextPlaceholder"
                    :disabled="chat.loading"
                    @submit="chat.submitFreeText($event)"
                />
            </div>
        </div>
    </Teleport>
</template>
