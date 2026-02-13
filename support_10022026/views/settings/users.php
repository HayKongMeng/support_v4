<?php
$pageTitle = 'Users';
$validationErrors = $_SESSION['validation_errors'] ?? [];
unset($_SESSION['validation_errors']);
ob_start();
?>

<div class="space-y-6">
    <!-- Add User Form -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Add New User</h2>
            <p class="text-sm text-gray-600 mt-1">
                <strong>Role Types:</strong>
                <span class="ml-2">Agent (Full Access) - Can see all tickets</span> |
                <span class="ml-2">Front/Back Office - Can only see assigned tickets</span>
            </p>
        </div>
        <form action="<?= $app->url('settings/users') ?>" method="POST" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <input type="text" name="name" placeholder="Full Name" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 <?= isset($validationErrors['name']) ? 'border-red-500' : '' ?>">
                </div>
                <div>
                    <input type="email" name="email" placeholder="Email Address" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 <?= isset($validationErrors['email']) ? 'border-red-500' : '' ?>">
                </div>
                <div>
                    <input type="password" name="password" placeholder="Password" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="flex gap-2">
                    <select name="role" required
                            class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <option value="agent">Agent (Full Access)</option>
                        <option value="front_office_agent">Front Office Agent</option>
                        <option value="back_office_agent">Back Office Agent</option>
                        <option value="admin">Admin</option>
                        <option value="customer">Customer</option>
                    </select>
                    <button type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Users List -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Team Members</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Login</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($users as $u): ?>
                    <tr class="hover:bg-gray-50" x-data="{ editing: false }">
                        <!-- User Info Column -->
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

                        <!-- Edit Mode -->
                        <td colspan="5" class="px-6 py-4" x-show="editing" style="display: none;">
                            <form action="<?= $app->url('settings/users/' . $u['id']) ?>" method="POST" class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                        <input type="text" name="name" value="<?= htmlspecialchars($u['name']) ?>" required
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                        <input type="email" name="email" value="<?= htmlspecialchars($u['email']) ?>" required
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                                        <select name="role" required
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                            <option value="agent" <?= $u['role'] === 'agent' ? 'selected' : '' ?>>Agent (Full Access)</option>
                                            <option value="front_office_agent" <?= $u['role'] === 'front_office_agent' ? 'selected' : '' ?>>Front Office Agent</option>
                                            <option value="back_office_agent" <?= $u['role'] === 'back_office_agent' ? 'selected' : '' ?>>Back Office Agent</option>
                                            <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                            <option value="customer" <?= $u['role'] === 'customer' ? 'selected' : '' ?>>Customer</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                        <select name="is_active"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                            <option value="1" <?= $u['is_active'] ? 'selected' : '' ?>>Active</option>
                                            <option value="0" <?= !$u['is_active'] ? 'selected' : '' ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">New Password (leave blank to keep current)</label>
                                    <input type="password" name="password" placeholder="Enter new password if changing"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
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
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-show="!editing">
                            <?= htmlspecialchars($u['email']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap" x-show="!editing">
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
                                    'front_office_agent' => 'Front Office',
                                    'back_office_agent' => 'Back Office',
                                    default => ucfirst($u['role'])
                                };
                                echo $roleDisplay;
                                ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap" x-show="!editing">
                            <?php if ($u['is_active']): ?>
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">Active</span>
                            <?php else: ?>
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-show="!editing">
                            <?= $u['last_login_at'] ? date('M j, Y', strtotime($u['last_login_at'])) : 'Never' ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm" x-show="!editing">
                            <?php if ($u['id'] != $user['id']): ?>
                            <button @click="editing = true" class="text-indigo-600 hover:text-indigo-900">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
