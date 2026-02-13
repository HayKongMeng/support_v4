<?php
$pageTitle = 'New Ticket';
ob_start();
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Submit a Support Request</h2>
        <p class="text-gray-500 mt-1">Describe your issue and we'll get back to you as soon as possible.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6">
        <form action="<?= $app->url('customer/tickets') ?>" method="POST" class="space-y-6">
            <div>
                <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">Subject *</label>
                <input type="text" id="subject" name="subject"
                       value="<?= htmlspecialchars($oldInput['subject'] ?? '') ?>"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                       placeholder="Brief summary of your issue"
                       required>
            </div>

            <div>
                <label for="category_id" class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                <select id="category_id" name="category_id"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Select a category (optional)</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">Don't worry if unsure - we'll categorize it for you.</p>
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description *</label>
                <textarea id="description" name="description" rows="6"
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                          placeholder="Please provide as much detail as possible..."
                          required><?= htmlspecialchars($oldInput['description'] ?? '') ?></textarea>
                <p class="text-xs text-gray-500 mt-1">Include steps to reproduce the issue, error messages, and screenshots if relevant.</p>
            </div>

            <div class="flex items-center justify-end gap-4 pt-4">
                <a href="<?= $app->url('customer/tickets') ?>" class="px-4 py-2 text-gray-600 hover:text-gray-800">Cancel</a>
                <button type="submit"
                        class="px-6 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700">
                    Submit Ticket
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/customer.php';
