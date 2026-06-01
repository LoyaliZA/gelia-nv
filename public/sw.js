/* Service Worker GELIA — Web Push en segundo plano */

self.addEventListener('push', (event) => {
    let payload = {
        title: 'GELIA ERP',
        body: '',
        icon: '/favicon.svg',
        badge: '/favicon.svg',
        url: '/dashboard',
        tag: 'gelia-push',
    };

    try {
        if (event.data) {
            const parsed = event.data.json();
            payload = { ...payload, ...parsed };
        }
    } catch (e) {
        if (event.data) {
            payload.body = event.data.text();
        }
    }

    const options = {
        body: payload.body || '',
        icon: payload.icon || '/favicon.svg',
        badge: payload.badge || '/favicon.svg',
        tag: payload.tag || 'gelia-push',
        data: {
            url: payload.url || '/dashboard',
            ...(payload.data || {}),
        },
        requireInteraction: false,
        renotify: true,
    };

    event.waitUntil(self.registration.showNotification(payload.title || 'GELIA ERP', options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = event.notification.data?.url || '/dashboard';
    const destino = new URL(url, self.location.origin).href;

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if ('focus' in client) {
                    client.navigate(destino);
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(destino);
            }
            return undefined;
        })
    );
});

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});
