import './bootstrap';
import { createApp } from 'vue';
import App from './AppPollVote.vue';

const el = document.getElementById('app-vote');
const token = el.dataset.token;

// On attend que le cookie CSRF soit chargé avant de monter Vue
fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' }).then(() => {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    const xsrf = match ? decodeURIComponent(match[1]) : null;

    createApp(App, { token }).mount(el);
});