<?php
$pageTitle = __('v_users');
$validationErrors = $_SESSION['validation_errors'] ?? [];
unset($_SESSION['validation_errors']);
$departments = $departments ?? [];
$staffUsers = $staffUsers ?? [];
$departmentFeatureEnabled = $departmentFeatureEnabled ?? false;
$telegramBotUsername = $telegramBotUsername ?? '';
$telegramLinkEnabled = $telegramLinkEnabled ?? false;
$telegramStartLinks = $telegramStartLinks ?? [];
$userFilters = $userFilters ?? [
    'tab' => 'users',
    'search' => '',
    'role' => '',
    'status' => '',
    'department_id' => 0,
];
$activeTab = in_array(($activeTab ?? 'users'), ['users', 'departments'], true) ? $activeTab : 'users';
$totalUsersCount = (int) ($totalUsersCount ?? count($users ?? []));
$filteredUsersCount = (int) ($filteredUsersCount ?? count($users ?? []));

$usersTabParams = ['tab' => 'users'];
if (($userFilters['search'] ?? '') !== '') {
    $usersTabParams['search'] = $userFilters['search'];
}
if (($userFilters['role'] ?? '') !== '') {
    $usersTabParams['role'] = $userFilters['role'];
}
if (($userFilters['status'] ?? '') !== '') {
    $usersTabParams['status'] = $userFilters['status'];
}
if ((int) ($userFilters['department_id'] ?? 0) > 0) {
    $usersTabParams['department_id'] = (int) $userFilters['department_id'];
}
$usersTabUrl = $app->url('settings/users') . '?' . http_build_query($usersTabParams);
$departmentsTabUrl = $app->url('settings/users?tab=departments');
ob_start();
?>

<div class="space-y-6">
    <div class="bg-white rounded-xl shadow-sm p-2">
        <div class="flex flex-wrap gap-2">
            <a href="<?= htmlspecialchars($usersTabUrl) ?>"
               class="px-4 py-2 rounded-lg text-sm font-medium <?= $activeTab === 'users' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                <?= __('users_tab_manage') ?>
            </a>
            <a href="<?= htmlspecialchars($departmentsTabUrl) ?>"
               class="px-4 py-2 rounded-lg text-sm font-medium <?= $activeTab === 'departments' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                <?= __('users_tab_departments') ?>
            </a>
        </div>
    </div>

    <?php if ($activeTab === 'users'): ?>
    <!-- Add User Form -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800"><?= __('v_add_new_user') ?></h2>
            <p class="text-sm text-gray-600 mt-1">
                <strong><?= __('v_role_types') ?></strong>
                <span class="ml-2"><?= __('v_agent_assigned_tickets_only') ?></span> |
                <span class="ml-2"><?= __('v_front_back_office_assigned_tickets_only') ?></span>
            </p>
        </div>
        <form action="<?= $app->url('settings/users') ?>" method="POST" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                <div>
                    <input type="text" name="name" placeholder="<?= __('v_full_name') ?>" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 <?= isset($validationErrors['name']) ? 'border-red-500' : '' ?>">
                </div>
                <div>
                    <input type="email" name="email" placeholder="<?= __('v_email_address') ?>" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 <?= isset($validationErrors['email']) ? 'border-red-500' : '' ?>">
                </div>
                <div>
                    <input type="password" name="password" placeholder="<?= __('v_password') ?>" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <select name="role" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <option value="agent"><?= __('v_agent') ?></option>
                        <option value="front_office_agent"><?= __('v_front_office_agent') ?></option>
                        <option value="back_office_agent"><?= __('v_back_office_agent') ?></option>
                        <option value="admin"><?= __('v_admin') ?></option>
                        <option value="customer"><?= __('v_customer') ?></option>
                    </select>
                </div>
                <div>
                    <select name="department_id"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 <?= isset($validationErrors['department_id']) ? 'border-red-500' : '' ?>"
                            <?= $departmentFeatureEnabled ? '' : 'disabled' ?>>
                        <option value=""><?= __('users_department_optional') ?></option>
                        <?php foreach ($departments as $department): ?>
                        <option value="<?= (int)$department['id'] ?>">
                            <?= htmlspecialchars($department['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit"
                            class="w-full px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            </div>
            <?php if (!$departmentFeatureEnabled): ?>
            <p class="mt-3 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                <?= __('users_department_feature_unavailable') ?>
            </p>
            <?php endif; ?>
            <?php if (isset($validationErrors['department_id'])): ?>
            <p class="mt-2 text-xs text-red-500"><?= htmlspecialchars($validationErrors['department_id']) ?></p>
            <?php endif; ?>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($activeTab === 'departments'): ?>
    <!-- Departments -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800"><?= __('users_departments_title') ?></h2>
            <p class="text-sm text-gray-600 mt-1"><?= __('users_departments_desc') ?></p>
        </div>
            <div class="p-6 space-y-4">
            <?php if ($departmentFeatureEnabled): ?>
            <form action="<?= $app->url('settings/users/departments?tab=departments') ?>" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-500 mb-1"><?= __('users_department_name_label') ?></label>
                    <input type="text" name="department_name" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                           placeholder="<?= __('users_department_name_placeholder') ?>">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1"><?= __('users_manager_optional') ?></label>
                    <select name="manager_user_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <option value=""><?= __('users_no_manager') ?></option>
                        <?php foreach ($staffUsers as $staff): ?>
                        <option value="<?= (int)$staff['id'] ?>">
                            <?= htmlspecialchars($staff['name']) ?> (<?= htmlspecialchars($staff['role']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                        <?= __('users_add_department') ?>
                    </button>
                </div>
            </form>

            <div class="space-y-2">
                <?php if (empty($departments)): ?>
                <p class="text-sm text-gray-500"><?= __('users_no_departments') ?></p>
                <?php endif; ?>

                <?php foreach ($departments as $department): ?>
                <div class="flex items-center justify-between gap-3 p-3 border border-gray-200 rounded-lg">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($department['name']) ?></p>
                        <p class="text-xs text-gray-500">
                            <?= __('users_manager') ?>: <?= htmlspecialchars($department['manager_name'] ?? '-') ?>
                            | <?= __('users_members') ?>: <?= (int)($department['member_count'] ?? 0) ?>
                        </p>
                    </div>
                    <form action="<?= $app->url('settings/users/departments/' . (int)$department['id'] . '/delete?tab=departments') ?>" method="POST"
                          onsubmit="return confirm('<?= e(__('users_deactivate_department_confirm')) ?>')">
                        <button type="submit" class="px-3 py-1.5 text-xs font-medium bg-red-50 text-red-700 rounded-lg hover:bg-red-100">
                            <?= __('users_remove') ?>
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                <?= __('users_department_tables_unavailable') ?>
            </p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($activeTab === 'users'): ?>
    <!-- Telegram Staff Linking -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800"><?= __('users_telegram_linking_title') ?></h2>
            <p class="text-sm text-gray-600 mt-1"><?= __('users_telegram_linking_desc') ?></p>
        </div>
        <div class="p-6">
            <?php if ($telegramLinkEnabled && $telegramBotUsername !== ''): ?>
            <p class="text-sm text-gray-700">
                <?= __('users_bot_username') ?>:
                <a href="https://t.me/<?= urlencode($telegramBotUsername) ?>" target="_blank" class="font-semibold text-indigo-600 hover:underline">
                    @<?= htmlspecialchars($telegramBotUsername) ?>
                </a>
            </p>
            <p class="text-xs text-gray-500 mt-2">
                <?= __('users_open_link_start') ?>
            </p>
            <?php else: ?>
            <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                <?= __('users_enable_telegram_prefix') ?>
                <a href="<?= $app->url('settings/telegram') ?>" class="underline text-amber-800"><?= __('v_telegram_settings') ?></a>
                <?= __('users_enable_telegram_suffix') ?>
            </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Users List -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200 space-y-4">
            <h2 class="text-lg font-semibold text-gray-800"><?= __('v_team_members') ?></h2>
            <div class="flex items-center justify-between text-sm text-gray-600">
                <p><?= __('users_showing_count', ['filtered' => (int) $filteredUsersCount, 'total' => (int) $totalUsersCount]) ?></p>
            </div>
            <form method="GET" action="<?= $app->url('settings/users') ?>" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
                <input type="hidden" name="tab" value="users">
                <div>
                    <label class="block text-xs text-gray-500 mb-1"><?= __('v_search') ?></label>
                    <input type="text"
                           name="search"
                           value="<?= htmlspecialchars((string) ($userFilters['search'] ?? '')) ?>"
                           placeholder="<?= __('users_search_placeholder') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1"><?= __('v_role') ?></label>
                    <select name="role" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                        <option value=""><?= __('users_all_roles') ?></option>
                        <option value="super_admin" <?= ($userFilters['role'] ?? '') === 'super_admin' ? 'selected' : '' ?>><?= __('users_super_admin') ?></option>
                        <option value="admin" <?= ($userFilters['role'] ?? '') === 'admin' ? 'selected' : '' ?>><?= __('v_admin') ?></option>
                        <option value="agent" <?= ($userFilters['role'] ?? '') === 'agent' ? 'selected' : '' ?>><?= __('v_agent') ?></option>
                        <option value="front_office_agent" <?= ($userFilters['role'] ?? '') === 'front_office_agent' ? 'selected' : '' ?>><?= __('v_front_office_agent') ?></option>
                        <option value="back_office_agent" <?= ($userFilters['role'] ?? '') === 'back_office_agent' ? 'selected' : '' ?>><?= __('v_back_office_agent') ?></option>
                        <option value="customer" <?= ($userFilters['role'] ?? '') === 'customer' ? 'selected' : '' ?>><?= __('v_customer') ?></option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1"><?= __('v_status') ?></label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                        <option value=""><?= __('users_all_status') ?></option>
                        <option value="active" <?= ($userFilters['status'] ?? '') === 'active' ? 'selected' : '' ?>><?= __('v_active') ?></option>
                        <option value="inactive" <?= ($userFilters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>><?= __('v_inactive') ?></option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1"><?= __('users_department_label') ?></label>
                    <select name="department_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm" <?= $departmentFeatureEnabled ? '' : 'disabled' ?>>
                        <option value=""><?= __('users_all_departments') ?></option>
                        <?php foreach ($departments as $department): ?>
                        <option value="<?= (int) $department['id'] ?>" <?= (int) ($userFilters['department_id'] ?? 0) === (int) $department['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($department['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm">
                        <?= __('users_filter') ?>
                    </button>
                    <a href="<?= $app->url('settings/users?tab=users') ?>" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm">
                        <?= __('users_reset') ?>
                    </a>
                </div>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= __('v_user') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= __('v_email') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= __('v_role') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= __('users_department_label') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= __('v_status') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= __('users_telegram') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= __('v_last_login') ?></th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase"><?= __('v_actions') ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-sm text-gray-500">
                            <?= __('users_no_match') ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($users as $u): ?>
                    <tr class="hover:bg-gray-50" x-data="{ editing: false }">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-600 font-medium">
                                    <?= strtoupper(substr($u['name'], 0, 1)) ?>
                                </div>
                                <div class="ml-3">
                                    <p class="font-medium text-gray-900"><?= htmlspecialchars($u['name']) ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= htmlspecialchars($u['email']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full
                                <?php
                                switch($u['role']) {
                                    case 'admin': echo 'bg-purple-100 text-purple-800'; break;
                                    case 'agent': echo 'bg-blue-100 text-blue-800'; break;
                                    case 'front_office_agent': echo 'bg-green-100 text-green-800'; break;
                                    case 'back_office_agent': echo 'bg-yellow-100 text-yellow-800'; break;
                                    default: echo 'bg-gray-100 text-gray-800';
                                }
                                ?>">
                                <?php
                                $roleDisplay = match($u['role']) {
                                    'front_office_agent' => __('v_front_office'),
                                    'back_office_agent' => __('v_back_office'),
                                    'admin' => __('v_admin'),
                                    'agent' => __('v_agent'),
                                    'customer' => __('v_customer'),
                                    default => ucfirst($u['role'])
                                };
                                echo $roleDisplay;
                                ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            <?= htmlspecialchars($u['department_name'] ?? '-') ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if ($u['is_active']): ?>
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800"><?= __('v_active') ?></span>
                            <?php else: ?>
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800"><?= __('v_inactive') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if (($u['role'] ?? '') === 'customer'): ?>
                            <span class="text-xs text-gray-400">-</span>
                            <?php elseif (!empty($u['telegram_chat_id'])): ?>
                            <div class="text-xs">
                                <div class="font-medium text-green-700"><?= __('users_connected') ?></div>
                                <?php if (!empty($u['telegram_username'])): ?>
                                <div class="text-gray-500">@<?= htmlspecialchars($u['telegram_username']) ?></div>
                                <?php endif; ?>
                            </div>
                            <?php elseif ($telegramLinkEnabled && !empty($telegramStartLinks[(int)$u['id']])): ?>
                            <a href="<?= htmlspecialchars($telegramStartLinks[(int)$u['id']]) ?>"
                               target="_blank"
                               class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium bg-indigo-50 text-indigo-700 rounded-md hover:bg-indigo-100">
                                <?= __('users_link_telegram') ?>
                            </a>
                            <?php else: ?>
                            <span class="text-xs text-gray-400"><?= __('users_not_linked') ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= $u['last_login_at'] ? date('M j, Y', strtotime($u['last_login_at'])) : __('v_never') ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                            <div class="flex items-center justify-end gap-3">
                                <button @click="editing = !editing" class="text-indigo-600 hover:text-indigo-900" title="<?= __('v_edit') ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($u['id'] != $user['id']): ?>
                                <form action="<?= $app->url('settings/users/' . $u['id']) ?>" method="POST"
                                      onsubmit="return confirm('<?= __('v_delete_this_user') ?>')">
                                    <input type="hidden" name="_method" value="DELETE">
                                    <button type="submit" class="text-red-600 hover:text-red-700" title="<?= __('v_delete') ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <tr x-show="editing" x-cloak class="bg-gray-50">
                        <td colspan="8" class="px-6 py-4">
                            <form action="<?= $app->url('settings/users/' . $u['id']) ?>" method="POST"
                                  class="grid grid-cols-1 md:grid-cols-8 gap-4 items-end">
                                <div class="md:col-span-2">
                                    <label class="block text-xs text-gray-500 mb-1"><?= __('v_name') ?></label>
                                    <input type="text" name="name" value="<?= htmlspecialchars($u['name']) ?>" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs text-gray-500 mb-1"><?= __('v_email') ?></label>
                                    <input type="email" name="email" value="<?= htmlspecialchars($u['email']) ?>" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1"><?= __('v_role') ?></label>
                                    <select name="role" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                        <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>><?= __('v_admin') ?></option>
                                        <option value="agent" <?= $u['role'] === 'agent' ? 'selected' : '' ?>><?= __('v_agent') ?></option>
                                        <option value="front_office_agent" <?= $u['role'] === 'front_office_agent' ? 'selected' : '' ?>><?= __('v_front_office_agent') ?></option>
                                        <option value="back_office_agent" <?= $u['role'] === 'back_office_agent' ? 'selected' : '' ?>><?= __('v_back_office_agent') ?></option>
                                        <option value="customer" <?= $u['role'] === 'customer' ? 'selected' : '' ?>><?= __('v_customer') ?></option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1"><?= __('users_department_label') ?></label>
                                    <select name="department_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" <?= $departmentFeatureEnabled ? '' : 'disabled' ?>>
                                        <option value=""><?= __('users_no_department') ?></option>
                                        <?php foreach ($departments as $department): ?>
                                        <option value="<?= (int)$department['id'] ?>" <?= ((int)($u['department_id'] ?? 0) === (int)$department['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($department['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1"><?= __('v_status') ?></label>
                                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="is_active" value="1" <?= $u['is_active'] ? 'checked' : '' ?>>
                                        <?= __('v_active') ?>
                                    </label>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1"><?= __('v_reset_password') ?></label>
                                    <input type="password" name="password" placeholder="<?= __('v_new_password') ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                </div>
                                <div class="md:col-span-8 flex gap-2 justify-end">
                                    <button type="button" @click="editing = false"
                                            class="px-4 py-2 text-sm border border-gray-300 rounded-lg"><?= __('v_cancel') ?></button>
                                    <button type="submit"
                                            class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg"><?= __('v_save') ?></button>
                                </div>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
