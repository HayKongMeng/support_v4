<?php
$pageTitle = 'General Settings';
ob_start();
?>

<div class="max-w-3xl">
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Company Settings</h2>
            <p class="text-sm text-gray-500 mt-1">Manage your company information and preferences</p>
        </div>

        <form action="<?= $app->url('settings/general') ?>" method="POST" class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Company Name</label>
                    <input type="text" id="name" name="name"
                           value="<?= htmlspecialchars($company['name']) ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                           required>
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Support Email</label>
                    <input type="email" id="email" name="email"
                           value="<?= htmlspecialchars($company['email'] ?? '') ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                    <input type="text" id="phone" name="phone"
                           value="<?= htmlspecialchars($company['phone'] ?? '') ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="timezone" class="block text-sm font-medium text-gray-700 mb-2">Timezone</label>
                    <select id="timezone" name="timezone"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <?php
                        $timezones = [
                            'UTC',
                            'Asia/Phnom_Penh',
                            'Asia/Ho_Chi_Minh',
                            'Asia/Singapore',
                            'Asia/Kuala_Lumpur',
                            'Asia/Jakarta',
                            'Asia/Manila',
                            'Asia/Tokyo',
                            'Asia/Shanghai',
                            'Asia/Hong_Kong',
                            'Asia/Seoul',
                            'Asia/Dubai',
                            'Asia/Kolkata',
                            'Europe/London',
                            'Europe/Paris',
                            'America/New_York',
                            'America/Los_Angeles',
                            'Australia/Sydney',
                        ];
                        foreach ($timezones as $tz):
                        ?>
                        <option value="<?= $tz ?>" <?= ($company['timezone'] ?? 'UTC') === $tz ? 'selected' : '' ?>>
                            <?= $tz ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <hr class="my-6">

            <h3 class="text-lg font-medium text-gray-800 mb-4">Ticket Settings</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="ticket_prefix" class="block text-sm font-medium text-gray-700 mb-2">Ticket Number Prefix</label>
                    <input type="text" id="ticket_prefix" name="ticket_prefix"
                           value="<?= htmlspecialchars($settings['ticket_prefix'] ?? 'TKT') ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                           placeholder="TKT">
                    <p class="text-xs text-gray-500 mt-1">Example: TKT-000001</p>
                </div>
            </div>

            <div class="space-y-4 mt-6">
                <label class="flex items-center">
                    <input type="checkbox" name="ai_categorization_enabled" value="1"
                           <?= ($settings['ai_categorization_enabled'] ?? true) ? 'checked' : '' ?>
                           class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                    <span class="ml-2 text-sm text-gray-700">Enable AI auto-categorization</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="auto_assign_enabled" value="1"
                           <?= ($settings['auto_assign_enabled'] ?? false) ? 'checked' : '' ?>
                           class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                    <span class="ml-2 text-sm text-gray-700">Enable auto-assignment based on category</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="customer_portal_enabled" value="1"
                           <?= ($settings['customer_portal_enabled'] ?? true) ? 'checked' : '' ?>
                           class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                    <span class="ml-2 text-sm text-gray-700">Enable customer self-service portal</span>
                </label>
            </div>

            <div class="flex justify-end pt-6 border-t border-gray-200">
                <button type="submit"
                        class="px-6 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700">
                    Save Settings
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
