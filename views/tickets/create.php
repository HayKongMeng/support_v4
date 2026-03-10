<?php
$pageTitle = __('create_ticket');
$validationErrors = $_SESSION['validation_errors'] ?? [];
unset($_SESSION['validation_errors']);
ob_start();
?>

<div class="max-w-3xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800"><?= __('create_new_ticket') ?></h2>
            <p class="text-sm text-gray-500 mt-1"><?= __('fill_ticket_details') ?></p>
        </div>

        <form action="<?= $app->url('tickets') ?>" method="POST" class="p-6 space-y-6">
            <!-- Requester Info -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="requester_name" class="block text-sm font-medium text-gray-700 mb-2"><?= __('customer_name') ?> *</label>
                    <input type="text" id="requester_name" name="requester_name"
                           value="<?= htmlspecialchars($oldInput['requester_name'] ?? '') ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 <?= isset($validationErrors['requester_name']) ? 'border-red-500' : '' ?>"
                           required>
                    <?php if (isset($validationErrors['requester_name'])): ?>
                    <p class="text-red-500 text-xs mt-1"><?= $validationErrors['requester_name'] ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="requester_email" class="block text-sm font-medium text-gray-700 mb-2"><?= __('customer_email') ?> *</label>
                    <input type="email" id="requester_email" name="requester_email"
                           value="<?= htmlspecialchars($oldInput['requester_email'] ?? '') ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 <?= isset($validationErrors['requester_email']) ? 'border-red-500' : '' ?>"
                           required>
                    <?php if (isset($validationErrors['requester_email'])): ?>
                    <p class="text-red-500 text-xs mt-1"><?= $validationErrors['requester_email'] ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Subject -->
            <div>
                <label for="subject" class="block text-sm font-medium text-gray-700 mb-2"><?= __('subject') ?> *</label>
                <input type="text" id="subject" name="subject"
                       value="<?= htmlspecialchars($oldInput['subject'] ?? '') ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 <?= isset($validationErrors['subject']) ? 'border-red-500' : '' ?>"
                       placeholder="<?= __('brief_summary_issue') ?>"
                       required>
                <?php if (isset($validationErrors['subject'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= $validationErrors['subject'] ?></p>
                <?php endif; ?>
            </div>

            <!-- Description -->
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-2"><?= __('description') ?> *</label>
                <textarea id="description" name="description" rows="6"
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 <?= isset($validationErrors['description']) ? 'border-red-500' : '' ?>"
                          placeholder="<?= __('provide_detail_issue') ?>"
                          required><?= htmlspecialchars($oldInput['description'] ?? '') ?></textarea>
                <?php if (isset($validationErrors['description'])): ?>
                <p class="text-red-500 text-xs mt-1"><?= $validationErrors['description'] ?></p>
                <?php endif; ?>
            </div>

            <!-- Category, Priority, Assignment -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label for="category_id" class="block text-sm font-medium text-gray-700 mb-2"><?= __('category') ?></label>
                    <select id="category_id" name="category_id"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <option value=""><?= __('auto_detect_ai') ?></option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($oldInput['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1"><?= __('leave_empty_ai_category') ?></p>
                </div>
                <div>
                    <label for="priority" class="block text-sm font-medium text-gray-700 mb-2"><?= __('priority') ?></label>
                    <select id="priority" name="priority"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <option value=""><?= __('auto_detect_ai') ?></option>
                        <option value="low" <?= ($oldInput['priority'] ?? '') === 'low' ? 'selected' : '' ?>><?= __('low') ?></option>
                        <option value="medium" <?= ($oldInput['priority'] ?? '') === 'medium' ? 'selected' : '' ?>><?= __('medium') ?></option>
                        <option value="high" <?= ($oldInput['priority'] ?? '') === 'high' ? 'selected' : '' ?>><?= __('high') ?></option>
                        <option value="urgent" <?= ($oldInput['priority'] ?? '') === 'urgent' ? 'selected' : '' ?>><?= __('urgent') ?></option>
                    </select>
                </div>
                <div>
                    <label for="assigned_to" class="block text-sm font-medium text-gray-700 mb-2"><?= __('assign_to') ?></label>
                    <select id="assigned_to" name="assigned_to"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <option value=""><?= __('unassigned') ?></option>
                        <?php foreach ($agents as $agent): ?>
                        <option value="<?= $agent['id'] ?>" <?= ($oldInput['assigned_to'] ?? '') == $agent['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($agent['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- AI Notice -->
            <div class="p-4 bg-indigo-50 rounded-lg">
                <div class="flex items-start">
                    <i class="fas fa-robot text-indigo-600 mt-0.5 mr-3"></i>
                    <div>
                        <h4 class="text-sm font-medium text-indigo-900"><?= __('ai_powered_categorization') ?></h4>
                        <p class="text-sm text-indigo-700 mt-1">
                            <?= __('ai_categorization_desc') ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Submit -->
            <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200">
                <a href="<?= $app->url('tickets') ?>" class="px-4 py-2 text-gray-600 hover:text-gray-800"><?= __('cancel') ?></a>
                <button type="submit"
                        class="px-6 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700">
                    <?= __('create_ticket') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
