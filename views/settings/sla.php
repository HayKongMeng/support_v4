<?php
$pageTitle = __('v_sla_configuration');
$validationErrors = $_SESSION['validation_errors'] ?? [];
unset($_SESSION['validation_errors']);
ob_start();
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="text-lg font-semibold text-gray-800"><?= __('v_sla_configuration_itil_standard') ?></h2>
        <p class="text-sm text-gray-600 mt-1">
            <?= __('v_configure_service_level_agreement_times_for_each_priority_le') ?>
        </p>
        <div class="mt-3 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <p class="text-sm text-blue-800">
                <strong><?= __('v_note') ?></strong> <?= __('v_times_are_in_minutes_standard_business_hours_are_8_am_5_30_p') ?>
            </p>
        </div>
    </div>

    <!-- SLA Policies Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800"><?= __('v_sla_policies_by_priority') ?></h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= __('v_priority') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= __('v_response_time') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= __('v_resolution_time') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= __('v_business_hours_only') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= __('v_status') ?></th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase"><?= __('v_actions') ?></th>
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
                                    <?php
                                    $priorityLabel = match($policy['priority']) {
                                        'urgent' => __('v_urgent'),
                                        'high' => __('v_high'),
                                        'medium' => __('v_medium'),
                                        default => __('v_low')
                                    };
                                    echo $priorityLabel;
                                    ?>
                                </span>
                            </div>
                        </td>

                        <!-- Edit Mode -->
                        <td colspan="5" class="px-6 py-4" x-show="editing">
                                <form action="<?= $app->url('settings/sla/' . $policy['id']) ?>" method="POST" class="space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                <?= __('v_response_time_minutes') ?>
                                            </label>
                                            <input type="number" name="response_time_minutes"
                                                   value="<?= $policy['response_time_minutes'] ?>" required min="1"
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                            <p class="text-xs text-gray-500 mt-1">
                                                <?= number_format($policy['response_time_minutes'] / 60, 1) ?> <?= __('v_hours') ?>
                                            </p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                <?= __('v_resolution_time_minutes') ?>
                                            </label>
                                            <input type="number" name="resolution_time_minutes"
                                                   value="<?= $policy['resolution_time_minutes'] ?>" required min="1"
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                            <p class="text-xs text-gray-500 mt-1">
                                                <?= number_format($policy['resolution_time_minutes'] / 60, 1) ?> <?= __('v_hours') ?>
                                            </p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                <?= __('v_business_hours_only') ?>
                                            </label>
                                            <select name="business_hours_only"
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                                <option value="0" <?= !$policy['business_hours_only'] ? 'selected' : '' ?>><?= __('v_24_7') ?></option>
                                                <option value="1" <?= $policy['business_hours_only'] ? 'selected' : '' ?>><?= __('v_business_hours_8_am_5_30_pm') ?></option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="submit"
                                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                                            <i class="fas fa-save mr-1"></i> <?= __('v_save_changes') ?>
                                        </button>
                                        <button type="button" @click="editing = false"
                                                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                                            <?= __('v_cancel') ?>
                                        </button>
                                    </div>
                                </form>
                        </td>

                        <!-- View Mode -->
                        <td class="px-6 py-4 whitespace-nowrap" x-show="!editing">
                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    <?= number_format($policy['response_time_minutes']) ?> <?= __('v_min') ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    <?= number_format($policy['response_time_minutes'] / 60, 1) ?> <?= __('v_hours') ?>
                                </p>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap" x-show="!editing">
                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    <?= number_format($policy['resolution_time_minutes']) ?> <?= __('v_min') ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    <?= number_format($policy['resolution_time_minutes'] / 60, 1) ?> <?= __('v_hours') ?>
                                    (<?= number_format($policy['resolution_time_minutes'] / 1440, 1) ?> <?= __('v_days') ?>)
                                </p>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap" x-show="!editing">
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full
                                <?= $policy['business_hours_only'] ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' ?>">
                                <?= $policy['business_hours_only'] ? __('v_8_am_5_30_pm') : '24/7' ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap" x-show="!editing">
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full
                                <?= $policy['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                <?= $policy['is_active'] ? __('v_active') : __('v_inactive') ?>
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
        <h3 class="text-lg font-semibold text-gray-800 mb-4"><?= __('v_itil_standard_reference') ?></h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h4 class="font-medium text-gray-900 mb-3"><?= __('v_recommended_response_times') ?></h4>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between p-2 bg-red-50 rounded">
                        <span class="font-medium text-red-800"><?= __('v_urgent_critical') ?></span>
                        <span class="text-red-600"><?= __('v_1_hour') ?></span>
                    </div>
                    <div class="flex justify-between p-2 bg-orange-50 rounded">
                        <span class="font-medium text-orange-800"><?= __('v_high_priority') ?></span>
                        <span class="text-orange-600"><?= __('v_4_hours') ?></span>
                    </div>
                    <div class="flex justify-between p-2 bg-yellow-50 rounded">
                        <span class="font-medium text-yellow-800"><?= __('v_medium_priority') ?></span>
                        <span class="text-yellow-600"><?= __('v_8_hours') ?></span>
                    </div>
                    <div class="flex justify-between p-2 bg-gray-50 rounded">
                        <span class="font-medium text-gray-800"><?= __('v_low_priority') ?></span>
                        <span class="text-gray-600"><?= __('v_24_hours') ?></span>
                    </div>
                </div>
            </div>
            <div>
                <h4 class="font-medium text-gray-900 mb-3"><?= __('v_recommended_resolution_times') ?></h4>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between p-2 bg-red-50 rounded">
                        <span class="font-medium text-red-800"><?= __('v_urgent_critical') ?></span>
                        <span class="text-red-600"><?= __('v_4_hours') ?></span>
                    </div>
                    <div class="flex justify-between p-2 bg-orange-50 rounded">
                        <span class="font-medium text-orange-800"><?= __('v_high_priority') ?></span>
                        <span class="text-orange-600"><?= __('v_8_hours') ?></span>
                    </div>
                    <div class="flex justify-between p-2 bg-yellow-50 rounded">
                        <span class="font-medium text-yellow-800"><?= __('v_medium_priority') ?></span>
                        <span class="text-yellow-600"><?= __('v_24_hours') ?></span>
                    </div>
                    <div class="flex justify-between p-2 bg-gray-50 rounded">
                        <span class="font-medium text-gray-800"><?= __('v_low_priority') ?></span>
                        <span class="text-gray-600"><?= __('v_72_hours_3_days') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
