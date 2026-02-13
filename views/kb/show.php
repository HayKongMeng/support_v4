<?php
$pageTitle = htmlspecialchars($article['title']) . ' - Knowledge Base';
ob_start();
?>

<!-- Breadcrumb -->
<nav class="mb-6">
    <ol class="flex items-center space-x-2 text-sm text-gray-500">
        <li><a href="<?= $app->url('kb') ?>" class="hover:text-indigo-600">Knowledge Base</a></li>
        <li><i class="fas fa-chevron-right text-xs mx-2"></i></li>
        <?php if ($article['category_name']): ?>
        <li>
            <a href="<?= $app->url('kb') ?>?category=<?= $article['category_id'] ?>" class="hover:text-indigo-600">
                <?= htmlspecialchars($article['category_name']) ?>
            </a>
        </li>
        <li><i class="fas fa-chevron-right text-xs mx-2"></i></li>
        <?php endif; ?>
        <li class="text-gray-900"><?= htmlspecialchars($article['title']) ?></li>
    </ol>
</nav>

<div class="flex flex-col lg:flex-row gap-6">
    <!-- Main Content -->
    <div class="flex-1">
        <article class="bg-white rounded-lg shadow">
            <!-- Article Header -->
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center space-x-3">
                        <?php if ($article['category_name']): ?>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                              style="background-color: <?= $article['category_color'] ?>20; color: <?= $article['category_color'] ?>">
                            <?= htmlspecialchars($article['category_name']) ?>
                        </span>
                        <?php endif; ?>
                        <?php if ($article['is_featured']): ?>
                        <span class="inline-flex items-center px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-medium">
                            <i class="fas fa-star mr-1"></i> Featured
                        </span>
                        <?php endif; ?>
                        <?php if (!$article['is_published']): ?>
                        <span class="inline-flex items-center px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs font-medium">
                            <i class="fas fa-eye-slash mr-1"></i> Draft
                        </span>
                        <?php endif; ?>
                    </div>

                    <div class="flex items-center space-x-2">
                        <button type="button" onclick="copyKbText('kb-copy-text')"
                                class="p-2 text-gray-500 hover:text-indigo-600 hover:bg-gray-100 rounded-lg"
                                title="Copy full text">
                            <i class="fas fa-copy"></i>
                        </button>
                        <?php if ($article['author_id'] === $auth->id() || $auth->isAgent()): ?>
                        <a href="<?= $app->url("kb/{$article['id']}/edit") ?>"
                           class="p-2 text-gray-500 hover:text-indigo-600 hover:bg-gray-100 rounded-lg">
                            <i class="fas fa-edit"></i>
                        </a>
                        <button onclick="deleteArticle(<?= $article['id'] ?>)"
                                class="p-2 text-gray-500 hover:text-red-600 hover:bg-gray-100 rounded-lg">
                            <i class="fas fa-trash"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <h1 class="text-2xl font-bold text-gray-900 mb-4"><?= htmlspecialchars($article['title']) ?></h1>

                <div class="flex items-center text-sm text-gray-500 space-x-4">
                    <span>
                        <i class="fas fa-user mr-1"></i> <?= htmlspecialchars($article['author_name']) ?>
                    </span>
                    <span>
                        <i class="fas fa-calendar mr-1"></i> <?= date('M j, Y', strtotime($article['created_at'])) ?>
                    </span>
                    <span>
                        <i class="fas fa-eye mr-1"></i> <?= number_format($article['views']) ?> views
                    </span>
                </div>
            </div>

            <!-- Article Content -->
            <div class="p-6">
                <div class="kb-content prose prose-lg max-w-none">
                    <?= $article['content'] ?>
                </div>
                <?php
                $copyContent = $article['content'] ?? '';
                $copyContent = preg_replace('/<br\s*\/?>/i', "\n", $copyContent);
                $copyContent = preg_replace('/<\/p>/i', "\n\n", $copyContent);
                $copyContent = html_entity_decode(strip_tags($copyContent), ENT_QUOTES | ENT_HTML5);
                ?>
                <textarea id="kb-copy-text" class="sr-only" aria-hidden="true"><?= htmlspecialchars($article['title'] . "\n\n" . trim($copyContent)) ?></textarea>
            </div>

            <!-- Tags -->
            <?php
            $tags = json_decode($article['tags'] ?? '[]', true);
            if (!empty($tags)):
            ?>
            <div class="px-6 pb-6">
                <div class="flex items-center flex-wrap gap-2">
                    <span class="text-sm text-gray-500"><i class="fas fa-tags mr-1"></i></span>
                    <?php foreach ($tags as $tag): ?>
                    <span class="inline-flex items-center px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm">
                        <?= htmlspecialchars($tag) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </article>

        <!-- Related Articles -->
        <?php if (!empty($related)): ?>
        <div class="mt-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Related Articles</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php foreach ($related as $rel): ?>
                <a href="<?= $app->url("kb/{$rel['slug']}") ?>" class="bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow">
                    <h3 class="font-medium text-gray-900 hover:text-indigo-600 mb-2">
                        <?= htmlspecialchars($rel['title']) ?>
                    </h3>
                    <p class="text-sm text-gray-600 line-clamp-2"><?= htmlspecialchars($rel['excerpt'] ?? '') ?></p>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="w-full lg:w-64 space-y-4">
        <!-- Actions -->
        <div class="bg-white rounded-lg shadow p-4">
            <a href="<?= $app->url('kb') ?>" class="flex items-center text-gray-700 hover:text-indigo-600 mb-3">
                <i class="fas fa-arrow-left mr-2"></i> Back to Articles
            </a>
            <a href="<?= $app->url('kb/create') ?>" class="flex items-center text-gray-700 hover:text-indigo-600">
                <i class="fas fa-plus mr-2"></i> Write an Article
            </a>
        </div>

        <!-- Article Info -->
        <div class="bg-white rounded-lg shadow p-4">
            <h3 class="font-semibold text-gray-900 mb-3">Article Info</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">Author</span>
                    <span class="text-gray-900"><?= htmlspecialchars($article['author_name']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Created</span>
                    <span class="text-gray-900"><?= date('M j, Y', strtotime($article['created_at'])) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Updated</span>
                    <span class="text-gray-900"><?= date('M j, Y', strtotime($article['updated_at'])) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Views</span>
                    <span class="text-gray-900"><?= number_format($article['views']) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

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
            window.location.href = '<?= $app->url('kb') ?>';
        } else {
            alert(data.error || 'Failed to delete article');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to delete article');
    });
}

function copyKbText(elementId) {
    const el = document.getElementById(elementId);
    if (!el) return;

    const text = el.value || '';
    if (!text.trim()) {
        alert('Nothing to copy');
        return;
    }

    navigator.clipboard.writeText(text)
        .then(() => alert('Copied'))
        .catch(() => {
            el.select();
            document.execCommand('copy');
            alert('Copied');
        });
}
</script>

<style>
/* KB Content Styling */
.kb-content {
    font-size: 16px;
    line-height: 1.75;
    color: #374151;
}
.kb-content h1 {
    font-size: 2em;
    font-weight: 700;
    margin-top: 1.5em;
    margin-bottom: 0.5em;
    color: #111827;
}
.kb-content h2 {
    font-size: 1.5em;
    font-weight: 600;
    margin-top: 1.5em;
    margin-bottom: 0.5em;
    color: #1f2937;
}
.kb-content h3 {
    font-size: 1.25em;
    font-weight: 600;
    margin-top: 1.25em;
    margin-bottom: 0.5em;
    color: #1f2937;
}
.kb-content p {
    margin-bottom: 1em;
}
.kb-content ul, .kb-content ol {
    margin-left: 1.5em;
    margin-bottom: 1em;
}
.kb-content ul {
    list-style-type: disc;
}
.kb-content ol {
    list-style-type: decimal;
}
.kb-content li {
    margin-bottom: 0.25em;
}
.kb-content blockquote {
    border-left: 4px solid #6366f1;
    padding-left: 1em;
    margin: 1em 0;
    color: #6b7280;
    font-style: italic;
}
.kb-content pre {
    background: #1f2937;
    color: #e5e7eb;
    padding: 1em;
    border-radius: 0.5em;
    overflow-x: auto;
    margin: 1em 0;
}
.kb-content code {
    background: #f3f4f6;
    padding: 0.2em 0.4em;
    border-radius: 0.25em;
    font-size: 0.9em;
}
.kb-content pre code {
    background: transparent;
    padding: 0;
}
.kb-content a {
    color: #4f46e5;
    text-decoration: underline;
}
.kb-content a:hover {
    color: #4338ca;
}
.kb-content img {
    max-width: 100%;
    height: auto;
    border-radius: 0.5em;
    margin: 1em 0;
}
.kb-content table {
    width: 100%;
    border-collapse: collapse;
    margin: 1em 0;
}
.kb-content th, .kb-content td {
    border: 1px solid #e5e7eb;
    padding: 0.5em;
    text-align: left;
}
.kb-content th {
    background: #f9fafb;
    font-weight: 600;
}
.kb-content strong {
    font-weight: 600;
}
.kb-content em {
    font-style: italic;
}
.kb-content u {
    text-decoration: underline;
}
.kb-content s {
    text-decoration: line-through;
}
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
