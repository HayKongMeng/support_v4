<?php
$pageTitle = __('my_profile');
ob_start();
?>

<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800"><?= __('profile_settings') ?></h2>
            <p class="text-sm text-gray-500 mt-1"><?= __('update_account_info') ?></p>
        </div>

        <form action="<?= $app->url('customer/profile') ?>" method="POST" class="p-6 space-y-6">
            <!-- Basic Info -->
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-2"><?= __('full_name') ?></label>
                <input type="text" id="name" name="name"
                       value="<?= htmlspecialchars($user['name']) ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                       required>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-2"><?= __('email_address') ?></label>
                <input type="email" id="email"
                       value="<?= htmlspecialchars($user['email']) ?>"
                       class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-500"
                       disabled>
                <p class="text-xs text-gray-500 mt-1"><?= __('email_cannot_change') ?></p>
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700 mb-2"><?= __('phone_number') ?></label>
                <input type="text" id="phone" name="phone"
                       value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                       placeholder="+1 234 567 8900">
            </div>

            <hr>

            <!-- Change Password -->
            <h3 class="text-lg font-medium text-gray-800"><?= __('change_password') ?></h3>
            <p class="text-sm text-gray-500"><?= __('leave_blank_keep_password') ?></p>

            <div>
                <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2"><?= __('current_password') ?></label>
                <input type="password" id="current_password" name="current_password"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2"><?= __('new_password') ?></label>
                <input type="password" id="new_password" name="new_password"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                       minlength="6">
            </div>

            <div class="flex justify-end pt-6 border-t border-gray-200">
                <button type="submit"
                        class="px-6 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700">
                    <?= __('save_changes') ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Telegram Link Info -->
    <?php if ($user['telegram_chat_id']): ?>
    <div class="mt-6 p-4 bg-green-50 rounded-lg">
        <div class="flex items-center">
            <i class="fab fa-telegram text-green-600 text-xl mr-3"></i>
            <div>
                <h4 class="font-medium text-green-900"><?= __('telegram_connected') ?></h4>
                <p class="text-sm text-green-700">
                    <?= __('account_linked_telegram') ?>
                    <?= $user['telegram_username'] ? "(@{$user['telegram_username']})" : '' ?>
                </p>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="mt-6 p-4 bg-blue-50 rounded-lg">
        <div class="flex items-center">
            <i class="fab fa-telegram text-blue-600 text-xl mr-3"></i>
            <div>
                <h4 class="font-medium text-blue-900"><?= __('connect_telegram') ?></h4>
                <p class="text-sm text-blue-700">
                    <?= __('link_telegram_for_tickets') ?>
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/customer.php';
