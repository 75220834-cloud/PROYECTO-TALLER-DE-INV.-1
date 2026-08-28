import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/theme-init.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        /*
         * IPv4 explicito. Por defecto Vite escucha en `localhost`, que en
         * Windows resuelve primero a IPv6 y hace que anuncie sus assets en
         * http://[::1]:5173. Ese origen NO se puede autorizar en una CSP:
         * los navegadores rechazan la directiva entera por invalida, asi que
         * la aplicacion se quedaba sin estilos en desarrollo.
         *
         * Se arregla aqui y no en la politica porque el problema es el
         * origen que Vite anuncia, no la politica que lo bloquea.
         */
        host: '127.0.0.1',
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
