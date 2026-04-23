<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import {
    ArrowRightOnRectangleIcon,
    ArrowsRightLeftIcon,
    ArrowTopRightOnSquareIcon,
    BanknotesIcon,
    Bars3Icon,
    CalendarDaysIcon,
    ChartPieIcon,
    CreditCardIcon,
    ChevronDownIcon,
    Cog6ToothIcon,
    DocumentTextIcon,
    HomeIcon,
    UserCircleIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import { useUserPreferences } from '@/composables/useUserPreferences.js';
import { useAuthStore } from '@/stores/auth.js';

const router = useRouter();
const route = useRoute();
const auth = useAuthStore();
const { profileIsPrivate } = useUserPreferences();
const { t } = useI18n();
const mobileMenuOpen = ref(false);
const userMenuOpen = ref(false);
const userMenuRef = ref(null);
const adminUrl = '/admin';
const adminLinkLabel = computed(() => auth.isAdmin ? t('shell.userMenu.openAdmin') : null);
const isUserMenuRoute = computed(() => ['profile', 'settings'].includes(route.name));
const userDisplayName = computed(() => auth.user?.name || t('shell.userMenu.account'));
const userEmail = computed(() => profileIsPrivate.value ? t('shell.userMenu.privateAccount') : (auth.user?.email || t('shell.userMenu.signedIn')));
const userInitials = computed(() => {
    const name = auth.user?.name?.trim();

    if (!name) {
        return 'U';
    }

    return name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
});

function handleDocumentClick(event) {
    if (!userMenuRef.value?.contains(event.target)) {
        userMenuOpen.value = false;
    }
}

function closeMobileMenu() {
    mobileMenuOpen.value = false;
}

function closeUserMenu() {
    userMenuOpen.value = false;
}

function toggleUserMenu() {
    userMenuOpen.value = !userMenuOpen.value;
}

const navLinks = computed(() => [
    { name: t('shell.nav.dashboard'), to: '/dashboard', icon: HomeIcon },
    { name: t('shell.nav.accounts'), to: '/accounts', icon: BanknotesIcon },
    { name: t('shell.nav.transactions'), to: '/transactions', icon: ArrowsRightLeftIcon },
    { name: t('shell.nav.reports'), to: '/reports/finance', icon: ChartPieIcon },
    { name: t('shell.nav.loans'), to: '/loans', icon: DocumentTextIcon },
    { name: t('shell.nav.creditCards'), to: '/credit-cards', icon: CreditCardIcon },
    { name: t('shell.nav.subscriptions'), to: '/subscriptions', icon: CalendarDaysIcon },
]);

async function handleLogout() {
    closeMobileMenu();
    closeUserMenu();
    await auth.logout();
    router.push('/login');
}

onMounted(() => {
    document.addEventListener('click', handleDocumentClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick);
});
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
            <div ref="userMenuRef" class="relative hidden md:block">
                <button
                    type="button"
                    :aria-expanded="userMenuOpen"
                    class="flex h-10 items-center gap-3 rounded-xl border px-2.5 py-2 text-left shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-amber-300"
                    :class="userMenuOpen || isUserMenuRoute
                        ? 'border-amber-200 bg-amber-50'
                        : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50'"
                    @click.stop="toggleUserMenu"
                >
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 text-xs font-semibold text-amber-900">
                        {{ userInitials }}
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-gray-900">{{ userDisplayName }}</p>
                        <p class="truncate text-xs text-gray-500">{{ userEmail }}</p>
                    </div>
                    <ChevronDownIcon class="h-4 w-4 text-gray-400" />
                </button>

                <div
                    v-if="userMenuOpen"
                    class="absolute right-0 top-12 z-50 w-72 rounded-2xl border border-gray-200 bg-white p-2 shadow-xl"
                >
                    <div class="rounded-xl bg-gray-50 px-3 py-3">
                        <p class="text-sm font-medium text-gray-900">{{ userDisplayName }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ userEmail }}</p>
                    </div>

                    <div class="mt-2 flex flex-col gap-1">
                        <router-link
                            to="/profile"
                            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 hover:text-gray-900"
                            @click="closeUserMenu"
                        >
                            <UserCircleIcon class="h-5 w-5" />
                            {{ t('shell.userMenu.userDetails') }}
                        </router-link>

                        <router-link
                            to="/settings"
                            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 hover:text-gray-900"
                            @click="closeUserMenu"
                        >
                            <Cog6ToothIcon class="h-5 w-5" />
                            {{ t('shell.userMenu.settings') }}
                        </router-link>

                        <a
                            v-if="adminLinkLabel"
                            :href="adminUrl"
                            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 hover:text-gray-900"
                            @click="closeUserMenu"
                        >
                            <ArrowTopRightOnSquareIcon class="h-5 w-5" />
                            {{ adminLinkLabel }}
                        </a>

                        <button
                            type="button"
                            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 hover:text-gray-900"
                            @click="handleLogout"
                        >
                            <ArrowRightOnRectangleIcon class="h-5 w-5" />
                            {{ t('shell.userMenu.signOut') }}
                        </button>
                    </div>
                </div>
            </div>

            <button
                :aria-label="t('shell.userMenu.toggleMenu')"
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
                :aria-label="t('shell.userMenu.closeMenu')"
                class="absolute right-4 top-4 flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900"
                @click="mobileMenuOpen = false"
            >
                <XMarkIcon class="h-5 w-5" />
            </button>

            <router-link
                to="/profile"
                class="flex items-center gap-3 rounded-xl px-4 py-3 text-base font-medium text-gray-700 transition-colors hover:bg-gray-100 hover:text-gray-900"
                active-class="bg-amber-100 text-amber-900"
                @click="closeMobileMenu"
            >
                <UserCircleIcon class="h-5 w-5 shrink-0" />
                {{ t('shell.userMenu.userDetails') }}
            </router-link>

            <router-link
                to="/settings"
                class="flex items-center gap-3 rounded-xl px-4 py-3 text-base font-medium text-gray-700 transition-colors hover:bg-gray-100 hover:text-gray-900"
                active-class="bg-amber-100 text-amber-900"
                @click="closeMobileMenu"
            >
                <Cog6ToothIcon class="h-5 w-5 shrink-0" />
                {{ t('shell.userMenu.settings') }}
            </router-link>

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
                {{ t('shell.userMenu.signOut') }}
            </button>
        </div>
    </Teleport>
</template>
