<?php
$pageTitle = 'Tickets';
ob_start();
?>

<div class="bg-white rounded-xl shadow-sm">
    <!-- Header -->
    <div class="p-6 border-b border-gray-200">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">All Tickets</h2>
                <p class="text-sm text-gray-500"><?= $tickets['total'] ?> total tickets</p>
            </div>
            <a href="<?= $app->url('tickets/create') ?>"
               class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700">
                <i class="fas fa-plus mr-2"></i> New Ticket
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="p-4 border-b border-gray-200 bg-gray-50">
        <form method="GET" class="flex flex-wrap gap-4">
            <div class="flex-1 min-w-[200px]">
                <input type="text" name="search" value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
                       placeholder="Search tickets..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
            </div>
            <select name="status" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">All Status</option>
                <option value="open" <?= ($filters['status'] ?? '') === 'open' ? 'selected' : '' ?>>Open</option>
                <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="in_progress" <?= ($filters['status'] ?? '') === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="resolved" <?= ($filters['status'] ?? '') === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                <option value="closed" <?= ($filters['status'] ?? '') === 'closed' ? 'selected' : '' ?>>Closed</option>
            </select>
            <select name="priority" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">All Priority</option>
                <option value="urgent" <?= ($filters['priority'] ?? '') === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                <option value="high" <?= ($filters['priority'] ?? '') === 'high' ? 'selected' : '' ?>>High</option>
                <option value="medium" <?= ($filters['priority'] ?? '') === 'medium' ? 'selected' : '' ?>>Medium</option>
                <option value="low" <?= ($filters['priority'] ?? '') === 'low' ? 'selected' : '' ?>>Low</option>
            </select>
            <select name="category_id" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= ($filters['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <select name="assigned_to" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">All Agents</option>
                <option value="unassigned" <?= ($filters['assigned_to'] ?? '') === 'unassigned' ? 'selected' : '' ?>>Unassigned</option>
                <?php foreach ($agents as $agent): ?>
                <option value="<?= $agent['id'] ?>" <?= ($filters['assigned_to'] ?? '') == $agent['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($agent['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm hover:bg-gray-700">
                Filter
            </button>
            <?php if (!empty(array_filter($filters))): ?>
            <a href="<?= $app->url('tickets') ?>" class="px-4 py-2 text-gray-600 hover:text-gray-800 text-sm">
                Clear
            </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Ticket List -->
    <div class="divide-y divide-gray-200">
        <?php if (empty($tickets['items'])): ?>
        <div class="p-12 text-center">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-ticket-alt text-gray-400 text-2xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900">No tickets found</h3>
            <p class="text-gray-500 mt-1">Try adjusting your filters or create a new ticket.</p>
        </div>
        <?php else: ?>
        <?php foreach ($tickets['items'] as $ticket): ?>
        <div class="p-4 hover:bg-gray-50 transition-colors <?= $ticket['assigned_to'] ? '' : 'bg-yellow-50' ?>">
            <div class="flex items-start gap-4">
                <!-- Priority indicator -->
                <div class="w-1 h-12 rounded-full flex-shrink-0 <?php
                    switch($ticket['priority']) {
                        case 'urgent': echo 'bg-red-500'; break;
                        case 'high': echo 'bg-orange-500'; break;
                        case 'medium': echo 'bg-yellow-500'; break;
                        default: echo 'bg-gray-300';
                    }
                ?>"></div>

                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <a href="<?= $app->url("tickets/{$ticket['id']}") ?>" class="text-sm font-medium text-indigo-600 hover:underline">
                            <?= htmlspecialchars($ticket['ticket_number']) ?>
                        </a>
                        <?php if ($ticket['category_name']): ?>
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full text-white"
                              style="background-color: <?= $ticket['category_color'] ?? '#6366f1' ?>">
                            <?= htmlspecialchars($ticket['category_name']) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <a href="<?= $app->url("tickets/{$ticket['id']}") ?>" class="text-base font-medium text-gray-900 hover:text-indigo-600 truncate block">
                        <?= htmlspecialchars($ticket['subject']) ?>
                    </a>
                    <div class="flex items-center gap-4 mt-2 text-sm text-gray-500">
                        <span><i class="fas fa-user mr-1"></i> <?= htmlspecialchars($ticket['requester_name']) ?></span>
                        <span><i class="fas fa-clock mr-1"></i> <?= date('M j, g:i A', strtotime($ticket['created_at'])) ?></span>
                        <?php if ($ticket['assigned_name']): ?>
                        <span><i class="fas fa-user-check mr-1"></i> <?= htmlspecialchars($ticket['assigned_name']) ?></span>
                        <?php else: ?>
                        <span class="text-orange-600"><i class="fas fa-exclamation-circle mr-1"></i> Unassigned</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="flex flex-col items-end gap-2">
                    <!-- Pickup Button for Unassigned Tickets -->
                    <?php if (!$ticket['assigned_to']): ?>
                    <form action="<?= $app->url("tickets/{$ticket['id']}/pickup") ?>" method="POST" class="mb-2" onsubmit="return confirm('Assign this ticket to yourself?')">
                        <button type="submit" class="px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-hand-paper mr-1"></i> Pick Up
                        </button>
                    </form>
                    <?php endif; ?>

                    <span class="inline-flex px-3 py-1 text-xs font-medium rounded-full
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
                    <span class="text-xs text-gray-500"><?= ucfirst($ticket['source']) ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($tickets['total_pages'] > 1): ?>
    <div class="p-4 border-t border-gray-200 flex items-center justify-between">
        <p class="text-sm text-gray-500">
            Page <?= $tickets['current_page'] ?> of <?= $tickets['total_pages'] ?>
        </p>
        <div class="flex gap-2">
            <?php if ($tickets['current_page'] > 1): ?>
            <a href="?page=<?= $tickets['current_page'] - 1 ?>&<?= http_build_query($filters) ?>"
               class="px-3 py-1 text-sm border border-gray-300 rounded hover:bg-gray-50">Previous</a>
            <?php endif; ?>
            <?php if ($tickets['current_page'] < $tickets['total_pages']): ?>
            <a href="?page=<?= $tickets['current_page'] + 1 ?>&<?= http_build_query($filters) ?>"
               class="px-3 py-1 text-sm border border-gray-300 rounded hover:bg-gray-50">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
