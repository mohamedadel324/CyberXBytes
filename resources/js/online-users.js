import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Initialize Echo if it's not already initialized
if (!window.Echo) {
    window.Pusher = Pusher;
    
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: process.env.MIX_PUSHER_APP_KEY,
        cluster: process.env.MIX_PUSHER_APP_CLUSTER,
        forceTLS: true
    });
}

// Function to update user count on UI
function updateOnlineUserCount(count) {
    const countElement = document.getElementById('online-users-count');
    if (countElement) {
        countElement.textContent = count;
    }
}

// Listen for the users.online event on the online-users channel
window.Echo.channel('online-users')
    .listen('.users.online', (event) => {
        console.log('Online users event received:', event);
        updateOnlineUserCount(event.onlineUsers);
    });

// Update user last seen regularly to stay "online"
function updateLastSeen() {
    fetch('/api/update-last-seen', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .catch(error => console.error('Error updating last seen:', error));
}

// Update last seen every 30 seconds
setInterval(updateLastSeen, 30000);

// Initial update
updateLastSeen();

// Get initial online user count
fetch('/api/online-users-count')
    .then(response => response.json())
    .then(data => updateOnlineUserCount(data))
    .catch(error => console.error('Error fetching online users count:', error)); 