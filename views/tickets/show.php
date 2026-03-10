<?php
$pageTitle = __('ticket_number_title', ['number' => $ticket['ticket_number']]);
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
                            <?= __((string)$ticket['status']) ?>
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
                            <?= __((string)$ticket['priority']) ?>
                        </span>
                    </div>
                    <h1 class="text-xl font-semibold text-gray-900"><?= htmlspecialchars($ticket['subject']) ?></h1>
                </div>
                <?php
                $sourceKey = strtolower((string)($ticket['source'] ?? ''));
                $sourceLabel = __($sourceKey);
                ?>
                <span class="text-xs text-gray-500"><?= $sourceLabel !== $sourceKey ? $sourceLabel : ucfirst((string)$ticket['source']) ?></span>
            </div>

            <div class="flex items-center gap-4 text-sm text-gray-500">
                <span><i class="fas fa-user mr-1"></i> <?= htmlspecialchars($ticket['requester_name']) ?> (<?= htmlspecialchars($ticket['requester_email']) ?>)</span>
                <span><i class="fas fa-clock mr-1"></i> <?= date('M j, Y g:i A', strtotime($ticket['created_at'])) ?></span>
            </div>

            <?php if ($ticket['ai_suggested_category'] && $ticket['ai_confidence_score']): ?>
            <div class="mt-4 p-3 bg-indigo-50 rounded-lg text-sm">
                <i class="fas fa-robot text-indigo-600 mr-2"></i>
                <?= __('ai_suggested_category_confidence', ['percent' => round($ticket['ai_confidence_score'] * 100)]) ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Messages Thread -->
        <div class="bg-white rounded-xl shadow-sm">
            <div class="p-4 border-b border-gray-200">
                <h2 class="font-semibold text-gray-800"><?= __('conversation') ?></h2>
            </div>
            <div class="divide-y divide-gray-100 max-h-[500px] overflow-y-auto">
                <?php foreach ($messages as $msg): ?>
                <div class="p-4 <?= $msg['is_internal'] ? 'bg-yellow-50' : '' ?>">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0
                            <?= in_array($msg['user_role'] ?? '', ['admin', 'agent']) ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-600' ?>">
                            <?= strtoupper(substr($msg['user_name'] ?? __('system'), 0, 1)) ?>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-medium text-gray-900"><?= htmlspecialchars($msg['user_name'] ?? __('system')) ?></span>
                                <?php if ($msg['is_internal']): ?>
                                <span class="text-xs px-2 py-0.5 bg-yellow-200 text-yellow-800 rounded-full"><?= __('internal_note') ?></span>
                                <?php endif; ?>
                                <span class="text-xs text-gray-500"><?= date('M j, g:i A', strtotime($msg['created_at'])) ?></span>
                            </div>
                            <div class="text-gray-700 whitespace-pre-wrap"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>

                            <?php if (!empty($msg['attachment_files'])): ?>
                            <!-- Attachments -->
                            <div class="mt-3 space-y-2">
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide"><?= __('attachments') ?></p>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($msg['attachment_files'] as $attachment): ?>
                                    <?php
                                    $mimeType = strtolower((string)($attachment['mime_type'] ?? ''));
                                    $fileName = strtolower((string)($attachment['original_name'] ?? $attachment['filename'] ?? ''));
                                    $isImage = strpos($mimeType, 'image/') === 0;
                                    $isAudio = strpos($mimeType, 'audio/') === 0
                                        || $mimeType === 'video/webm'
                                        || str_contains($mimeType, 'opus')
                                        || str_contains($mimeType, 'ogg')
                                        || (bool) preg_match('/\.(ogg|oga|opus|mp3|m4a|aac|wav|weba|webm)$/i', $fileName);
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
            <h3 class="font-semibold text-gray-800 mb-4"><?= __('reply') ?></h3>
            <form action="<?= $app->url("tickets/{$ticket['id']}/reply") ?>" method="POST" enctype="multipart/form-data">
                <div class="mb-4">
                    <textarea name="message" rows="4" x-model="message"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                              placeholder="<?= __('type_your_message') ?>"></textarea>
                </div>

                <!-- File Upload -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2"><?= __('attachments') ?></label>
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
                                <span class="text-indigo-600 font-medium"><?= __('click_to_upload') ?></span> <?= __('or_drag_drop') ?>
                            </p>
                            <p class="text-xs text-gray-500 mt-1"><?= __('images_docs_limit') ?></p>
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
                    <label class="block text-sm font-medium text-gray-700 mb-2"><?= __('quick_responses') ?></label>
                    <select class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm"
                            @change="if($event.target.value) { message = $event.target.value; $event.target.value = ''; }">
                        <option value=""><?= __('select_canned_response') ?></option>
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
                        <span class="ml-2 text-sm text-gray-600"><?= __('internal_note_hidden') ?></span>
                    </label>
                    <button type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                            :class="{ 'bg-yellow-600 hover:bg-yellow-700': isInternal }">
                        <span x-show="!isInternal"><?= __('send_reply') ?></span>
                        <span x-show="isInternal"><?= __('add_note') ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">
        <?php if (!empty($workflowState)): ?>
        <?php
        $workflowStatus = (string) ($workflowState['status'] ?? 'in_progress');
        $workflowStatusClass = $workflowStatus === 'completed'
            ? 'bg-green-100 text-green-700'
            : ($workflowStatus === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-indigo-100 text-indigo-700');
        $stepPosition = (int) ($workflowState['step_position'] ?? 0);
        $totalSteps = (int) ($workflowState['total_steps'] ?? 0);
        $progressPercent = $totalSteps > 0 ? min(100, max(0, (int) round(($stepPosition / $totalSteps) * 100))) : 0;
        $remainingSeconds = isset($workflowState['remaining_seconds']) ? (int) $workflowState['remaining_seconds'] : null;
        $formatDuration = static function (int $seconds): string {
            $seconds = max(0, $seconds);
            $hours = intdiv($seconds, 3600);
            $minutes = intdiv($seconds % 3600, 60);
            if ($hours > 0) {
                return $hours . 'h ' . $minutes . 'm';
            }
            return max(1, $minutes) . 'm';
        };
        ?>
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-semibold text-gray-800"><?= __('wf_workflow') ?></h3>
                <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full <?= $workflowStatusClass ?>">
                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $workflowStatus))) ?>
                </span>
            </div>

            <?php if ($totalSteps > 0): ?>
            <div class="mt-4">
                <div class="flex items-center justify-between text-xs text-gray-500">
                    <span><?= __('wf_step_of', ['current' => max(1, $stepPosition), 'total' => $totalSteps]) ?></span>
                    <span><?= $progressPercent ?>%</span>
                </div>
                <div class="mt-1 h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full bg-indigo-500 rounded-full transition-all" style="width: <?= $progressPercent ?>%"></div>
                </div>
            </div>
            <?php endif; ?>

            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <div class="rounded-lg border border-gray-200 p-3">
                    <p class="text-xs uppercase tracking-wide text-gray-500"><?= __('wf_current_step') ?></p>
                    <p class="mt-1 font-medium text-gray-900">
                        <?= htmlspecialchars($workflowState['current_step_name'] ?? __('wf_step_number', ['step' => (int) ($workflowState['current_step_order'] ?? 0)])) ?>
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3">
                    <p class="text-xs uppercase tracking-wide text-gray-500"><?= __('wf_assigned') ?></p>
                    <p class="mt-1 font-medium text-gray-900"><?= htmlspecialchars($workflowState['current_assignee_name'] ?? __('unassigned')) ?></p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3 sm:col-span-2">
                    <div class="flex flex-wrap items-center justify-between gap-1">
                        <p class="text-xs uppercase tracking-wide text-gray-500"><?= __('wf_next_escalation') ?></p>
                        <?php if (!empty($workflowState['due_at'])): ?>
                        <span class="text-xs text-gray-500"><?= date('M j, Y g:i A', strtotime($workflowState['due_at'])) ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="mt-1 font-medium <?= !empty($workflowState['is_overdue']) ? 'text-red-600' : 'text-gray-900' ?>">
                        <?php if (empty($workflowState['due_at'])): ?>
                            <?= __('wf_no_due_time') ?>
                        <?php elseif (!empty($workflowState['is_overdue'])): ?>
                            <?= __('wf_overdue_by', ['duration' => $formatDuration(abs($remainingSeconds ?? 0))]) ?>
                        <?php else: ?>
                            <?= __('wf_due_in', ['duration' => $formatDuration(max(0, $remainingSeconds ?? 0))]) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <?php if (!empty($workflowState['next_step_name'])): ?>
                <div class="rounded-lg border border-gray-200 p-3 sm:col-span-2">
                    <p class="text-xs uppercase tracking-wide text-gray-500"><?= __('wf_next_step') ?></p>
                    <p class="mt-1 font-medium text-gray-900"><?= htmlspecialchars($workflowState['next_step_name']) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Properties -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="font-semibold text-gray-800 mb-4"><?= __('properties') ?></h3>

            <!-- Status -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2"><?= __('status') ?></label>
                <form action="<?= $app->url("tickets/{$ticket['id']}/status") ?>" method="POST">
                    <select name="status" onchange="this.form.submit()"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="open" <?= $ticket['status'] === 'open' ? 'selected' : '' ?>><?= __('open') ?></option>
                        <option value="pending" <?= $ticket['status'] === 'pending' ? 'selected' : '' ?>><?= __('pending') ?></option>
                        <option value="in_progress" <?= $ticket['status'] === 'in_progress' ? 'selected' : '' ?>><?= __('in_progress') ?></option>
                        <option value="resolved" <?= $ticket['status'] === 'resolved' ? 'selected' : '' ?>><?= __('resolved') ?></option>
                        <option value="closed" <?= $ticket['status'] === 'closed' ? 'selected' : '' ?>><?= __('closed') ?></option>
                    </select>
                </form>
            </div>

            <!-- Priority -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2"><?= __('priority') ?></label>
                <form action="<?= $app->url("tickets/{$ticket['id']}/priority") ?>" method="POST">
                    <select name="priority" onchange="this.form.submit()"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="low" <?= $ticket['priority'] === 'low' ? 'selected' : '' ?>><?= __('low') ?></option>
                        <option value="medium" <?= $ticket['priority'] === 'medium' ? 'selected' : '' ?>><?= __('medium') ?></option>
                        <option value="high" <?= $ticket['priority'] === 'high' ? 'selected' : '' ?>><?= __('high') ?></option>
                        <option value="urgent" <?= $ticket['priority'] === 'urgent' ? 'selected' : '' ?>><?= __('urgent') ?></option>
                    </select>
                </form>
            </div>

            <!-- Category -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2"><?= __('category') ?></label>
                <form action="<?= $app->url("tickets/{$ticket['id']}/category") ?>" method="POST">
                    <select name="category_id" onchange="this.form.submit()"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value=""><?= __('uncategorized') ?></option>
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
                <label class="block text-sm font-medium text-gray-700 mb-2"><?= __('assign_to') ?></label>
                <form action="<?= $app->url("tickets/{$ticket['id']}/assign") ?>" method="POST">
                    <select name="assigned_to" onchange="this.form.submit()"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value=""><?= __('unassigned') ?></option>
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
            <h3 class="font-semibold text-gray-800 mb-4"><?= __('requester') ?></h3>
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
            <h3 class="font-semibold text-gray-800 mb-4"><?= __('customer_satisfaction') ?></h3>
            <?php if ($survey['rating']): ?>
            <div class="text-center">
                <div class="text-3xl mb-2">
                    <?= str_repeat('⭐', $survey['rating']) ?><?= str_repeat('☆', 5 - $survey['rating']) ?>
                </div>
                <p class="text-sm text-gray-600">
                    <?php
                    $labels = [1 => __('very_poor'), 2 => __('poor'), 3 => __('okay'), 4 => __('good'), 5 => __('excellent')];
                    echo $labels[$survey['rating']] ?? '';
                    ?>
                </p>
                <?php if ($survey['comment']): ?>
                <div class="mt-3 p-3 bg-gray-50 rounded-lg text-left">
                    <p class="text-xs text-gray-500 uppercase tracking-wide mb-1"><?= __('customer_feedback') ?></p>
                    <p class="text-sm text-gray-700"><?= htmlspecialchars($survey['comment']) ?></p>
                </div>
                <?php endif; ?>
                <p class="text-xs text-gray-400 mt-3">
                    <?= __('rated_on') ?> <?= date('M j, Y', strtotime($survey['rated_at'])) ?>
                </p>
            </div>
            <?php else: ?>
            <div class="text-center text-sm text-gray-500">
                <i class="fas fa-clock text-gray-400 text-2xl mb-2"></i>
                <p><?= __('survey_sent_awaiting') ?></p>
                <p class="text-xs text-gray-400 mt-1">
                    <?= __('sent_on') ?> <?= date('M j, Y', strtotime($survey['survey_sent_at'])) ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Activity Log -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="font-semibold text-gray-800 mb-4"><?= __('activity') ?></h3>
            <div class="space-y-3 max-h-64 overflow-y-auto">
                <?php foreach (array_slice($activities, 0, 10) as $activity): ?>
                <div class="flex items-start text-sm">
                    <div class="w-2 h-2 bg-gray-400 rounded-full mt-1.5 mr-2 flex-shrink-0"></div>
                    <div>
                        <p class="text-gray-700"><?= htmlspecialchars($activity['description'] ?? $activity['action']) ?></p>
                        <p class="text-xs text-gray-500">
                            <?= $activity['user_name'] ?? __('system') ?> &bull;
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
