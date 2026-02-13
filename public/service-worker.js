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

// Handle push notifications
self.addEventListener('push', (event) => {
    let notificationData = {
        body: 'New ticket notification',
        icon: '/favicon.ico',
        badge: '/favicon.ico',
        vibrate: [200, 100, 200],
        requireInteraction: true,
        data: {
            url: '/'
        }
    };

    // Parse push event data if present
    if (event.data) {
        try {
            const data = event.data.json();
            notificationData = {
                body: data.body || notificationData.body,
                icon: data.icon || notificationData.icon,
                badge: data.badge || notificationData.badge,
                vibrate: data.vibrate || notificationData.vibrate,
                requireInteraction: data.requireInteraction !== undefined ? data.requireInteraction : true,
                tag: data.tag || 'ticket-notification',
                data: data.data || notificationData.data
            };
        } catch (e) {
            notificationData.body = event.data.text();
        }
    }

    const title = notificationData.title || 'Support Desk';
    event.waitUntil(
        self.registration.showNotification(title, notificationData)
    );
});
