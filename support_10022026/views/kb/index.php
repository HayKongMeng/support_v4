<?php
$pageTitle = 'Knowledge Base';
ob_start();
?>

<div class="flex flex-col lg:flex-row gap-6">
    <!-- Main Content -->
    <div class="flex-1">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">Knowledge Base</h2>
                <p class="text-sm text-gray-500">Find answers and share knowledge</p>
            </div>
            <a href="<?= $app->url('kb/create') ?>" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                <i class="fas fa-plus mr-2"></i> New Article
            </a>
        </div>

        <!-- Search and Filter -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <form method="GET" action="<?= $app->url('kb') ?>" class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <div class="relative">
                        <input type="text" name="search" value="<?= htmlspecialchars($search ?? '') ?>"
                            placeholder="Search articles..."
                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                    </div>
                </div>
                <div class="w-full md:w-48">
                    <select name="category" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($categoryId ?? '') == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="px-6 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900">
                    Search
                </button>
            </form>
        </div>

        <?php if (!empty($featured) && empty($search) && empty($categoryId)): ?>
        <!-- Featured Articles -->
        <div class="mb-8">
            <h3 class="text-md font-semibold text-gray-900 mb-4"><i class="fas fa-star text-yellow-500 mr-2"></i>Featured Articles</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php foreach ($featured as $article): ?>
                <a href="<?= $app->url("kb/{$article['slug']}") ?>" class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg p-4 text-white hover:shadow-lg transition-shadow">
                    <h4 class="font-semibold mb-2"><?= htmlspecialchars($article['title']) ?></h4>
                    <p class="text-sm text-indigo-100 line-clamp-2"><?= htmlspecialchars($article['excerpt'] ?? '') ?></p>
                    <div class="mt-3 text-xs text-indigo-200">
                        <i class="fas fa-eye mr-1"></i> <?= number_format($article['views']) ?> views
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Articles Grid -->
        <?php if (empty($articles)): ?>
        <div class="bg-white rounded-lg shadow p-8 text-center">
            <i class="fas fa-book-open text-gray-400 text-5xl mb-4"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No articles found</h3>
            <p class="text-gray-600 mb-4">Be the first to share knowledge!</p>
            <a href="<?= $app->url('kb/create') ?>" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                <i class="fas fa-plus mr-2"></i> Write an Article
            </a>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($articles as $article): ?>
            <div class="bg-white rounded-lg shadow hover:shadow-md transition-shadow">
                <div class="p-5">
                    <div class="flex items-start justify-between mb-3">
                        <?php if ($article['category_name']): ?>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" style="background-color: <?= $article['category_color'] ?>20; color: <?= $article['category_color'] ?>">
                            <?= htmlspecialchars($article['category_name']) ?>
                        </span>
                        <?php else: ?>
                        <span></span>
                        <?php endif; ?>
                        <?php if ($article['is_featured']): ?>
                        <i class="fas fa-star text-yellow-500"></i>
                        <?php endif; ?>
                    </div>
                    <a href="<?= $app->url("kb/{$article['slug']}") ?>" class="block">
                        <h3 class="text-lg font-semibold text-gray-900 hover:text-indigo-600 mb-2">
                            <?= htmlspecialchars($article['title']) ?>
                        </h3>
                        <p class="text-gray-600 text-sm line-clamp-2 mb-4">
                            <?= htmlspecialchars($article['excerpt'] ?? '') ?>
                        </p>
                    </a>
                    <div class="flex items-center justify-between text-sm text-gray-500">
                        <span>
                            <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($article['author_name']) ?>
                        </span>
                        <span>
                            <i class="fas fa-eye mr-1"></i> <?= number_format($article['views']) ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($pagination['total_pages'] > 1): ?>
        <div class="flex justify-center mt-6">
            <nav class="flex items-center space-x-2">
                <?php if ($pagination['current_page'] > 1): ?>
                <a href="?page=<?= $pagination['current_page'] - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $categoryId ? '&category=' . $categoryId : '' ?>"
                   class="px-3 py-2 bg-white border rounded-lg hover:bg-gray-50">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php endif; ?>

                <span class="px-4 py-2 text-gray-700">
                    Page <?= $pagination['current_page'] ?> of <?= $pagination['total_pages'] ?>
                </span>

                <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                <a href="?page=<?= $pagination['current_page'] + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $categoryId ? '&category=' . $categoryId : '' ?>"
                   class="px-3 py-2 bg-white border rounded-lg hover:bg-gray-50">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="w-full lg:w-64 space-y-4">
        <!-- My Articles -->
        <div class="bg-white rounded-lg shadow p-4">
            <h3 class="font-semibold text-gray-900 mb-3"><i class="fas fa-pen mr-2"></i>My Articles</h3>
            <a href="<?= $app->url('kb/my-articles') ?>" class="text-indigo-600 hover:text-indigo-800 text-sm">
                View my articles <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>

        <!-- Popular Articles -->
        <?php if (!empty($popular)): ?>
        <div class="bg-white rounded-lg shadow p-4">
            <h3 class="font-semibold text-gray-900 mb-3"><i class="fas fa-fire text-orange-500 mr-2"></i>Popular</h3>
            <div class="space-y-3">
                <?php foreach ($popular as $article): ?>
                <a href="<?= $app->url("kb/{$article['slug']}") ?>" class="block text-sm text-gray-700 hover:text-indigo-600">
                    <?= htmlspecialchars($article['title']) ?>
                    <span class="text-gray-400 ml-1">(<?= number_format($article['views']) ?>)</span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Categories -->
        <div class="bg-white rounded-lg shadow p-4">
            <h3 class="font-semibold text-gray-900 mb-3"><i class="fas fa-tags mr-2"></i>Categories</h3>
            <div class="space-y-2">
                <?php foreach ($categories as $cat): ?>
                <a href="<?= $app->url('kb') ?>?category=<?= $cat['id'] ?>"
                   class="flex items-center text-sm text-gray-700 hover:text-indigo-600 <?= ($categoryId ?? '') == $cat['id'] ? 'font-semibold text-indigo-600' : '' ?>">
                    <span class="w-3 h-3 rounded-full mr-2" style="background-color: <?= $cat['color'] ?>"></span>
                    <?= htmlspecialchars($cat['name']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
