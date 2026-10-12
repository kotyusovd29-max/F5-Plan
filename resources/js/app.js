import '../css/inertia.css';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import FlashToasts from './Components/FlashToasts.vue';
import PersistentNotifications from './Components/PersistentNotifications.vue';
import { initPresenceTracking } from './presence';

createInertiaApp({
    title: (title) => (title ? `${title}` : 'F5 Plan'),
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({
            render: () => h('div', { class: 'contents' }, [
                h(PersistentNotifications),
                h(FlashToasts),
                h(App, props),
            ]),
        })
            .use(plugin)
            .mount(el);

        initPresenceTracking(props.initialPage);
    },
    progress: {
        color: '#b8e62e',
    },
});
