// Web Push Notification Manager
// Handles service worker registration and push subscriptions

class PushNotificationManager {
    constructor() {
        this.registration = null;
        this.subscription = null;
        this.publicKey = null;
    }

    async init() {
        // Check if browser supports service workers and push
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            console.log('Push notifications not supported');
            return false;
        }

        try {
            // Register service worker
            this.registration = await navigator.serviceWorker.register('/service-worker.js', {
                scope: '/'
            });
            console.log('Service Worker registered for push notifications');

            // Get public key from server
            const keyResponse = await fetch('/api/push/public-key');
            const keyData = await keyResponse.json();

            if (keyData.success && keyData.publicKey) {
                this.publicKey = keyData.publicKey;
                // Auto-subscribe if notification permission is granted
                if (Notification.permission === 'granted') {
                    await this.subscribe();
                }
            }

            return true;
        } catch (error) {
            console.error('Push notification setup failed:', error);
            return false;
        }
    }

    async subscribe() {
        if (!this.registration || !this.publicKey) {
            console.log('Service worker or public key not ready');
            return false;
        }

        try {
            const subscription = await this.registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(this.publicKey)
            });

            // Send subscription to server
            const response = await fetch('/api/push/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(subscription)
            });

            const result = await response.json();
            if (result.success) {
                this.subscription = subscription;
                console.log('Successfully subscribed to push notifications');
                return true;
            }
            return false;
        } catch (error) {
            console.error('Push subscription failed:', error);
            return false;
        }
    }

    async unsubscribe() {
        if (!this.registration) return false;

        try {
            const subscription = await this.registration.pushManager.getSubscription();
            
            if (subscription) {
                // Notify server
                await fetch('/api/push/unsubscribe', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        endpoint: subscription.endpoint
                    })
                });

                // Unsubscribe from push manager
                return await subscription.unsubscribe();
            }
            return false;
        } catch (error) {
            console.error('Push unsubscription failed:', error);
            return false;
        }
    }

    async getSubscription() {
        if (!this.registration) return null;
        return await this.registration.pushManager.getSubscription();
    }

    // Convert VAPID public key to Uint8Array
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }

        return outputArray;
    }
}

// Initialize on document load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        window.pushNotificationManager = new PushNotificationManager();
        window.pushNotificationManager.init();
    });
} else {
    window.pushNotificationManager = new PushNotificationManager();
    window.pushNotificationManager.init();
}
