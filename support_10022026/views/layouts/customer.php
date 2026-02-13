<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Support Portal' ?> - <?= htmlspecialchars($company['name'] ?? 'Support') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <header class="bg-white shadow-sm">
        <div class="max-w-5xl mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-indigo-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-headset text-white"></i>
                    </div>
                    <div>
                        <h1 class="font-bold text-gray-900"><?= htmlspecialchars($company['name'] ?? 'Support') ?></h1>
                        <p class="text-xs text-gray-500">Support Portal</p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
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
                    <i class="fas fa-ticket-alt mr-2"></i> My Tickets
                </a>
                <a href="<?= $app->url('customer/tickets/create') ?>"
                   class="py-3 border-b-2 <?= strpos($_SERVER['REQUEST_URI'], 'create') !== false ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                    <i class="fas fa-plus mr-2"></i> New Ticket
                </a>
                <a href="<?= $app->url('kb') ?>"
                   class="py-3 border-b-2 <?= strpos($_SERVER['REQUEST_URI'], '/kb') !== false ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                    <i class="fas fa-book mr-2"></i> Knowledge Base
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
        <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($company['name'] ?? 'Support Desk') ?>. All rights reserved.</p>
    </footer>
</body>
</html>
