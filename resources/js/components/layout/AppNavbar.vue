<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import {
    ArrowRightOnRectangleIcon,
    ArrowsRightLeftIcon,
    ArrowTopRightOnSquareIcon,
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
const adminUrl = '/admin';
const frontendDashboardUrl = '/dashboard';
const adminLinkLabel = computed(() => auth.isAdmin ? 'Open Admin' : null);

function closeMobileMenu() {
    mobileMenuOpen.value = false;
}

const navLinks = [
    { name: 'Dashboard', to: '/dashboard', icon: HomeIcon },
    { name: 'Accounts', to: '/accounts', icon: BanknotesIcon },
    { name: 'Transactions', to: '/transactions', icon: ArrowsRightLeftIcon },
    { name: 'Loans', to: '/loans', icon: DocumentTextIcon },
    { name: 'Credit Cards', to: '/credit-cards', icon: CreditCardIcon },
    { name: 'Subscriptions', to: '/subscriptions', icon: CalendarDaysIcon },
];

async function handleLogout() {
    closeMobileMenu();
    await auth.logout();
    router.push('/login');
}
</script>

<template>
    <nav class="fixed left-0 right-0 top-0 z-40 flex h-14 items-center justify-between border-b border-gray-200 bg-white px-4 shadow-sm lg:px-8">
        <span class="text-xl font-semibold text-gray-900">Fluxa</span>

        <div class="hidden items-center gap-1 md:flex">
            <router-link
                v-for="link in navLinks"
                :key="link.to"
                :to="link.to"
                class="rounded-lg px-3 py-2 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900"
                active-class="bg-amber-100 text-amber-900"
            >
                {{ link.name }}
            </router-link>
        </div>

        <div class="flex items-center gap-2">
            <a
                v-if="adminLinkLabel"
                :href="adminUrl"
                class="hidden h-9 items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 text-sm font-medium text-amber-900 transition-colors hover:bg-amber-100 md:flex"
            >
                <ArrowTopRightOnSquareIcon class="h-4 w-4" />
                {{ adminLinkLabel }}
            </a>
            <button
                aria-label="Logout"
                class="hidden h-9 w-9 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-amber-300 md:flex"
                @click="handleLogout"
            >
                <ArrowRightOnRectangleIcon class="h-5 w-5" />
            </button>

            <button
                aria-label="Toggle menu"
                class="flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-amber-300 md:hidden"
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
            class="fixed inset-0 z-50 flex flex-col gap-1 bg-white/95 px-4 pt-16 backdrop-blur-sm md:hidden"
        >
            <button
                aria-label="Close menu"
                class="absolute right-4 top-4 flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900"
                @click="mobileMenuOpen = false"
            >
                <XMarkIcon class="h-5 w-5" />
            </button>

            <router-link
                v-for="link in navLinks"
                :key="link.to"
                :to="link.to"
                class="flex items-center gap-3 rounded-xl px-4 py-3 text-base font-medium text-gray-700 transition-colors hover:bg-gray-100 hover:text-gray-900"
                active-class="bg-amber-100 text-amber-900"
                @click="closeMobileMenu"
            >
                <component :is="link.icon" class="h-5 w-5 shrink-0" />
                {{ link.name }}
            </router-link>

            <a
                v-if="adminLinkLabel"
                :href="adminUrl"
                class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-base font-medium text-amber-900 transition-colors hover:bg-amber-100"
                @click="closeMobileMenu"
            >
                <ArrowTopRightOnSquareIcon class="h-5 w-5 shrink-0" />
                {{ adminLinkLabel }}
            </a>

            <button
                class="mb-8 mt-auto flex items-center gap-3 rounded-xl px-4 py-3 text-base font-medium text-gray-700 transition-colors hover:bg-gray-100 hover:text-gray-900"
                @click="handleLogout"
            >
                <ArrowRightOnRectangleIcon class="h-5 w-5 shrink-0" />
                Sign out
            </button>
        </div>
    </Teleport>
</template>
