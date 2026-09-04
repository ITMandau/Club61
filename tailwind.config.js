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
                sans: ['Plus Jakarta Sans', ...defaultTheme.fontFamily.sans],
                serif: ['Cinzel', ...defaultTheme.fontFamily.serif],
                script: ['Alex Brush', 'cursive'],
            },
            colors: {
                forest: {
                    950: '#04160F',
                    900: '#07241A',
                    850: '#0A2E22',
                    800: '#0F3C2C',
                    700: '#15523D',
                    600: '#1C694E',
                    500: '#268564',
                },
                tennis: {
                    DEFAULT: '#CCFF00',
                    lime: '#CCFF00',
                    volt: '#D6FF2E',
                    soft: '#E4FFA1',
                },
            },
        },
    },

    plugins: [forms],
};