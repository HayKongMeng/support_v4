<!DOCTYPE html>
<html lang="<?= e(app_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('error_404_title') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
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
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
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

    <div class="text-center">
        <h1 class="text-9xl font-bold text-gray-300">404</h1>
        <h2 class="text-2xl font-semibold text-gray-800 mt-4"><?= __('error_404_heading') ?></h2>
        <p class="text-gray-500 mt-2"><?= __('error_404_message') ?></p>
        <a href="javascript:history.back()"
           class="inline-flex items-center px-4 py-2 mt-6 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700">
            <?= __('error_404_back') ?>
        </a>
    </div>
</body>
</html>
