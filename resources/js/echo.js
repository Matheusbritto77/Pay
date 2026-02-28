import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: window.reverbConfig?.key || import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: window.reverbConfig?.host || import.meta.env.VITE_REVERB_HOST,
    wsPort: (window.reverbConfig?.port || import.meta.env.VITE_REVERB_PORT) ?? 80,
    wssPort: (window.reverbConfig?.port || import.meta.env.VITE_REVERB_PORT) ?? 443,
    forceTLS: (window.reverbConfig?.scheme || (import.meta.env.VITE_REVERB_SCHEME ?? 'https')) === 'https',
    enabledTransports: ['ws', 'wss'],
});
