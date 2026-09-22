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
                sans: ['Noto Sans', 'Noto Sans Arabic', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: 'var(--color-primary)',
                'on-primary': 'var(--color-on-primary)',
                secondary: '#565e74',
                'on-secondary': '#ffffff',
                tertiary: '#3f4f65',
                'on-tertiary': '#ffffff',
                error: '#ba1a1a',
                'on-error': '#ffffff',
                'error-container': '#ffdad6',
                'on-error-container': '#93000a',
                background: '#f7f9fb',
                'on-background': '#191c1e',
                surface: '#f7f9fb',
                'surface-container-lowest': '#ffffff',
                'surface-container-low': '#f2f4f6',
                'surface-container': '#eceef0',
                'surface-container-high': '#e6e8ea',
                'surface-variant': '#e0e3e5',
                'on-surface': '#191c1e',
                'on-surface-variant': '#434656',
                outline: '#737688',
                'outline-variant': '#c3c5d9',
                success: '#0f9d58',
                'success-container': '#d3f5df',
                warning: '#b06000',
                'warning-container': '#ffe6c7',
            },
            borderRadius: {
                DEFAULT: '0.125rem',
                lg: '0.25rem',
                xl: '0.5rem',
                full: '0.75rem',
            },
            spacing: {
                base: '8px',
                'stack-sm': '12px',
                'stack-md': '24px',
                'stack-lg': '48px',
                gutter: '24px',
            },
            fontSize: {
                'display-lg': ['48px', { lineHeight: '56px', letterSpacing: '-0.02em', fontWeight: '700' }],
                'headline-md': ['24px', { lineHeight: '32px', letterSpacing: '-0.01em', fontWeight: '600' }],
                'headline-md-mobile': ['20px', { lineHeight: '28px', fontWeight: '600' }],
                'headline-sm': ['20px', { lineHeight: '28px', fontWeight: '600' }],
                'body-lg': ['18px', { lineHeight: '28px', fontWeight: '400' }],
                'body-md': ['16px', { lineHeight: '24px', fontWeight: '400' }],
                'label-md': ['14px', { lineHeight: '20px', letterSpacing: '0.01em', fontWeight: '600' }],
                'label-sm': ['12px', { lineHeight: '16px', fontWeight: '500' }],
            },
        },
    },

    plugins: [forms],
};
