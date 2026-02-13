<?php
$pageTitle = "Ticket #{$ticket['ticket_number']}";
ob_start();
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main Content -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Ticket Header -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <div class="flex items-center gap-2 mb-2">
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
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full
                            <?php
                            switch($ticket['priority']) {
                                case 'urgent': echo 'bg-red-100 text-red-800'; break;
                                case 'high': echo 'bg-orange-100 text-orange-800'; break;
                                case 'medium': echo 'bg-yellow-100 text-yellow-800'; break;
                                default: echo 'bg-gray-100 text-gray-800';
                            }
                            ?>">
                            <?= ucfirst($ticket['priority']) ?>
                        </span>
                    </div>
                    <h1 class="text-xl font-semibold text-gray-900"><?= htmlspecialchars($ticket['subject']) ?></h1>
                </div>
                <span class="text-xs text-gray-500"><?= ucfirst($ticket['source']) ?></span>
            </div>

            <div class="flex items-center gap-4 text-sm text-gray-500">
                <span><i class="fas fa-user mr-1"></i> <?= htmlspecialchars($ticket['requester_name']) ?> (<?= htmlspecialchars($ticket['requester_email']) ?>)</span>
                <span><i class="fas fa-clock mr-1"></i> <?= date('M j, Y g:i A', strtotime($ticket['created_at'])) ?></span>
            </div>

            <?php if ($ticket['ai_suggested_category'] && $ticket['ai_confidence_score']): ?>
            <div class="mt-4 p-3 bg-indigo-50 rounded-lg text-sm">
                <i class="fas fa-robot text-indigo-600 mr-2"></i>
                AI suggested category with <?= round($ticket['ai_confidence_score'] * 100) ?>% confidence
            </div>
            <?php endif; ?>
        </div>

        <!-- Messages Thread -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b border-gray-200">
                <h2 class="font-semibold text-gray-800">Conversation</h2>
            </div>
            <div class="divide-y divide-gray-100 max-h-[500px] overflow-y-auto">
                <?php foreach ($messages as $msg): ?>
                <div class="p-4 <?= $msg['is_internal'] ? 'bg-yellow-50' : '' ?>">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0
                            <?= in_array($msg['user_role'] ?? '', ['admin', 'agent']) ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-600' ?>">
                            <?= strtoupper(substr($msg['user_name'] ?? 'S', 0, 1)) ?>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-medium text-gray-900"><?= htmlspecialchars($msg['user_name'] ?? 'System') ?></span>
                                <?php if ($msg['is_internal']): ?>
                                <span class="text-xs px-2 py-0.5 bg-yellow-200 text-yellow-800 rounded-full">Internal Note</span>
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
                                    $isAudio = strpos($attachment['mime_type'], 'audio/') === 0;
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
                                    <?php elseif ($isAudio): ?>
                                    <!-- Audio Preview -->
                                    <div class="flex flex-col gap-1">
                                        <audio controls src="<?= $fileUrl ?>" class="w-64 max-w-full"></audio>
                                        <p class="text-xs text-gray-500 truncate max-w-[160px]"><?= htmlspecialchars($attachment['original_name']) ?></p>
                                    </div>
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
        <div class="bg-white rounded-xl shadow-sm p-6" x-data="{ isInternal: false, message: '', files: [], dragover: false }">
            <h3 class="font-semibold text-gray-800 mb-4">Reply</h3>
            <form action="<?= $app->url("tickets/{$ticket['id']}/reply") ?>" method="POST" enctype="multipart/form-data">
                <div class="mb-4">
                    <textarea name="message" rows="4" x-model="message"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                              placeholder="Type your reply..."></textarea>
                </div>

                <!-- File Upload -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Attachments</label>
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

                <!-- Canned Responses -->
                <?php if (!empty($cannedResponses)): ?>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Quick Responses</label>
                    <select class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm"
                            @change="if($event.target.value) { message = $event.target.value; $event.target.value = ''; }">
                        <option value="">Select a canned response...</option>
                        <?php foreach ($cannedResponses as $cr): ?>
                        <option value="<?= htmlspecialchars($cr['content']) ?>">
                            <?= htmlspecialchars($cr['title']) ?>
                            <?= $cr['shortcut'] ? "({$cr['shortcut']})" : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="flex items-center justify-between">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_internal" x-model="isInternal"
                               class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-gray-600">Internal note (hidden from customer)</span>
                    </label>
                    <button type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                            :class="{ 'bg-yellow-600 hover:bg-yellow-700': isInternal }">
                        <span x-show="!isInternal">Send Reply</span>
                        <span x-show="isInternal">Add Note</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- Properties -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="font-semibold text-gray-800 mb-4">Properties</h3>

            <!-- Status -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <form action="<?= $app->url("tickets/{$ticket['id']}/status") ?>" method="POST">
                    <select name="status" onchange="this.form.submit()"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="open" <?= $ticket['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                        <option value="pending" <?= $ticket['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="in_progress" <?= $ticket['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="resolved" <?= $ticket['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                        <option value="closed" <?= $ticket['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                    </select>
                </form>
            </div>

            <!-- Priority -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                <form action="<?= $app->url("tickets/{$ticket['id']}/priority") ?>" method="POST">
                    <select name="priority" onchange="this.form.submit()"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="low" <?= $ticket['priority'] === 'low' ? 'selected' : '' ?>>Low</option>
                        <option value="medium" <?= $ticket['priority'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="high" <?= $ticket['priority'] === 'high' ? 'selected' : '' ?>>High</option>
                        <option value="urgent" <?= $ticket['priority'] === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                    </select>
                </form>
            </div>

            <!-- Category -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                <form action="<?= $app->url("tickets/{$ticket['id']}/category") ?>" method="POST">
                    <select name="category_id" onchange="this.form.submit()"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">Uncategorized</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $ticket['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <!-- Assigned To -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Assigned To</label>
                <form action="<?= $app->url("tickets/{$ticket['id']}/assign") ?>" method="POST">
                    <select name="assigned_to" onchange="this.form.submit()"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">Unassigned</option>
                        <?php foreach ($agents as $agent): ?>
                        <option value="<?= $agent['id'] ?>" <?= $ticket['assigned_to'] == $agent['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($agent['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>

        <!-- Requester Info -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="font-semibold text-gray-800 mb-4">Requester</h3>
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center text-gray-600 font-medium">
                    <?= strtoupper(substr($ticket['requester_name'], 0, 1)) ?>
                </div>
                <div>
                    <p class="font-medium text-gray-900"><?= htmlspecialchars($ticket['requester_name']) ?></p>
                    <p class="text-sm text-gray-500"><?= htmlspecialchars($ticket['requester_email']) ?></p>
                </div>
            </div>
        </div>

        <?php if (!empty($survey)): ?>
        <!-- Customer Satisfaction -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="font-semibold text-gray-800 mb-4">Customer Satisfaction</h3>
            <?php if ($survey['rating']): ?>
            <div class="text-center">
                <div class="text-3xl mb-2">
                    <?= str_repeat('⭐', $survey['rating']) ?><?= str_repeat('☆', 5 - $survey['rating']) ?>
                </div>
                <p class="text-sm text-gray-600">
                    <?php
                    $labels = [1 => 'Very Poor', 2 => 'Poor', 3 => 'Okay', 4 => 'Good', 5 => 'Excellent'];
                    echo $labels[$survey['rating']] ?? '';
                    ?>
                </p>
                <?php if ($survey['comment']): ?>
                <div class="mt-3 p-3 bg-gray-50 rounded-lg text-left">
                    <p class="text-xs text-gray-500 uppercase tracking-wide mb-1">Customer Feedback</p>
                    <p class="text-sm text-gray-700"><?= htmlspecialchars($survey['comment']) ?></p>
                </div>
                <?php endif; ?>
                <p class="text-xs text-gray-400 mt-3">
                    Rated on <?= date('M j, Y', strtotime($survey['rated_at'])) ?>
                </p>
            </div>
            <?php else: ?>
            <div class="text-center text-sm text-gray-500">
                <i class="fas fa-clock text-gray-400 text-2xl mb-2"></i>
                <p>Survey sent, awaiting response</p>
                <p class="text-xs text-gray-400 mt-1">
                    Sent on <?= date('M j, Y', strtotime($survey['survey_sent_at'])) ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Activity Log -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="font-semibold text-gray-800 mb-4">Activity</h3>
            <div class="space-y-3 max-h-64 overflow-y-auto">
                <?php foreach (array_slice($activities, 0, 10) as $activity): ?>
                <div class="flex items-start text-sm">
                    <div class="w-2 h-2 bg-gray-400 rounded-full mt-1.5 mr-2 flex-shrink-0"></div>
                    <div>
                        <p class="text-gray-700"><?= htmlspecialchars($activity['description'] ?? $activity['action']) ?></p>
                        <p class="text-xs text-gray-500">
                            <?= $activity['user_name'] ?? 'System' ?> &bull;
                            <?= date('M j, g:i A', strtotime($activity['created_at'])) ?>
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
