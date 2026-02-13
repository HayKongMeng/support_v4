<?php
$pageTitle = 'Login';
ob_start();
?>

<h2 class="text-2xl font-bold text-gray-800 mb-6">Welcome Back</h2>

<form action="<?= $app->url('login') ?>" method="POST">
    <div class="mb-4">
        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($oldInput['email'] ?? '') ?>"
               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
               placeholder="you@example.com" required>
    </div>

    <div class="mb-6">
        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
        <input type="password" id="password" name="password"
               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
               placeholder="••••••••" required>
    </div>

    <button type="submit"
            class="w-full py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition-colors">
        Sign In
    </button>
</form>

<div class="mt-6 text-center">
    <p class="text-gray-600">
        Don't have an account?
        <a href="<?= $app->url('register') ?>" class="text-indigo-600 font-medium hover:underline">
            Create one
        </a>
    </p>
</div>

<!-- Demo credentials -->
<div class="mt-6 p-4 bg-gray-50 rounded-lg">
    <p class="text-xs text-gray-500 font-medium mb-2">Demo Credentials:</p>
    <p class="text-xs text-gray-600">Admin: admin@demo.com / password</p>
    <p class="text-xs text-gray-600">Agent: agent@demo.com / password</p>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/auth.php';
