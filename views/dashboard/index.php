<?php
$pageTitle = 'Dashboard';
ob_start();
?>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Open Tickets</p>
                <p class="text-3xl font-bold text-gray-800"><?= $stats['open'] ?? 0 ?></p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                <i class="fas fa-envelope-open text-blue-600"></i>
            </div>
        </div>
        <p class="text-xs text-gray-500 mt-2">
            <span class="text-red-500"><?= $stats['urgent'] ?? 0 ?> urgent</span>
        </p>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">In Progress</p>
                <p class="text-3xl font-bold text-gray-800"><?= ($stats['pending'] ?? 0) + ($stats['in_progress'] ?? 0) ?></p>
            </div>
            <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                <i class="fas fa-clock text-yellow-600"></i>
            </div>
        </div>
        <p class="text-xs text-gray-500 mt-2">
            <?= $stats['pending'] ?? 0 ?> pending, <?= $stats['in_progress'] ?? 0 ?> active
        </p>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Resolved Today</p>
                <p class="text-3xl font-bold text-gray-800"><?= $stats['resolved'] ?? 0 ?></p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                <i class="fas fa-check-circle text-green-600"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Unassigned</p>
                <p class="text-3xl font-bold text-gray-800"><?= $stats['unassigned'] ?? 0 ?></p>
            </div>
            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                <i class="fas fa-user-slash text-red-600"></i>
            </div>
        </div>
    </div>
</div>

<!-- SLA Breach Warning (ITIL Standard) -->
<?php if (!empty($slaNearBreach)): ?>
<div class="bg-gradient-to-r from-orange-500 to-red-600 rounded-xl shadow-lg p-6 mb-6 text-white">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                <i class="fas fa-exclamation-triangle text-2xl"></i>
            </div>
            <div>
                <h3 class="text-xl font-bold">SLA Breach Alert</h3>
                <p class="text-sm opacity-90"><?= count($slaNearBreach) ?> ticket(s) require immediate attention (ITIL Standard)</p>
            </div>
        </div>
        <a href="<?= $app->url('analytics') ?>" class="px-4 py-2 bg-white/20 hover:bg-white/30 rounded-lg text-sm font-medium">
            View Analytics
        </a>
    </div>
    <div class="space-y-2">
        <?php foreach (array_slice($slaNearBreach, 0, 3) as $ticket): ?>
        <?php
        $responseOverdue = !$ticket['first_response_at'] && strtotime($ticket['sla_response_due_at']) < time();
        $resolutionOverdue = !$ticket['resolved_at'] && strtotime($ticket['sla_resolution_due_at']) < time();
        $minutesUntilBreach = $responseOverdue || $resolutionOverdue ? 0 : min(
            !$ticket['first_response_at'] ? round((strtotime($ticket['sla_response_due_at']) - time()) / 60) : PHP_INT_MAX,
            !$ticket['resolved_at'] ? round((strtotime($ticket['sla_resolution_due_at']) - time()) / 60) : PHP_INT_MAX
        );
        ?>
        <a href="<?= $app->url("tickets/{$ticket['id']}") ?>" class="block bg-white/10 hover:bg-white/20 rounded-lg p-3 transition-colors">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <div class="flex items-center gap-2">
                        <span class="font-semibold"><?= htmlspecialchars($ticket['ticket_number']) ?></span>
                        <span class="text-xs opacity-75">&bull;</span>
                        <span class="text-sm opacity-90"><?= htmlspecialchars($ticket['subject']) ?></span>
                    </div>
                    <p class="text-xs opacity-75 mt-1">
                        <?php if ($responseOverdue): ?>
                            <i class="fas fa-clock mr-1"></i> Response SLA BREACHED
                        <?php elseif ($resolutionOverdue): ?>
                            <i class="fas fa-hourglass-end mr-1"></i> Resolution SLA BREACHED
                        <?php else: ?>
                            <i class="fas fa-clock mr-1"></i> SLA breach in <?= $minutesUntilBreach ?> minutes
                        <?php endif; ?>
                    </p>
                </div>
                <span class="px-3 py-1 bg-white/20 rounded-full text-xs font-medium">
                    <?= ucfirst($ticket['priority']) ?>
                </span>
            </div>
        </a>
        <?php endforeach; ?>
        <?php if (count($slaNearBreach) > 3): ?>
        <p class="text-sm text-center opacity-75 mt-2">
            + <?= count($slaNearBreach) - 3 ?> more tickets near SLA breach
        </p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- SLA Stats Summary -->
<?php if (isset($slaStats) && $slaStats['total_with_sla'] > 0): ?>
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-2">
            <p class="text-sm text-gray-600 font-medium">Response On Time</p>
            <i class="fas fa-bolt text-green-600"></i>
        </div>
        <p class="text-2xl font-bold text-green-600">
            <?= $slaStats['response_met'] ?? 0 ?>
        </p>
        <p class="text-xs text-gray-500 mt-1">
            <?= $slaStats['response_breached'] ?? 0 ?> breached
        </p>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-2">
            <p class="text-sm text-gray-600 font-medium">Resolution On Time</p>
            <i class="fas fa-check-circle text-green-600"></i>
        </div>
        <p class="text-2xl font-bold text-green-600">
            <?= $slaStats['resolution_met'] ?? 0 ?>
        </p>
        <p class="text-xs text-gray-500 mt-1">
            <?= $slaStats['resolution_breached'] ?? 0 ?> breached
        </p>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-2">
            <p class="text-sm text-gray-600 font-medium">Response Overdue</p>
            <i class="fas fa-exclamation-triangle text-orange-600"></i>
        </div>
        <p class="text-2xl font-bold <?= ($slaStats['response_overdue'] ?? 0) > 0 ? 'text-orange-600' : 'text-gray-400' ?>">
            <?= $slaStats['response_overdue'] ?? 0 ?>
        </p>
        <p class="text-xs text-gray-500 mt-1">Needs immediate response</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-2">
            <p class="text-sm text-gray-600 font-medium">Resolution Overdue</p>
            <i class="fas fa-hourglass-end text-red-600"></i>
        </div>
        <p class="text-2xl font-bold <?= ($slaStats['resolution_overdue'] ?? 0) > 0 ? 'text-red-600' : 'text-gray-400' ?>">
            <?= $slaStats['resolution_overdue'] ?? 0 ?>
        </p>
        <p class="text-xs text-gray-500 mt-1">Needs immediate resolution</p>
    </div>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Recent Tickets -->
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-800">Recent Tickets</h2>
                <a href="<?= $app->url('tickets') ?>" class="text-sm text-indigo-600 hover:underline">View All</a>
            </div>
        </div>
        <div class="divide-y divide-gray-200">
            <?php if (empty($recentTickets)): ?>
            <div class="p-6 text-center text-gray-500">
                No tickets yet
            </div>
            <?php else: ?>
            <?php foreach ($recentTickets as $ticket): ?>
            <a href="<?= $app->url("tickets/{$ticket['id']}") ?>" class="block p-4 hover:bg-gray-50 transition-colors">
                <div class="flex items-start justify-between">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-indigo-600"><?= htmlspecialchars($ticket['ticket_number']) ?></span>
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
                        <p class="mt-1 text-sm text-gray-900 truncate"><?= htmlspecialchars($ticket['subject']) ?></p>
                        <p class="mt-1 text-xs text-gray-500">
                            <?= htmlspecialchars($ticket['requester_name']) ?> &bull;
                            <?= date('M j, g:i A', strtotime($ticket['created_at'])) ?>
                        </p>
                    </div>
                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full
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
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Category Distribution & Activity -->
    <div class="space-y-6">
        <!-- Categories -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">By Category</h2>
            <div class="space-y-3">
                <?php foreach ($categoryStats as $cat): ?>
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-3 h-3 rounded-full mr-2" style="background-color: <?= $cat['color'] ?>"></div>
                        <span class="text-sm text-gray-700"><?= htmlspecialchars($cat['name']) ?></span>
                    </div>
                    <div class="flex items-center">
                        <span class="text-sm font-medium text-gray-900"><?= $cat['open_count'] ?? 0 ?></span>
                        <span class="text-xs text-gray-500 ml-1">open</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Recent Activity</h2>
            <div class="space-y-4">
                <?php foreach (array_slice($recentActivity, 0, 5) as $activity): ?>
                <div class="flex items-start">
                    <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-<?php
                            switch($activity['action']) {
                                case 'ticket_created': echo 'plus'; break;
                                case 'reply_added': echo 'reply'; break;
                                case 'status_changed': echo 'sync'; break;
                                case 'ticket_assigned': echo 'user'; break;
                                default: echo 'circle';
                            }
                        ?> text-gray-500 text-xs"></i>
                    </div>
                    <div class="ml-3 flex-1 min-w-0">
                        <p class="text-sm text-gray-900 truncate">
                            <?= htmlspecialchars($activity['description'] ?? $activity['action']) ?>
                        </p>
                        <p class="text-xs text-gray-500">
                            <?= htmlspecialchars($activity['user_name'] ?? 'System') ?> &bull;
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
