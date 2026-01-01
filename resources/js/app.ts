import '../css/app.css';
import 'primeicons/primeicons.css'
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, DefineComponent, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { PerfectScrollbarPlugin } from 'vue3-perfect-scrollbar';
import 'vue3-perfect-scrollbar/style.css';
import PrimeVue from 'primevue/config';
import { DialogService, Tooltip, ToastService, ConfirmationService } from 'primevue';
import VueApexCharts from "vue3-apexcharts";
import Aura from '@primeuix/themes/aura';
import { ModalPlugin } from './Components/Modals/ModalService';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(PrimeVue, {
                theme: {
                    preset: Aura,
                    options: {
                        cssLayer: {
                            name: 'primevue',
                            order: 'theme, base, primevue'
                        },
                        darkModeSelector: '.dark',
                    }
                }
            })
            .use(PerfectScrollbarPlugin)
            .use(ToastService)
            .use(DialogService)
            .use(ConfirmationService)
            .use(VueApexCharts)
            .use(ModalPlugin)
            .directive('tooltip', Tooltip)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
