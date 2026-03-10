<?php
$pageTitle = __('v_write_article_knowledge_base');
ob_start();
?>

<!-- Quill Editor CSS -->
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">

<div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-800"><?= __('v_write_article') ?></h2>
            <p class="text-sm text-gray-500"><?= __('v_share_your_knowledge_with_the_team') ?></p>
        </div>
        <a href="<?= $app->url('kb') ?>" class="text-gray-600 hover:text-gray-900">
            <i class="fas fa-times text-xl"></i>
        </a>
    </div>

    <form action="<?= $app->url('kb') ?>" method="POST" class="bg-white rounded-lg shadow" id="articleForm">
        <div class="p-6 space-y-6">
            <!-- Title -->
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 mb-1"><?= __('v_title') ?> *</label>
                <input type="text" id="title" name="title" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent text-lg"
                    placeholder="<?= __('v_enter_article_title') ?>">
            </div>

            <!-- Category -->
            <div>
                <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1"><?= __('v_category') ?></label>
                <select id="category_id" name="category_id"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    <option value=""><?= __('v_select_a_category_optional') ?></option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Content with Quill Editor -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1"><?= __('v_content') ?> *</label>
                <div id="editor" class="bg-white" style="height: 400px;"></div>
                <input type="hidden" name="content" id="content">
                <p class="mt-1 text-sm text-gray-500"><?= __('v_use_the_toolbar_to_format_your_content') ?></p>
            </div>

            <!-- Tags -->
            <div>
                <label for="tags" class="block text-sm font-medium text-gray-700 mb-1"><?= __('v_tags') ?></label>
                <input type="text" id="tags" name="tags"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                    placeholder="<?= __('v_tag1_tag2_tag3_comma_separated') ?>">
            </div>

            <!-- Options -->
            <div class="flex items-center space-x-6">
                <label class="flex items-center">
                    <input type="checkbox" name="is_published" value="1" checked
                        class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                    <span class="ml-2 text-sm text-gray-700"><?= __('v_publish_immediately') ?></span>
                </label>

                <?php if ($auth->isAgent()): ?>
                <label class="flex items-center">
                    <input type="checkbox" name="is_featured" value="1"
                        class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                    <span class="ml-2 text-sm text-gray-700"><?= __('v_featured_article') ?></span>
                </label>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 rounded-b-lg flex items-center justify-between">
            <a href="<?= $app->url('kb') ?>" class="text-gray-600 hover:text-gray-900"><?= __('v_cancel') ?></a>
            <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                <i class="fas fa-paper-plane mr-2"></i> <?= __('v_publish_article') ?>
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
        placeholder: <?= json_encode(__('v_write_your_article_content_here')) ?>,
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

    // Before form submit, copy editor content to hidden input
    document.getElementById('articleForm').addEventListener('submit', function(e) {
        var content = quill.root.innerHTML;

        // Check if content is empty (just empty paragraph)
        if (content === '<p><br></p>' || content.trim() === '') {
            e.preventDefault();
            alert(<?= json_encode(__('v_please_enter_article_content')) ?>);
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
