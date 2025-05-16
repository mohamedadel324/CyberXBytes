import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Create an instance with a custom timeout for profile requests
const profileAxios = axios.create({
    timeout: 60000 // 60 seconds timeout for profile requests
});

// Create an interceptor to dynamically set timeouts based on the request URL
axios.interceptors.request.use(config => {
    // Increase timeout for profile-related endpoints
    if (config.url && (
        config.url.includes('/user/profile') || 
        config.url.includes('/profile') || 
        config.url.includes('/user/stats') || 
        config.url.includes('/user/activities')
    )) {
        config.timeout = 60000; // 60 seconds for profile requests
    } else {
        // Default timeout for other requests
        config.timeout = config.timeout || 30000; // 30 seconds default
    }
    return config;
});

// Export for use in components
window.profileAxios = profileAxios;

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
