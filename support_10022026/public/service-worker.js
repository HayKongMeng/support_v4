// Service Worker for Background Notifications
// This enables notifications even when the browser is in the background

self.addEventListener('install', (event) => {
    console.log('Service Worker installing...');
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    console.log('Service Worker activating...');
    event.waitUntil(clients.claim());
});

// Handle notification clicks
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    // Get the URL from notification data
    const urlToOpen = event.notification.data?.url || '/';

    // Open or focus the app window
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                // Check if there's already a window open
                for (const client of clientList) {
                    if (client.url === urlToOpen && 'focus' in client) {
                        return client.focus();
                    }
                }
                // If not, open a new window
                if (clients.openWindow) {
                    return clients.openWindow(urlToOpen);
                }
            })
    );
});

// Handle push notifications (for future enhancement)
self.addEventListener('push', (event) => {
    const options = {
        body: event.data ? event.data.text() : 'New ticket notification',
        icon: '/favicon.ico',
        badge: '/favicon.ico',
        vibrate: [200, 100, 200],
        requireInteraction: true
    };

    event.waitUntil(
        self.registration.showNotification('Support Desk', options)
    );
});
