import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;

// axios.defaults.withCredentials = true;  // Ensures Laravel Sanctum works properly
