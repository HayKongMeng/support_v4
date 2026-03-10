<?php $lang = app_lang(); ?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? __('app_support_portal') ?> - <?= htmlspecialchars($company['name'] ?? __('support')) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+Khmer:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        html[lang="km"] body,
        html[lang="km"] input,
        html[lang="km"] textarea,
        html[lang="km"] select,
        html[lang="km"] button {
            font-family: 'Noto Serif Khmer', serif;
            line-height: 1.65;
        }

        .page-load-progress {
            position: fixed;
            top: 0;
            left: 0;
            width: 0;
            height: 4px;
            z-index: 9999;
            pointer-events: none;
            background: linear-gradient(90deg, #ff004d, #ff7a00, #ffe100, #00c853, #00b0ff, #7c4dff, #ff00b3, #ff004d);
            background-size: 300% 100%;
            animation: rainbow-shift 1.8s linear infinite;
            box-shadow: 0 0 12px rgba(255, 255, 255, 0.7);
            transition: width 180ms ease, opacity 260ms ease;
        }
        .page-load-progress.is-done {
            opacity: 0;
        }
        @keyframes rainbow-shift {
            from { background-position: 0% 50%; }
            to { background-position: 100% 50%; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div id="page-load-progress" class="page-load-progress" aria-hidden="true"></div>
    <script>
    (function () {
        const bar = document.getElementById('page-load-progress');
        if (!bar) return;

        let progress = 8;
        bar.style.width = progress + '%';

        const timer = setInterval(() => {
            if (progress >= 92) return;
            progress += progress < 50 ? 9 : (progress < 80 ? 3 : 1);
            bar.style.width = progress + '%';
        }, 120);

        window.addEventListener('load', () => {
            clearInterval(timer);
            bar.style.width = '100%';
            setTimeout(() => {
                bar.classList.add('is-done');
                setTimeout(() => bar.remove(), 260);
            }, 120);
        }, { once: true });
    })();
    </script>

    <!-- Header -->
    <header class="bg-white shadow-sm">
        <div class="max-w-5xl mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-indigo-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-headset text-white"></i>
                    </div>
                    <div>
                        <h1 class="font-bold text-gray-900"><?= htmlspecialchars($company['name'] ?? __('support')) ?></h1>
                        <p class="text-xs text-gray-500"><?= __('app_support_portal') ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2 text-xs">
                        <span class="text-gray-500"><?= __('language') ?>:</span>
                        <a href="<?= e(lang_url('en')) ?>"
                           class="px-2 py-1 rounded border <?= $lang === 'en' ? 'border-indigo-600 text-indigo-700 bg-indigo-50' : 'border-gray-200 text-gray-600 hover:text-gray-800' ?>">
                            <?= __('english') ?>
                        </a>
                        <a href="<?= e(lang_url('km')) ?>"
                           class="px-2 py-1 rounded border <?= $lang === 'km' ? 'border-indigo-600 text-indigo-700 bg-indigo-50' : 'border-gray-200 text-gray-600 hover:text-gray-800' ?>">
                            <?= __('khmer') ?>
                        </a>
                    </div>
                    <span class="text-sm text-gray-600"><?= htmlspecialchars($user['name']) ?></span>
                    <a href="<?= $app->url('customer/profile') ?>" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-user-circle text-xl"></i>
                    </a>
                    <a href="<?= $app->url('logout') ?>" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="bg-white border-b">
        <div class="max-w-5xl mx-auto px-4">
            <div class="flex gap-6">
                <a href="<?= $app->url('customer/tickets') ?>"
                   class="py-3 border-b-2 <?= strpos($_SERVER['REQUEST_URI'], 'customer/tickets') !== false && strpos($_SERVER['REQUEST_URI'], 'create') === false ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                    <i class="fas fa-ticket-alt mr-2"></i> <?= __('my_tickets') ?>
                </a>
                <a href="<?= $app->url('customer/tickets/create') ?>"
                   class="py-3 border-b-2 <?= strpos($_SERVER['REQUEST_URI'], 'create') !== false ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                    <i class="fas fa-plus mr-2"></i> <?= __('new_ticket') ?>
                </a>
                <a href="<?= $app->url('kb') ?>"
                   class="py-3 border-b-2 <?= strpos($_SERVER['REQUEST_URI'], '/kb') !== false ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                    <i class="fas fa-book mr-2"></i> <?= __('knowledge_base') ?>
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-5xl mx-auto px-4 py-8">
        <?php if ($flashSuccess ?? null): ?>
        <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
            <?= htmlspecialchars($flashSuccess) ?>
        </div>
        <?php endif; ?>

        <?php if ($flashError ?? null): ?>
        <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
            <?= htmlspecialchars($flashError) ?>
        </div>
        <?php endif; ?>

        <?= $content ?? '' ?>
    </main>

    <!-- Footer -->
    <footer class="mt-auto py-6 text-center text-sm text-gray-500">
        <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($company['name'] ?? __('app_support_desk')) ?>. <?= __('all_rights_reserved') ?></p>
    </footer>
</body>
</html>
