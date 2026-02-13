<?php
$pageTitle = 'Edit Article - Knowledge Base';
ob_start();
$tags = json_decode($article['tags'] ?? '[]', true);
$tagsString = is_array($tags) ? implode(', ', $tags) : '';
?>

<!-- Quill Editor CSS -->
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">

<div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">Edit Article</h2>
            <p class="text-sm text-gray-500">Update your article</p>
        </div>
        <a href="<?= $app->url("kb/{$article['slug']}") ?>" class="text-gray-600 hover:text-gray-900">
            <i class="fas fa-times text-xl"></i>
        </a>
    </div>

    <form action="<?= $app->url("kb/{$article['id']}") ?>" method="POST" class="bg-white rounded-lg shadow" id="articleForm">
        <div class="p-6 space-y-6">
            <!-- Title -->
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                <input type="text" id="title" name="title" required
                    value="<?= htmlspecialchars($article['title']) ?>"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-lg"
                    placeholder="Enter article title...">
            </div>

            <!-- Category -->
            <div>
                <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select id="category_id" name="category_id"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    <option value="">Select a category (optional)</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $article['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Content with Quill Editor -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Content *</label>
                <div id="editor" class="bg-white" style="height: 400px;"></div>
                <input type="hidden" name="content" id="content">
                <p class="mt-1 text-sm text-gray-500">Use the toolbar to format your content.</p>
            </div>

            <!-- Tags -->
            <div>
                <label for="tags" class="block text-sm font-medium text-gray-700 mb-1">Tags</label>
                <input type="text" id="tags" name="tags"
                    value="<?= htmlspecialchars($tagsString) ?>"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                    placeholder="tag1, tag2, tag3 (comma separated)">
            </div>

            <!-- Options -->
            <div class="flex items-center space-x-6">
                <label class="flex items-center">
                    <input type="checkbox" name="is_published" value="1" <?= $article['is_published'] ? 'checked' : '' ?>
                        class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                    <span class="ml-2 text-sm text-gray-700">Published</span>
                </label>

                <?php if ($auth->isAgent()): ?>
                <label class="flex items-center">
                    <input type="checkbox" name="is_featured" value="1" <?= $article['is_featured'] ? 'checked' : '' ?>
                        class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                    <span class="ml-2 text-sm text-gray-700">Featured article</span>
                </label>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 rounded-b-lg flex items-center justify-between">
            <a href="<?= $app->url("kb/{$article['slug']}") ?>" class="text-gray-600 hover:text-gray-900">Cancel</a>
            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                <i class="fas fa-save mr-2"></i> Update Article
            </button>
        </div>
    </form>
</div>

<!-- Quill Editor JS -->
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
    // Initialize Quill editor
    var quill = new Quill('#editor', {
        theme: 'snow',
        placeholder: 'Write your article content here...',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'color': [] }, { 'background': [] }],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                [{ 'indent': '-1'}, { 'indent': '+1' }],
                ['blockquote', 'code-block'],
                ['link', 'image'],
                [{ 'align': [] }],
                ['clean']
            ]
        }
    });

    // Load existing content
    quill.root.innerHTML = <?= json_encode($article['content']) ?>;

    // Before form submit, copy editor content to hidden input
    document.getElementById('articleForm').addEventListener('submit', function(e) {
        var content = quill.root.innerHTML;

        // Check if content is empty
        if (content === '<p><br></p>' || content.trim() === '') {
            e.preventDefault();
            alert('Please enter article content');
            return false;
        }

        document.getElementById('content').value = content;
    });
</script>

<style>
    .ql-editor {
        font-size: 16px;
        line-height: 1.6;
    }
    .ql-container {
        border-bottom-left-radius: 0.5rem;
        border-bottom-right-radius: 0.5rem;
    }
    .ql-toolbar {
        border-top-left-radius: 0.5rem;
        border-top-right-radius: 0.5rem;
        background: #f9fafb;
    }
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
