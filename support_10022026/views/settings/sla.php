<?php
$pageTitle = 'SLA Configuration';
$validationErrors = $_SESSION['validation_errors'] ?? [];
unset($_SESSION['validation_errors']);
ob_start();
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="text-lg font-semibold text-gray-800">SLA Configuration (ITIL Standard)</h2>
        <p class="text-sm text-gray-600 mt-1">
            Configure Service Level Agreement times for each priority level based on ITIL international standards.
        </p>
        <div class="mt-3 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <p class="text-sm text-blue-800">
                <strong>Note:</strong> Times are in minutes. Standard business hours are 8 AM - 5:30 PM, Monday - Friday.
            </p>
        </div>
    </div>

    <!-- SLA Policies Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">SLA Policies by Priority</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Priority</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Response Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Resolution Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Business Hours Only</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($slaPolicies as $policy): ?>
                    <tr class="hover:bg-gray-50" x-data="{ editing: false }">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <span class="inline-flex px-3 py-1 text-xs font-medium rounded-full
                                    <?php
                                    switch($policy['priority']) {
                                        case 'urgent': echo 'bg-red-100 text-red-800'; break;
                                        case 'high': echo 'bg-orange-100 text-orange-800'; break;
                                        case 'medium': echo 'bg-yellow-100 text-yellow-800'; break;
                                        case 'low': echo 'bg-gray-100 text-gray-800'; break;
                                    }
                                    ?>">
                                    <?= ucfirst($policy['priority']) ?>
                                </span>
                            </div>
                        </td>

                        <!-- Edit Mode -->
                        <td colspan="5" class="px-6 py-4" x-show="editing">
                                <form action="<?= $app->url('settings/sla/' . $policy['id']) ?>" method="POST" class="space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Response Time (minutes)
                                            </label>
                                            <input type="number" name="response_time_minutes"
                                                   value="<?= $policy['response_time_minutes'] ?>" required min="1"
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                            <p class="text-xs text-gray-500 mt-1">
                                                <?= number_format($policy['response_time_minutes'] / 60, 1) ?> hours
                                            </p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Resolution Time (minutes)
                                            </label>
                                            <input type="number" name="resolution_time_minutes"
                                                   value="<?= $policy['resolution_time_minutes'] ?>" required min="1"
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                            <p class="text-xs text-gray-500 mt-1">
                                                <?= number_format($policy['resolution_time_minutes'] / 60, 1) ?> hours
                                            </p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Business Hours Only
                                            </label>
                                            <select name="business_hours_only"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                                <option value="0" <?= !$policy['business_hours_only'] ? 'selected' : '' ?>>24/7</option>
                                                <option value="1" <?= $policy['business_hours_only'] ? 'selected' : '' ?>>Business Hours (8 AM - 5:30 PM)</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="submit"
                                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                                            <i class="fas fa-save mr-1"></i> Save Changes
                                        </button>
                                        <button type="button" @click="editing = false"
                                                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                        </td>

                        <!-- View Mode -->
                        <td class="px-6 py-4 whitespace-nowrap" x-show="!editing">
                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    <?= number_format($policy['response_time_minutes']) ?> min
                                </p>
                                <p class="text-xs text-gray-500">
                                    <?= number_format($policy['response_time_minutes'] / 60, 1) ?> hours
                                </p>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap" x-show="!editing">
                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    <?= number_format($policy['resolution_time_minutes']) ?> min
                                </p>
                                <p class="text-xs text-gray-500">
                                    <?= number_format($policy['resolution_time_minutes'] / 60, 1) ?> hours
                                    (<?= number_format($policy['resolution_time_minutes'] / 1440, 1) ?> days)
                                </p>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap" x-show="!editing">
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full
                                <?= $policy['business_hours_only'] ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' ?>">
                                <?= $policy['business_hours_only'] ? '8 AM - 5:30 PM' : '24/7' ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap" x-show="!editing">
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full
                                <?= $policy['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                <?= $policy['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right" x-show="!editing">
                            <button @click="editing = true"
                                    class="text-indigo-600 hover:text-indigo-900">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ITIL Reference Guide -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">ITIL Standard Reference</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h4 class="font-medium text-gray-900 mb-3">Recommended Response Times</h4>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between p-2 bg-red-50 rounded">
                        <span class="font-medium text-red-800">Urgent (Critical)</span>
                        <span class="text-red-600">1 hour</span>
                    </div>
                    <div class="flex justify-between p-2 bg-orange-50 rounded">
                        <span class="font-medium text-orange-800">High Priority</span>
                        <span class="text-orange-600">4 hours</span>
                    </div>
                    <div class="flex justify-between p-2 bg-yellow-50 rounded">
                        <span class="font-medium text-yellow-800">Medium Priority</span>
                        <span class="text-yellow-600">8 hours</span>
                    </div>
                    <div class="flex justify-between p-2 bg-gray-50 rounded">
                        <span class="font-medium text-gray-800">Low Priority</span>
                        <span class="text-gray-600">24 hours</span>
                    </div>
                </div>
            </div>
            <div>
                <h4 class="font-medium text-gray-900 mb-3">Recommended Resolution Times</h4>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between p-2 bg-red-50 rounded">
                        <span class="font-medium text-red-800">Urgent (Critical)</span>
                        <span class="text-red-600">4 hours</span>
                    </div>
                    <div class="flex justify-between p-2 bg-orange-50 rounded">
                        <span class="font-medium text-orange-800">High Priority</span>
                        <span class="text-orange-600">8 hours</span>
                    </div>
                    <div class="flex justify-between p-2 bg-yellow-50 rounded">
                        <span class="font-medium text-yellow-800">Medium Priority</span>
                        <span class="text-yellow-600">24 hours</span>
                    </div>
                    <div class="flex justify-between p-2 bg-gray-50 rounded">
                        <span class="font-medium text-gray-800">Low Priority</span>
                        <span class="text-gray-600">72 hours (3 days)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
