<?php $lang = app_lang(); ?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? __('login') ?> - <?= __('app_support_desk') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
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
<body class="bg-gradient-to-br from-indigo-500 to-purple-600 min-h-screen flex items-center justify-center p-4">
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

    <div class="fixed top-4 right-4 z-10 flex items-center gap-2 text-xs">
        <span class="text-indigo-100"><?= __('language') ?>:</span>
        <a href="<?= e(lang_url('en')) ?>"
           class="px-2 py-1 rounded border <?= $lang === 'en' ? 'border-white bg-white text-indigo-700' : 'border-indigo-200 text-white hover:bg-white/10' ?>">
            <?= __('english') ?>
        </a>
        <a href="<?= e(lang_url('km')) ?>"
           class="px-2 py-1 rounded border <?= $lang === 'km' ? 'border-white bg-white text-indigo-700' : 'border-indigo-200 text-white hover:bg-white/10' ?>">
            <?= __('khmer') ?>
        </a>
    </div>
    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-white rounded-full shadow-lg mb-4">
                <i class="fas fa-headset text-indigo-600 text-2xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-white"><?= __('app_support_desk') ?></h1>
            <p class="text-indigo-200 mt-2"><?= __('modern_ai_ticketing_system') ?></p>
        </div>

        <!-- Card -->
        <div class="bg-white rounded-2xl shadow-xl p-8">
            <?php if ($flashError ?? null): ?>
            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg text-sm">
                <?= htmlspecialchars($flashError) ?>
            </div>
            <?php endif; ?>

            <?php if ($flashSuccess ?? null): ?>
            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg text-sm">
                <?= htmlspecialchars($flashSuccess) ?>
            </div>
            <?php endif; ?>

            <?= $content ?? '' ?>
        </div>

        <!-- Footer -->
        <p class="text-center text-indigo-200 mt-6 text-sm">
            &copy; <?= date('Y') ?> <?= __('app_support_desk') ?>. <?= __('all_rights_reserved') ?>
        </p>
    </div>
</body>
</html>
