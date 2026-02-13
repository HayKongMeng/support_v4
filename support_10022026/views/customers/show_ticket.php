<?php
$pageTitle = "Ticket #{$ticket['ticket_number']}";
ob_start();
?>

<div class="max-w-3xl mx-auto">
    <!-- Ticket Header -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
        <div class="flex items-center gap-2 mb-2">
            <a href="<?= $app->url('customer/tickets') ?>" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-arrow-left"></i>
            </a>
            <span class="text-sm font-medium text-indigo-600"><?= htmlspecialchars($ticket['ticket_number']) ?></span>
            <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full
                <?php
                switch($ticket['status']) {
                    case 'open': echo 'bg-blue-100 text-blue-800'; break;
                    case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                    case 'in_progress': echo 'bg-purple-100 text-purple-800'; break;
                    case 'resolved': echo 'bg-green-100 text-green-800'; break;
                    default: echo 'bg-gray-100 text-gray-800';
                }
                ?>">
                <?= ucfirst(str_replace('_', ' ', $ticket['status'])) ?>
            </span>
        </div>
        <h1 class="text-xl font-semibold text-gray-900 mb-2"><?= htmlspecialchars($ticket['subject']) ?></h1>
        <p class="text-sm text-gray-500">
            Created on <?= date('F j, Y \a\t g:i A', strtotime($ticket['created_at'])) ?>
            <?php if (!empty($ticket['category_name'])): ?>
            &bull; <?= htmlspecialchars($ticket['category_name']) ?>
            <?php endif; ?>
        </p>
    </div>

    <!-- Messages -->
    <div class="bg-white rounded-xl shadow-sm mb-6">
        <div class="p-4 border-b border-gray-200">
            <h2 class="font-semibold text-gray-800">Conversation</h2>
        </div>
        <div class="divide-y divide-gray-100">
            <?php foreach ($messages as $msg): ?>
            <div class="p-4">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0
                        <?= in_array($msg['user_role'] ?? '', ['admin', 'agent']) ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-600' ?>">
                        <?= strtoupper(substr($msg['user_name'] ?? 'S', 0, 1)) ?>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="font-medium text-gray-900">
                                <?= htmlspecialchars($msg['user_name'] ?? 'Support Team') ?>
                            </span>
                            <?php if (in_array($msg['user_role'] ?? '', ['admin', 'agent'])): ?>
                            <span class="text-xs px-2 py-0.5 bg-indigo-100 text-indigo-800 rounded-full">Support</span>
                            <?php endif; ?>
                            <span class="text-xs text-gray-500"><?= date('M j, g:i A', strtotime($msg['created_at'])) ?></span>
                        </div>
                        <div class="text-gray-700 whitespace-pre-wrap"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>

                        <?php if (!empty($msg['attachment_files'])): ?>
                        <!-- Attachments -->
                        <div class="mt-3 space-y-2">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Attachments</p>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($msg['attachment_files'] as $attachment): ?>
                                <?php
                                $isImage = strpos($attachment['mime_type'], 'image/') === 0;
                                $fileUrl = $app->url('uploads/' . $attachment['path']);
                                ?>
                                <?php if ($isImage): ?>
                                <!-- Image Preview -->
                                <a href="<?= $fileUrl ?>" target="_blank" class="block">
                                    <div class="relative group">
                                        <img src="<?= $fileUrl ?>" alt="<?= htmlspecialchars($attachment['original_name']) ?>"
                                             class="h-20 w-auto rounded-lg border border-gray-200 object-cover hover:border-indigo-500 transition-colors">
                                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 rounded-lg transition-all flex items-center justify-center">
                                            <i class="fas fa-expand text-white opacity-0 group-hover:opacity-100"></i>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1 truncate max-w-[100px]"><?= htmlspecialchars($attachment['original_name']) ?></p>
                                </a>
                                <?php else: ?>
                                <!-- File Download -->
                                <a href="<?= $fileUrl ?>" target="_blank" download
                                   class="flex items-center gap-2 px-3 py-2 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                                    <i class="fas <?php
                                        if (strpos($attachment['mime_type'], 'pdf') !== false) echo 'fa-file-pdf text-red-500';
                                        elseif (strpos($attachment['mime_type'], 'word') !== false) echo 'fa-file-word text-blue-500';
                                        elseif (strpos($attachment['mime_type'], 'excel') !== false || strpos($attachment['mime_type'], 'spreadsheet') !== false) echo 'fa-file-excel text-green-500';
                                        elseif (strpos($attachment['mime_type'], 'zip') !== false) echo 'fa-file-archive text-yellow-500';
                                        else echo 'fa-file text-gray-500';
                                    ?>"></i>
                                    <div class="min-w-0">
                                        <p class="text-sm text-gray-700 truncate max-w-[150px]"><?= htmlspecialchars($attachment['original_name']) ?></p>
                                        <p class="text-xs text-gray-500"><?= number_format($attachment['size'] / 1024, 1) ?> KB</p>
                                    </div>
                                </a>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Reply Form -->
    <?php if (!in_array($ticket['status'], ['closed'])): ?>
    <div class="bg-white rounded-xl shadow-sm p-6" x-data="{ files: [], dragover: false }">
        <h3 class="font-semibold text-gray-800 mb-4">Add a Reply</h3>
        <form action="<?= $app->url("customer/tickets/{$ticket['id']}/reply") ?>" method="POST" enctype="multipart/form-data">
            <div class="mb-4">
                <textarea name="message" rows="4"
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                          placeholder="Type your message..."></textarea>
            </div>

            <!-- File Upload -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Attachments (optional)</label>
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center transition-colors"
                     :class="{ 'border-indigo-500 bg-indigo-50': dragover }"
                     @dragover.prevent="dragover = true"
                     @dragleave.prevent="dragover = false"
                     @drop.prevent="dragover = false; files = [...files, ...$event.dataTransfer.files]">
                    <input type="file" name="attachments[]" id="attachments" multiple
                           class="hidden"
                           accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip,.rar"
                           @change="files = [...files, ...$event.target.files]">
                    <label for="attachments" class="cursor-pointer">
                        <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                        <p class="text-sm text-gray-600">
                            <span class="text-indigo-600 font-medium">Click to upload</span> or drag and drop
                        </p>
                        <p class="text-xs text-gray-500 mt-1">Images, PDFs, Documents up to 10MB each</p>
                    </label>
                </div>
                <!-- File Preview -->
                <div x-show="files.length > 0" class="mt-3 space-y-2">
                    <template x-for="(file, index) in files" :key="index">
                        <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                            <div class="flex items-center gap-2 min-w-0">
                                <i class="fas fa-file text-gray-400"></i>
                                <span class="text-sm text-gray-700 truncate" x-text="file.name"></span>
                                <span class="text-xs text-gray-500" x-text="(file.size / 1024).toFixed(1) + ' KB'"></span>
                            </div>
                            <button type="button" @click="files = files.filter((_, i) => i !== index)"
                                    class="text-red-500 hover:text-red-700">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit"
                        class="px-6 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700">
                    Send Reply
                </button>
            </div>
        </form>
    </div>
    <?php else: ?>
    <div class="bg-gray-100 rounded-xl p-6 text-center">
        <p class="text-gray-600">This ticket has been closed. If you need further assistance, please create a new ticket.</p>
        <a href="<?= $app->url('customer/tickets/create') ?>"
           class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 mt-4">
            <i class="fas fa-plus mr-2"></i> New Ticket
        </a>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/customer.php';
