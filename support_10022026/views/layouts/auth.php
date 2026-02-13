<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Login' ?> - Support Desk</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-indigo-500 to-purple-600 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-white rounded-full shadow-lg mb-4">
                <i class="fas fa-headset text-indigo-600 text-2xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-white">Support Desk</h1>
            <p class="text-indigo-200 mt-2">Modern AI-Powered Ticketing System</p>
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
            &copy; <?= date('Y') ?> Support Desk. All rights reserved.
        </p>
    </div>
</body>
</html>
