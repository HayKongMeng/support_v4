<?php
$pageTitle = 'Categories';
ob_start();
?>

<div class="space-y-6">
    <!-- Add Category Form -->
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Add New Category</h2>
        </div>
        <form action="<?= $app->url('settings/categories') ?>" method="POST" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Name *</label>
                    <input type="text" name="name" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                           placeholder="Category name">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Color</label>
                    <input type="color" name="color" value="#6366f1"
                           class="w-full h-10 border border-gray-300 rounded-lg cursor-pointer">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Auto-assign to</label>
                    <select name="auto_assign_to"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <option value="">No auto-assignment</option>
                        <?php
                        $agents = $app->db()->select("SELECT id, name FROM users WHERE company_id = ? AND role IN ('admin', 'agent') AND is_active = 1", [$auth->companyId()]);
                        foreach ($agents as $agent):
                        ?>
                        <option value="<?= $agent['id'] ?>"><?= htmlspecialchars($agent['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <input type="text" name="description"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                           placeholder="Brief description">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">AI Keywords</label>
                    <input type="text" name="keywords"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                           placeholder="keyword1, keyword2, keyword3">
                    <p class="text-xs text-gray-500 mt-1">Comma-separated keywords for AI categorization</p>
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit"
                        class="px-6 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700">
                    Add Category
                </button>
            </div>
        </form>
    </div>

    <!-- Categories List -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Categories</h2>
        </div>
        <div class="divide-y divide-gray-200">
            <?php foreach ($categories as $cat): ?>
            <div class="p-4 hover:bg-gray-50">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-4 h-4 rounded-full mr-3" style="background-color: <?= $cat['color'] ?>"></div>
                        <div>
                            <h3 class="font-medium text-gray-900"><?= htmlspecialchars($cat['name']) ?></h3>
                            <?php if ($cat['description']): ?>
                            <p class="text-sm text-gray-500"><?= htmlspecialchars($cat['description']) ?></p>
                            <?php endif; ?>
                            <?php if ($cat['keywords']): ?>
                            <p class="text-xs text-gray-400 mt-1">
                                Keywords: <?= htmlspecialchars(implode(', ', json_decode($cat['keywords'], true) ?? [])) ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <?php
                        $catStat = array_filter($categoryStats, fn($s) => $s['id'] == $cat['id']);
                        $catStat = reset($catStat);
                        ?>
                        <span class="text-sm text-gray-500">
                            <?= $catStat['open_count'] ?? 0 ?> open tickets
                        </span>
                        <?php if ($cat['auto_assign_name']): ?>
                        <span class="text-xs px-2 py-1 bg-blue-100 text-blue-800 rounded-full">
                            Auto: <?= htmlspecialchars($cat['auto_assign_name']) ?>
                        </span>
                        <?php endif; ?>
                        <form action="<?= $app->url("settings/categories/{$cat['id']}") ?>" method="POST" class="inline"
                              onsubmit="return confirm('Delete this category?')">
                            <input type="hidden" name="_method" value="DELETE">
                            <button type="submit" class="text-red-500 hover:text-red-700">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($categories)): ?>
            <div class="p-8 text-center text-gray-500">
                No categories yet. Add one above.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
