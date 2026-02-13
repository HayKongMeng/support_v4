<?php
$pageTitle = 'Register';
$validationErrors = $_SESSION['validation_errors'] ?? [];
unset($_SESSION['validation_errors']);
ob_start();
?>

<h2 class="text-2xl font-bold text-gray-800 mb-6">Create Your Account</h2>

<form action="<?= $app->url('register') ?>" method="POST">
    <div class="mb-4">
        <label for="company_name" class="block text-sm font-medium text-gray-700 mb-2">Company Name</label>
        <input type="text" id="company_name" name="company_name" value="<?= htmlspecialchars($oldInput['company_name'] ?? '') ?>"
               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 <?= isset($validationErrors['company_name']) ? 'border-red-500' : '' ?>"
               placeholder="Acme Inc." required>
        <?php if (isset($validationErrors['company_name'])): ?>
        <p class="text-red-500 text-xs mt-1"><?= $validationErrors['company_name'] ?></p>
        <?php endif; ?>
    </div>

    <div class="mb-4">
        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Your Name</label>
        <input type="text" id="name" name="name" value="<?= htmlspecialchars($oldInput['name'] ?? '') ?>"
               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 <?= isset($validationErrors['name']) ? 'border-red-500' : '' ?>"
               placeholder="John Doe" required>
        <?php if (isset($validationErrors['name'])): ?>
        <p class="text-red-500 text-xs mt-1"><?= $validationErrors['name'] ?></p>
        <?php endif; ?>
    </div>

    <div class="mb-4">
        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($oldInput['email'] ?? '') ?>"
               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 <?= isset($validationErrors['email']) ? 'border-red-500' : '' ?>"
               placeholder="you@example.com" required>
        <?php if (isset($validationErrors['email'])): ?>
        <p class="text-red-500 text-xs mt-1"><?= $validationErrors['email'] ?></p>
        <?php endif; ?>
    </div>

    <div class="mb-4">
        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
        <input type="password" id="password" name="password"
               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 <?= isset($validationErrors['password']) ? 'border-red-500' : '' ?>"
               placeholder="••••••••" required minlength="6">
        <?php if (isset($validationErrors['password'])): ?>
        <p class="text-red-500 text-xs mt-1"><?= $validationErrors['password'] ?></p>
        <?php endif; ?>
    </div>

    <div class="mb-6">
        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label>
        <input type="password" id="password_confirmation" name="password_confirmation"
               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
               placeholder="••••••••" required>
    </div>

    <button type="submit"
            class="w-full py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition-colors">
        Create Account
    </button>
</form>

<div class="mt-6 text-center">
    <p class="text-gray-600">
        Already have an account?
        <a href="<?= $app->url('login') ?>" class="text-indigo-600 font-medium hover:underline">
            Sign in
        </a>
    </p>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/auth.php';
