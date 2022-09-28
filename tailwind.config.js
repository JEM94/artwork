const defaultTheme = require('tailwindcss/defaultTheme');

module.exports = {
    mode: 'jit',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        "./node_modules/flowbite/**/*.js"
    ],

    theme: {
        extend: {
            colors: {
                primary:'#27233C',
                primaryHover:'#372F60',
                success:'#1FC687',
                error:'#FD6D73',
                secondary:'#A7A6B1',
                secondaryHover:'#FCFCFB',
                darkInputBg:'rgba(252,252,251,0.15)',
                darkInputText: '#D8D7DE',
                help: '#93929D',
                darkGray: '#616069',
                darkGrayBg: 'rgba(255,255,255,0.15)',
                backgroundGray: '#F5F5F3',
                buttonBlue:'#3017AD',
                buttonHover:'#2D1FDE',
                tagBg: 'rgba(48,23,173,0.1)',
                tag: 'rgba(48,23,173,0.3)',
                tagText: '#3017AD'
            },
            fontSize: {
                header: '30px'
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                nanum: "Nanum Pen Script",
                lexend: "Lexend"
            },
            fontWeight: {
              header: '900'
            },
            boxShadow: {
                'buttonBlue': '0 35px 60px -15px #2D1FDE'
            }
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
        require('@tailwindcss/typography'),
        require('flowbite/plugin')
    ],
};
