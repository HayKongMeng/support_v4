<?php
$pageTitle = __('v_my_articles_knowledge_base');
ob_start();
?>

<!-- Header -->
<div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-gray-800"><?= __('v_my_articles') ?></h2>
        <p class="text-sm text-gray-500"><?= __('v_articles_you_ve_written') ?></p>
    </div>
    <div class="mt-4 md:mt-0 flex items-center space-x-3">
        <a href="<?= $app->url('kb') ?>" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
            <i class="fas fa-arrow-left mr-2"></i> <?= __('v_back') ?>
        </a>
        <a href="<?= $app->url('kb/create') ?>" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
            <i class="fas fa-plus mr-2"></i> <?= __('v_new_article') ?>
        </a>
    </div>
</div>

<?php if (empty($articles)): ?>
<div class="bg-white rounded-lg shadow p-8 text-center">
    <i class="fas fa-pen-fancy text-gray-400 text-5xl mb-4"></i>
    <h3 class="text-lg font-medium text-gray-900 mb-2"><?= __('v_no_articles_yet') ?></h3>
    <p class="text-gray-600 mb-4"><?= __('v_start_writing_and_share_your_knowledge') ?></p>
    <a href="<?= $app->url('kb/create') ?>" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
        <i class="fas fa-plus mr-2"></i> <?= __('v_write_your_first_article') ?>
    </a>
</div>
<?php else: ?>
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?= __('v_title') ?></th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?= __('v_category') ?></th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?= __('v_status') ?></th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?= __('v_views_2') ?></th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?= __('v_created') ?></th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider"><?= __('v_actions') ?></th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php foreach ($articles as $article): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <a href="<?= $app->url("kb/{$article['slug']}") ?>" class="text-gray-900 hover:text-indigo-600 font-medium">
                        <?= htmlspecialchars($article['title']) ?>
                    </a>
                    <?php if ($article['is_featured']): ?>
                    <i class="fas fa-star text-yellow-500 ml-2"></i>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4">
                    <?php if ($article['category_name']): ?>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                          style="background-color: <?= $article['category_color'] ?>20; color: <?= $article['category_color'] ?>">
                        <?= htmlspecialchars($article['category_name']) ?>
                    </span>
                    <?php else: ?>
                    <span class="text-gray-400">-</span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4">
                    <?php if ($article['is_published']): ?>
                    <span class="inline-flex items-center px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">
                        <i class="fas fa-check mr-1"></i> <?= __('v_published') ?>
                    </span>
                    <?php else: ?>
                    <span class="inline-flex items-center px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs font-medium">
                        <i class="fas fa-eye-slash mr-1"></i> <?= __('v_draft') ?>
                    </span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-gray-500">
                    <?= number_format($article['views']) ?>
                </td>
                <td class="px-6 py-4 text-gray-500">
                    <?= date('M j, Y', strtotime($article['created_at'])) ?>
                </td>
                <td class="px-6 py-4 text-right">
                    <a href="<?= $app->url("kb/{$article['id']}/edit") ?>"
                       class="text-indigo-600 hover:text-indigo-900 mr-3">
                        <i class="fas fa-edit"></i>
                    </a>
                    <button onclick="deleteArticle(<?= $article['id'] ?>)"
                            class="text-red-600 hover:text-red-900">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<script>
function deleteArticle(id) {
    if (!confirm(<?= json_encode(__('v_are_you_sure_you_want_to_delete_this_article')) ?>)) return;

    fetch('<?= $app->url('kb') ?>/' + id, {
        method: 'DELETE',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.error || <?= json_encode(__('v_failed_to_delete_article')) ?>);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert(<?= json_encode(__('v_failed_to_delete_article')) ?>);
    });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';

