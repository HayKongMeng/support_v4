<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Support Desk' ?> - <?= htmlspecialchars($company['name'] ?? 'Support Desk') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        [x-cloak] { display: none !important; }
        .toast-notification {
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        .toast-notification.removing {
            animation: slideOut 0.3s ease-in;
        }
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen" x-data="{ sidebarOpen: true, mobileMenu: false, toasts: window.toastsData || [] }" x-init="window.toastsData = toasts">
    <!-- Toast Notifications Container -->
    <div class="fixed top-20 right-4 z-[200] space-y-3 max-w-sm">
        <template x-for="toast in toasts" :key="toast.id">
            <div :class="toast.removing ? 'removing' : ''"
                 class="toast-notification bg-white rounded-lg shadow-2xl border-l-4 overflow-hidden cursor-pointer"
                 :class="{
                     'border-green-500': toast.type === 'new_ticket',
                     'border-blue-500': toast.type === 'ticket_reply',
                     'border-yellow-500': toast.type === 'ticket_updated'
                 }"
                 @click="window.location.href = toast.url">
                <div class="p-4">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                             :class="{
                                 'bg-green-100 text-green-600': toast.type === 'new_ticket',
                                 'bg-blue-100 text-blue-600': toast.type === 'ticket_reply',
                                 'bg-yellow-100 text-yellow-600': toast.type === 'ticket_updated'
                             }">
                            <i :class="{
                                'fas fa-ticket-alt': toast.type === 'new_ticket',
                                'fas fa-reply': toast.type === 'ticket_reply',
                                'fas fa-edit': toast.type === 'ticket_updated'
                            }" class="text-lg"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 mb-1" x-text="toast.title"></p>
                            <p class="text-xs text-gray-600" x-text="toast.message"></p>
                        </div>
                        <button @click.stop="removeToast(toast.id)" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside :class="sidebarOpen ? 'w-64' : 'w-20'" class="hidden lg:flex flex-col bg-slate-800 text-white transition-all duration-300">
            <!-- Logo -->
            <div class="flex items-center justify-between h-16 px-4 border-b border-slate-700">
                <span x-show="sidebarOpen" class="text-xl font-bold">Support Desk</span>
                <span x-show="!sidebarOpen" class="text-xl font-bold">SD</span>
                <button @click="sidebarOpen = !sidebarOpen" class="p-1 hover:bg-slate-700 rounded">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto py-4">
                <a href="<?= $app->url('dashboard') ?>" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white <?= strpos($_SERVER['REQUEST_URI'], '/dashboard') !== false ? 'bg-slate-700 text-white' : '' ?>">
                    <i class="fas fa-home w-6"></i>
                    <span x-show="sidebarOpen" class="ml-3">Dashboard</span>
                </a>
                <a href="<?= $app->url('tickets') ?>" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white <?= strpos($_SERVER['REQUEST_URI'], '/tickets') !== false && strpos($_SERVER['REQUEST_URI'], '/customer') === false ? 'bg-slate-700 text-white' : '' ?>">
                    <i class="fas fa-ticket-alt w-6"></i>
                    <span x-show="sidebarOpen" class="ml-3">Tickets</span>
                </a>
                <a href="<?= $app->url('kb') ?>" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white <?= strpos($_SERVER['REQUEST_URI'], '/kb') !== false ? 'bg-slate-700 text-white' : '' ?>">
                    <i class="fas fa-book w-6"></i>
                    <span x-show="sidebarOpen" class="ml-3">Knowledge Base</span>
                </a>
                <?php if ($auth->isAgent()): ?>
                <a href="<?= $app->url('analytics') ?>" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white <?= strpos($_SERVER['REQUEST_URI'], '/analytics') !== false ? 'bg-slate-700 text-white' : '' ?>">
                    <i class="fas fa-chart-line w-6"></i>
                    <span x-show="sidebarOpen" class="ml-3">Analytics</span>
                </a>
                <?php endif; ?>

                <?php if ($auth->isAdmin()): ?>
                <div x-show="sidebarOpen" class="px-4 py-2 mt-4 text-xs font-semibold text-slate-500 uppercase">Settings</div>
                <a href="<?= $app->url('settings/general') ?>" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white <?= strpos($_SERVER['REQUEST_URI'], '/settings/general') !== false ? 'bg-slate-700 text-white' : '' ?>">
                    <i class="fas fa-cog w-6"></i>
                    <span x-show="sidebarOpen" class="ml-3">General</span>
                </a>
                <a href="<?= $app->url('settings/categories') ?>" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white <?= strpos($_SERVER['REQUEST_URI'], '/settings/categories') !== false ? 'bg-slate-700 text-white' : '' ?>">
                    <i class="fas fa-tags w-6"></i>
                    <span x-show="sidebarOpen" class="ml-3">Categories</span>
                </a>
                <a href="<?= $app->url('settings/users') ?>" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white <?= strpos($_SERVER['REQUEST_URI'], '/settings/users') !== false ? 'bg-slate-700 text-white' : '' ?>">
                    <i class="fas fa-users w-6"></i>
                    <span x-show="sidebarOpen" class="ml-3">Users</span>
                </a>
                <a href="<?= $app->url('settings/email') ?>" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white <?= strpos($_SERVER['REQUEST_URI'], '/settings/email') !== false ? 'bg-slate-700 text-white' : '' ?>">
                    <i class="fas fa-envelope w-6"></i>
                    <span x-show="sidebarOpen" class="ml-3">Email</span>
                </a>
                <a href="<?= $app->url('settings/telegram') ?>" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white <?= strpos($_SERVER['REQUEST_URI'], '/settings/telegram') !== false ? 'bg-slate-700 text-white' : '' ?>">
                    <i class="fab fa-telegram w-6"></i>
                    <span x-show="sidebarOpen" class="ml-3">Telegram</span>
                </a>
                <a href="<?= $app->url('settings/canned-responses') ?>" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white <?= strpos($_SERVER['REQUEST_URI'], '/settings/canned') !== false ? 'bg-slate-700 text-white' : '' ?>">
                    <i class="fas fa-reply-all w-6"></i>
                    <span x-show="sidebarOpen" class="ml-3">Canned Responses</span>
                </a>
                <a href="<?= $app->url('settings/sla') ?>" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white <?= strpos($_SERVER['REQUEST_URI'], '/settings/sla') !== false ? 'bg-slate-700 text-white' : '' ?>">
                    <i class="fas fa-clock w-6"></i>
                    <span x-show="sidebarOpen" class="ml-3">SLA Configuration</span>
                </a>
                <?php endif; ?>
            </nav>

            <!-- User info -->
            <div class="p-4 border-t border-slate-700">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-indigo-500 rounded-full flex items-center justify-center">
                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                    </div>
                    <div x-show="sidebarOpen" class="ml-3">
                        <p class="text-sm font-medium"><?= htmlspecialchars($user['name']) ?></p>
                        <p class="text-xs text-slate-400"><?= ucfirst($user['role']) ?></p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Mobile sidebar -->
        <div x-show="mobileMenu" x-cloak class="fixed inset-0 z-50 lg:hidden">
            <div class="fixed inset-0 bg-black/50" @click="mobileMenu = false"></div>
            <aside class="fixed top-0 left-0 w-64 h-full bg-slate-800 text-white z-50">
                <!-- Same content as desktop sidebar -->
                <div class="flex items-center justify-between h-16 px-4 border-b border-slate-700">
                    <span class="text-xl font-bold">Support Desk</span>
                    <button @click="mobileMenu = false" class="p-1 hover:bg-slate-700 rounded">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <nav class="py-4">
                    <a href="<?= $app->url('dashboard') ?>" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700">
                        <i class="fas fa-home w-6"></i>
                        <span class="ml-3">Dashboard</span>
                    </a>
                    <a href="<?= $app->url('tickets') ?>" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700">
                        <i class="fas fa-ticket-alt w-6"></i>
                        <span class="ml-3">Tickets</span>
                    </a>
                    <a href="<?= $app->url('kb') ?>" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700">
                        <i class="fas fa-book w-6"></i>
                        <span class="ml-3">Knowledge Base</span>
                    </a>
                    <?php if ($auth->isAgent()): ?>
                    <a href="<?= $app->url('analytics') ?>" class="flex items-center px-4 py-3 text-slate-300 hover:bg-slate-700">
                        <i class="fas fa-chart-line w-6"></i>
                        <span class="ml-3">Analytics</span>
                    </a>
                    <?php endif; ?>
                </nav>
            </aside>
        </div>

        <!-- Main content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top header -->
            <header class="bg-white shadow-sm h-16 flex items-center justify-between px-4 lg:px-6">
                <div class="flex items-center">
                    <button @click="mobileMenu = true" class="lg:hidden p-2 hover:bg-gray-100 rounded-lg mr-2">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="text-xl font-semibold text-gray-800"><?= $pageTitle ?? 'Dashboard' ?></h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600 hidden sm:inline"><?= htmlspecialchars($company['name'] ?? '') ?></span>

                    <!-- Notifications -->
                    <div class="relative" x-data="notificationBell()" x-init="init()">
                        <button @click="toggle()" class="relative p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg">
                            <i class="fas fa-bell text-lg"></i>
                            <span x-show="unreadCount > 0"
                                  x-text="unreadCount > 9 ? '9+' : unreadCount"
                                  class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center">
                            </span>
                            <span x-show="notificationPermission === 'granted'"
                                  class="absolute -bottom-1 -right-1 w-3 h-3 bg-green-500 rounded-full border-2 border-white"
                                  title="Desktop notifications enabled"></span>
                            <span x-show="notificationPermission === 'denied'"
                                  class="absolute -bottom-1 -right-1 w-3 h-3 bg-red-500 rounded-full border-2 border-white"
                                  title="Desktop notifications blocked"></span>
                        </button>

                        <!-- Dropdown -->
                        <div x-show="open" x-cloak @click.away="open = false"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             class="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-lg border border-gray-200 z-50">
                            <div class="p-3 border-b border-gray-200 flex items-center justify-between">
                                <h3 class="font-semibold text-gray-800">Notifications</h3>
                                <button @click="markAllRead()" x-show="unreadCount > 0" class="text-xs text-indigo-600 hover:text-indigo-800">
                                    Mark all read
                                </button>
                            </div>
                            <div class="p-3 bg-gray-50 border-b border-gray-200">
                                <div class="flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-desktop text-gray-600"></i>
                                        <span class="text-gray-700">Desktop Notifications</span>
                                    </div>
                                    <div>
                                        <span x-show="notificationPermission === 'granted'" class="text-green-600 font-medium">
                                            <i class="fas fa-check-circle"></i> Enabled
                                        </span>
                                        <span x-show="notificationPermission === 'denied'" class="text-red-600 font-medium">
                                            <i class="fas fa-times-circle"></i> Blocked
                                        </span>
                                        <span x-show="notificationPermission === 'default'" class="text-yellow-600 font-medium">
                                            <i class="fas fa-exclamation-circle"></i> Not Set
                                        </span>
                                    </div>
                                </div>
                                <template x-if="notificationPermission !== 'granted'">
                                    <button @click="checkNotificationPermission()"
                                            class="mt-2 w-full px-3 py-1.5 bg-indigo-600 text-white text-xs rounded hover:bg-indigo-700">
                                        <i class="fas fa-bell"></i> Enable Desktop Notifications
                                    </button>
                                </template>
                                <template x-if="notificationPermission === 'denied'">
                                    <p class="mt-2 text-xs text-gray-600">
                                        Please enable notifications in your browser settings for this site.
                                    </p>
                                </template>
                            </div>
                            <div class="max-h-80 overflow-y-auto">
                                <template x-if="notifications.length === 0">
                                    <div class="p-4 text-center text-gray-500">
                                        <i class="fas fa-bell-slash text-2xl mb-2"></i>
                                        <p class="text-sm">No notifications</p>
                                    </div>
                                </template>
                                <template x-for="n in notifications" :key="n.id">
                                    <a :href="n.ticket_id ? '<?= $app->url('tickets') ?>/' + n.ticket_id : '#'"
                                       @click="markRead(n.id)"
                                       class="block p-3 hover:bg-gray-50 border-b border-gray-100"
                                       :class="{ 'bg-indigo-50': !n.read_at }">
                                        <div class="flex items-start gap-3">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                                                 :class="n.type === 'new_ticket' ? 'bg-green-100 text-green-600' : 'bg-blue-100 text-blue-600'">
                                                <i :class="n.type === 'new_ticket' ? 'fas fa-plus' : 'fas fa-reply'"></i>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900" x-text="n.title"></p>
                                                <p class="text-xs text-gray-600 truncate" x-text="n.message"></p>
                                                <p class="text-xs text-gray-400 mt-1" x-text="timeAgo(n.created_at)"></p>
                                            </div>
                                            <span x-show="!n.read_at" class="w-2 h-2 bg-indigo-500 rounded-full flex-shrink-0"></span>
                                        </div>
                                    </a>
                                </template>
                            </div>
                        </div>
                    </div>

                    <a href="<?= $app->url('logout') ?>" class="text-gray-600 hover:text-gray-900 p-2 hover:bg-gray-100 rounded-lg" title="Logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </header>

            <!-- Page content -->
            <main class="flex-1 overflow-y-auto p-4 lg:p-6">
                <?php if ($flashSuccess ?? null): ?>
                <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                    <?= htmlspecialchars($flashSuccess) ?>
                </div>
                <?php endif; ?>

                <?php if ($flashError ?? null): ?>
                <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                    <?= htmlspecialchars($flashError) ?>
                </div>
                <?php endif; ?>

                <?= $content ?? '' ?>
            </main>
        </div>
    </div>
    <script>
    // Register Service Worker for background notifications
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('<?= $app->url('service-worker.js') ?>')
            .then(registration => {
                console.log('Service Worker registered successfully:', registration);
            })
            .catch(error => {
                console.log('Service Worker registration failed:', error);
            });
    }

    // Global toast management
    window.toastsData = window.toastsData || [];

    function removeToast(id) {
        const index = window.toastsData.findIndex(t => t.id === id);
        if (index !== -1) {
            window.toastsData[index].removing = true;
            setTimeout(() => {
                window.toastsData.splice(index, 1);
            }, 300);
        }
    }

    function showToast(notification) {
        const toast = {
            id: Date.now() + Math.random(),
            type: notification.type,
            title: notification.title,
            message: notification.message,
            url: notification.ticket_id ? '<?= $app->url('tickets') ?>/' + notification.ticket_id : '#',
            removing: false
        };

        window.toastsData.push(toast);

        setTimeout(() => {
            removeToast(toast.id);
        }, 8000);
    }

    function notificationBell() {
        return {
            open: false,
            notifications: [],
            unreadCount: 0,
            lastCount: 0,
            audioEnabled: true,
            notificationPermission: 'default',

            init() {
                this.checkNotificationPermission();
                this.fetch();
                // Poll every 15 seconds
                setInterval(() => this.fetch(), 15000);
            },

            async checkNotificationPermission() {
                if (!('Notification' in window)) {
                    console.log('Browser does not support notifications');
                    return;
                }

                this.notificationPermission = Notification.permission;

                if (Notification.permission === 'default') {
                    try {
                        const permission = await Notification.requestPermission();
                        this.notificationPermission = permission;
                        if (permission === 'granted') {
                            this.showWelcomeNotification();
                        }
                    } catch (error) {
                        console.error('Notification permission error:', error);
                    }
                }
            },

            showWelcomeNotification() {
                try {
                    const notification = new Notification('Desktop Notifications Enabled', {
                        body: 'You will now receive ticket notifications even when the browser is minimized.',
                        icon: '/favicon.ico',
                        badge: '/favicon.ico',
                        tag: 'welcome-notification',
                        requireInteraction: false,
                        silent: false
                    });

                    setTimeout(() => notification.close(), 5000);
                } catch (e) {
                    console.error('Welcome notification error:', e);
                }
            },

            toggle() {
                this.open = !this.open;
                if (this.open) this.fetch();
            },

            async fetch() {
                try {
                    const res = await fetch('<?= $app->url('api/notifications') ?>');
                    const data = await res.json();
                    if (data.success) {
                        const newCount = data.data.unread_count;
                        const prevNotifications = this.notifications;

                        if (newCount > this.lastCount && this.lastCount > 0) {
                            const newNotifications = data.data.notifications.filter(n =>
                                !prevNotifications.some(prev => prev.id === n.id)
                            );

                            newNotifications.forEach(n => {
                                showToast(n);
                                this.showBrowserNotification(n);
                            });

                            if (newNotifications.length > 0) {
                                this.playSound();
                            }
                        }
                        this.lastCount = newCount;
                        this.notifications = data.data.notifications;
                        this.unreadCount = newCount;
                    }
                } catch (e) {
                    console.error('Failed to fetch notifications', e);
                }
            },

            playSound() {
                if (!this.audioEnabled) return;
                try {
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.frequency.value = 800;
                    osc.type = 'sine';
                    gain.gain.value = 0.3;
                    osc.start();
                    osc.stop(ctx.currentTime + 0.15);
                } catch (e) {}
            },

            showBrowserNotification(n) {
                if (!('Notification' in window) || Notification.permission !== 'granted' || !n) {
                    return;
                }

                try {
                    const notification = new Notification(n.title, {
                        body: n.message,
                        icon: '/favicon.ico',
                        badge: '/favicon.ico',
                        tag: 'ticket-' + n.id,
                        requireInteraction: true,
                        silent: false,
                        timestamp: Date.now(),
                        data: {
                            ticketId: n.ticket_id,
                            url: n.ticket_id ? '<?= $app->url('tickets') ?>/' + n.ticket_id : '<?= $app->url('dashboard') ?>'
                        }
                    });

                    notification.onclick = function(event) {
                        event.preventDefault();
                        window.focus();
                        if (event.target.data && event.target.data.url) {
                            window.location.href = event.target.data.url;
                        }
                        notification.close();
                    };

                    if ('actions' in notification) {
                        notification.onaction = function(event) {
                            if (event.action === 'view') {
                                window.focus();
                                if (event.target.data && event.target.data.url) {
                                    window.location.href = event.target.data.url;
                                }
                            }
                            notification.close();
                        };
                    }

                    setTimeout(() => {
                        notification.close();
                    }, 30000);

                } catch (error) {
                    console.error('Failed to show notification:', error);
                }
            },

            async markRead(id) {
                try {
                    await fetch('<?= $app->url('api/notifications') ?>/' + id + '/read', { method: 'POST' });
                    this.fetch();
                } catch (e) {}
            },

            async markAllRead() {
                try {
                    await fetch('<?= $app->url('api/notifications/read-all') ?>', { method: 'POST' });
                    this.fetch();
                } catch (e) {}
            },

            timeAgo(dateStr) {
                const date = new Date(dateStr);
                const now = new Date();
                const diff = Math.floor((now - date) / 1000);
                if (diff < 60) return 'Just now';
                if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
                if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
                return Math.floor(diff / 86400) + 'd ago';
            }
        };
    }
    </script>

    <!-- Web Push Notifications -->
    <script src="<?= $app->url('push-notifications.js') ?>"></script>
</body>
</html>
