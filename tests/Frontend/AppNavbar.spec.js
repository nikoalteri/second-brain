import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';

vi.mock('@/stores/auth.js', () => ({
    useAuthStore: () => ({ accessToken: 'token-a', user: { name: 'Test User', email: 't@example.test', settings: {} }, logout: vi.fn() }),
}));
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key) => key }) }));
vi.mock('vue-router', () => ({ useRoute: () => ({ path: '/loans' }), useRouter: () => ({ push: vi.fn() }) }));
vi.mock('@/i18n/index.js', () => ({ resolveBrowserLocale: () => 'en-US' }));

const { default: AppNavbar } = await import('@/components/layout/AppNavbar.vue');

describe('AppNavbar', () => {
    it('makes the Fluxa brand a link back to the home page', () => {
        setActivePinia(createPinia());
        const wrapper = mount(AppNavbar, {
            global: {
                stubs: {
                    NotificationBell: true,
                    'router-link': { props: ['to'], template: '<a :data-to="to"><slot /></a>' },
                },
            },
        });

        const brand = wrapper.findAll('a').find((link) => link.text() === 'Fluxa');

        expect(brand).toBeDefined();
        expect(brand.attributes('data-to')).toBe('/home');
        expect(brand.attributes('aria-label')).toBe('Fluxa home');
    });
});
