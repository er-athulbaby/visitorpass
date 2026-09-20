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
                primary: '#0F172A',
                'on-primary': '#FFFFFF',
            },
        },
    },

    plugins: [forms],
};
