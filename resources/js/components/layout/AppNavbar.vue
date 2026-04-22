<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import {
    ArrowRightOnRectangleIcon,
    ArrowsRightLeftIcon,
    BanknotesIcon,
    Bars3Icon,
    CalendarDaysIcon,
    CreditCardIcon,
    DocumentTextIcon,
    HomeIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import { useAuthStore } from '@/stores/auth.js';

const router = useRouter();
const auth = useAuthStore();
const mobileMenuOpen = ref(false);

const navLinks = [
    { name: 'Dashboard', to: '/dashboard', icon: HomeIcon },
    { name: 'Accounts', to: '/accounts', icon: BanknotesIcon },
    { name: 'Transactions', to: '/transactions', icon: ArrowsRightLeftIcon },
    { name: 'Loans', to: '/loans', icon: DocumentTextIcon },
    { name: 'Credit Cards', to: '/credit-cards', icon: CreditCardIcon },
    { name: 'Subscriptions', to: '/subscriptions', icon: CalendarDaysIcon },
];

async function handleLogout() {
    mobileMenuOpen.value = false;
    await auth.logout();
    router.push('/login');
}
</script>

<template>
    <nav class="fixed left-0 right-0 top-0 z-40 flex h-14 items-center justify-between border-b border-gray-700 bg-gray-800 px-4 lg:px-8">
        <span class="text-xl font-semibold text-white">Fluxa</span>

        <div class="hidden items-center gap-1 md:flex">
            <router-link
                v-for="link in navLinks"
                :key="link.to"
                :to="link.to"
                class="rounded-lg px-3 py-2 text-sm font-normal text-gray-400 transition-colors hover:bg-gray-700 hover:text-white"
                active-class="bg-gray-700 text-white"
            >
                {{ link.name }}
            </router-link>
        </div>

        <div class="flex items-center gap-2">
            <button
                aria-label="Logout"
                class="hidden h-9 w-9 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-gray-800 hover:text-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-500 md:flex"
                @click="handleLogout"
            >
                <ArrowRightOnRectangleIcon class="h-5 w-5" />
            </button>

            <button
                aria-label="Toggle menu"
                class="flex h-9 w-9 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-gray-700 hover:text-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-500 md:hidden"
                @click="mobileMenuOpen = !mobileMenuOpen"
            >
                <XMarkIcon v-if="mobileMenuOpen" class="h-5 w-5" />
                <Bars3Icon v-else class="h-5 w-5" />
            </button>
        </div>
    </nav>

    <Teleport to="body">
        <div
            v-if="mobileMenuOpen"
            class="fixed inset-0 z-50 flex flex-col gap-1 bg-gray-950/95 px-4 pt-16 backdrop-blur-sm md:hidden"
        >
            <button
                aria-label="Close menu"
                class="absolute right-4 top-4 flex h-9 w-9 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-gray-700 hover:text-gray-100"
                @click="mobileMenuOpen = false"
            >
                <XMarkIcon class="h-5 w-5" />
            </button>

            <router-link
                v-for="link in navLinks"
                :key="link.to"
                :to="link.to"
                class="flex items-center gap-3 rounded-xl px-4 py-3 text-base font-normal text-gray-300 transition-colors hover:bg-gray-800 hover:text-white"
                active-class="bg-gray-800 text-white"
                @click="mobileMenuOpen = false"
            >
                <component :is="link.icon" class="h-5 w-5 shrink-0" />
                {{ link.name }}
            </router-link>

            <button
                class="mt-auto mb-8 flex items-center gap-3 rounded-xl px-4 py-3 text-base font-normal text-gray-300 transition-colors hover:bg-gray-800 hover:text-white"
                @click="handleLogout"
            >
                <ArrowRightOnRectangleIcon class="h-5 w-5 shrink-0" />
                Sign out
            </button>
        </div>
    </Teleport>
</template>
