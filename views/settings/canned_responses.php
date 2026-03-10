<?php
$pageTitle = __('v_canned_responses');
ob_start();
?>

<div class="space-y-6">
    <!-- Add Response Form -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800"><?= __('v_add_canned_response') ?></h2>
            <p class="text-sm text-gray-500 mt-1"><?= __('v_create_pre_written_responses_for_common_questions') ?></p>
        </div>
        <form action="<?= $app->url('settings/canned-responses') ?>" method="POST" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2"><?= __('v_title') ?> *</label>
                    <input type="text" name="title" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                           placeholder="<?= __('v_response_title') ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2"><?= __('v_shortcut') ?></label>
                    <input type="text" name="shortcut"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                           placeholder="#thanks">
                    <p class="text-xs text-gray-500 mt-1"><?= __('v_quick_access_code_e_g_thanks') ?></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2"><?= __('v_category') ?></label>
                    <select name="category_id"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <option value=""><?= __('v_all_categories_2') ?></option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2"><?= __('v_content') ?> *</label>
                <textarea name="content" rows="4" required
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                          placeholder="<?= __('v_type_your_canned_response') ?>"></textarea>
            </div>
            <div class="flex justify-end">
                <button type="submit"
                        class="px-6 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700">
                    <?= __('v_add_response') ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Responses List -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800"><?= __('v_saved_responses') ?></h2>
        </div>
        <div class="divide-y divide-gray-200">
            <?php foreach ($responses as $response): ?>
            <div class="p-4 hover:bg-gray-50">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <h3 class="font-medium text-gray-900"><?= htmlspecialchars($response['title']) ?></h3>
                            <?php if ($response['shortcut']): ?>
                            <code class="text-xs px-2 py-0.5 bg-gray-100 text-gray-600 rounded">
                                <?= htmlspecialchars($response['shortcut']) ?>
                            </code>
                            <?php endif; ?>
                            <?php if ($response['category_name']): ?>
                            <span class="text-xs px-2 py-0.5 bg-indigo-100 text-indigo-800 rounded-full">
                                <?= htmlspecialchars($response['category_name']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <p class="text-sm text-gray-600 whitespace-pre-line">
                            <?= htmlspecialchars(substr($response['content'], 0, 200)) ?>
                            <?= strlen($response['content']) > 200 ? '...' : '' ?>
                        </p>
                        <p class="text-xs text-gray-400 mt-2">
                            <?= __('v_used') ?> <?= $response['usage_count'] ?> <?= __('v_times') ?>
                        </p>
                    </div>
                    <form action="<?= $app->url("settings/canned-responses/{$response['id']}") ?>" method="POST"
                          onsubmit="return confirm('<?= __('v_delete_this_response') ?>')">
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="text-red-500 hover:text-red-700 ml-4">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($responses)): ?>
            <div class="p-8 text-center text-gray-500">
                <?= __('v_no_canned_responses_yet_add_one_above') ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
