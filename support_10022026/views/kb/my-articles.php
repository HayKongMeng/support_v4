<?php
$pageTitle = 'My Articles - Knowledge Base';
ob_start();
?>

<!-- Header -->
<div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-gray-800">My Articles</h2>
        <p class="text-sm text-gray-500">Articles you've written</p>
    </div>
    <div class="mt-4 md:mt-0 flex items-center space-x-3">
        <a href="<?= $app->url('kb') ?>" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
            <i class="fas fa-arrow-left mr-2"></i> Back
        </a>
        <a href="<?= $app->url('kb/create') ?>" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
            <i class="fas fa-plus mr-2"></i> New Article
        </a>
    </div>
</div>

<?php if (empty($articles)): ?>
<div class="bg-white rounded-lg shadow p-8 text-center">
    <i class="fas fa-pen-fancy text-gray-400 text-5xl mb-4"></i>
    <h3 class="text-lg font-medium text-gray-900 mb-2">No articles yet</h3>
    <p class="text-gray-600 mb-4">Start writing and share your knowledge!</p>
    <a href="<?= $app->url('kb/create') ?>" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
        <i class="fas fa-plus mr-2"></i> Write Your First Article
    </a>
</div>
<?php else: ?>
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Views</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
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
                        <i class="fas fa-check mr-1"></i> Published
                    </span>
                    <?php else: ?>
                    <span class="inline-flex items-center px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs font-medium">
                        <i class="fas fa-eye-slash mr-1"></i> Draft
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
    if (!confirm('Are you sure you want to delete this article?')) return;

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
            alert(data.error || 'Failed to delete article');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to delete article');
    });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
