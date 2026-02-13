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
                <span class="ml-2">Agent (Assigned tickets only)</span> |
                <span class="ml-2">Front/Back Office (Assigned tickets only)</span>
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
                        <option value="agent">Agent</option>
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
                                    'front_office_agent' => 'Front Office',
                                    'back_office_agent' => 'Back Office',
                                    default => ucfirst($u['role'])
                                };
                                echo $roleDisplay;
                                ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if ($u['is_active']): ?>
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">Active</span>
                            <?php else: ?>
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= $u['last_login_at'] ? date('M j, Y', strtotime($u['last_login_at'])) : 'Never' ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                            <div class="flex items-center justify-end gap-3">
                                <button @click="editing = !editing" class="text-indigo-600 hover:text-indigo-900" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($u['id'] != $user['id']): ?>
                                <form action="<?= $app->url('settings/users/' . $u['id']) ?>" method="POST"
                                      onsubmit="return confirm('Delete this user?')">
                                    <input type="hidden" name="_method" value="DELETE">
                                    <button type="submit" class="text-red-600 hover:text-red-700" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <tr x-show="editing" x-cloak class="bg-gray-50">
                        <td colspan="6" class="px-6 py-4">
                            <form action="<?= $app->url('settings/users/' . $u['id']) ?>" method="POST"
                                  class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
                                <div class="md:col-span-2">
                                    <label class="block text-xs text-gray-500 mb-1">Name</label>
                                    <input type="text" name="name" value="<?= htmlspecialchars($u['name']) ?>" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs text-gray-500 mb-1">Email</label>
                                    <input type="email" name="email" value="<?= htmlspecialchars($u['email']) ?>" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Role</label>
                                    <select name="role" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                        <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                        <option value="agent" <?= $u['role'] === 'agent' ? 'selected' : '' ?>>Agent</option>
                                        <option value="front_office_agent" <?= $u['role'] === 'front_office_agent' ? 'selected' : '' ?>>Front Office Agent</option>
                                        <option value="back_office_agent" <?= $u['role'] === 'back_office_agent' ? 'selected' : '' ?>>Back Office Agent</option>
                                        <option value="customer" <?= $u['role'] === 'customer' ? 'selected' : '' ?>>Customer</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Status</label>
                                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="is_active" value="1" <?= $u['is_active'] ? 'checked' : '' ?>>
                                        Active
                                    </label>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs text-gray-500 mb-1">Reset Password</label>
                                    <input type="password" name="password" placeholder="New password"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                </div>
                                <div class="md:col-span-4"></div>
                                <div class="md:col-span-2 flex gap-2 justify-end">
                                    <button type="button" @click="editing = false"
                                            class="px-4 py-2 text-sm border border-gray-300 rounded-lg">Cancel</button>
                                    <button type="submit"
                                            class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg">Save</button>
                                </div>
                            </form>
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
