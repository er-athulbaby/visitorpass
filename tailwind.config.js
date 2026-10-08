import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                // Inter for Latin UI text; Noto Sans Arabic picks up Arabic glyphs.
                sans: ['Inter', 'Noto Sans Arabic', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Customer brand colour from Admin → Settings: the single action colour.
                primary: 'var(--color-primary)',
                'on-primary': 'var(--color-on-primary)',
                navy: '#0F172A',
                'navy-2': '#1E293B',
                secondary: '#475569',
                'on-secondary': '#ffffff',
                tertiary: '#334155',
                'on-tertiary': '#ffffff',
                error: '#DC2626',
                'on-error': '#ffffff',
                'error-container': '#FEF2F2',
                'on-error-container': '#991B1B',
                background: '#F8FAFC',
                'on-background': '#0F172A',
                surface: '#F8FAFC',
                'surface-container-lowest': '#FFFFFF',
                'surface-container-low': '#F8FAFC',
                'surface-container': '#F1F5F9',
                'surface-container-high': '#E2E8F0',
                'surface-variant': '#E2E8F0',
                'on-surface': '#0F172A',
                'on-surface-variant': '#64748B',
                outline: '#94A3B8',
                'outline-variant': '#E2E8F0',
                success: '#16A34A',
                'success-container': '#F0FDF4',
                warning: '#B45309',
                'warning-accent': '#F59E0B',
                'warning-container': '#FFFBEB',
                info: '#0EA5E9',
                'info-container': '#F0F9FF',
            },
            spacing: {
                base: '8px',
                'stack-sm': '12px',
                'stack-md': '24px',
                'stack-lg': '48px',
                gutter: '24px',
            },
            fontSize: {
                'page-title': ['28px', { lineHeight: '36px', letterSpacing: '-0.02em', fontWeight: '700' }],
                'section-title': ['18px', { lineHeight: '28px', letterSpacing: '-0.01em', fontWeight: '600' }],
                metric: ['32px', { lineHeight: '40px', letterSpacing: '-0.02em', fontWeight: '700' }],
                'display-lg': ['48px', { lineHeight: '56px', letterSpacing: '-0.02em', fontWeight: '700' }],
                'headline-md': ['24px', { lineHeight: '32px', letterSpacing: '-0.01em', fontWeight: '600' }],
                'headline-md-mobile': ['20px', { lineHeight: '28px', fontWeight: '600' }],
                'headline-sm': ['20px', { lineHeight: '28px', fontWeight: '600' }],
                'body-lg': ['18px', { lineHeight: '28px', fontWeight: '400' }],
                'body-md': ['15px', { lineHeight: '24px', fontWeight: '400' }],
                'body-sm': ['14px', { lineHeight: '20px', fontWeight: '400' }],
                'label-md': ['14px', { lineHeight: '20px', fontWeight: '500' }],
                'label-sm': ['12px', { lineHeight: '16px', fontWeight: '500' }],
            },
        },
    },

    plugins: [forms],
};
