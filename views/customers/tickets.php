<?php
$pageTitle = __('my_tickets');
ob_start();
?>

<div class="mb-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-900"><?= __('my_tickets') ?></h2>
        <a href="<?= $app->url('customer/tickets/create') ?>"
           class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700">
            <i class="fas fa-plus mr-2"></i> <?= __('new_ticket') ?>
        </a>
    </div>
</div>

<!-- Status Filter -->
<div class="mb-6 flex gap-2">
    <a href="<?= $app->url('customer/tickets') ?>"
       class="px-4 py-2 rounded-lg text-sm <?= empty($currentStatus) ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100' ?>">
        <?= __('all') ?>
    </a>
    <a href="<?= $app->url('customer/tickets') ?>?status=open"
       class="px-4 py-2 rounded-lg text-sm <?= $currentStatus === 'open' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100' ?>">
        <?= __('open') ?>
    </a>
    <a href="<?= $app->url('customer/tickets') ?>?status=pending"
       class="px-4 py-2 rounded-lg text-sm <?= $currentStatus === 'pending' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100' ?>">
        <?= __('pending') ?>
    </a>
    <a href="<?= $app->url('customer/tickets') ?>?status=resolved"
       class="px-4 py-2 rounded-lg text-sm <?= $currentStatus === 'resolved' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100' ?>">
        <?= __('resolved') ?>
    </a>
</div>

<!-- Tickets List -->
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <?php if (empty($tickets['items'])): ?>
    <div class="p-12 text-center">
        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-ticket-alt text-gray-400 text-2xl"></i>
        </div>
        <h3 class="text-lg font-medium text-gray-900"><?= __('no_tickets_yet') ?></h3>
        <p class="text-gray-500 mt-1 mb-4"><?= __('create_ticket_help_team') ?></p>
        <a href="<?= $app->url('customer/tickets/create') ?>"
           class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700">
            <i class="fas fa-plus mr-2"></i> <?= __('create_ticket') ?>
        </a>
    </div>
    <?php else: ?>
    <div class="divide-y divide-gray-200">
        <?php foreach ($tickets['items'] as $ticket): ?>
        <a href="<?= $app->url("customer/tickets/{$ticket['id']}") ?>" class="block p-4 hover:bg-gray-50 transition-colors">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
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
                    </div>
                    <h3 class="text-base font-medium text-gray-900"><?= htmlspecialchars($ticket['subject']) ?></h3>
                    <p class="text-sm text-gray-500 mt-1">
                        <?= __('created_on') ?> <?= date('M j, Y', strtotime($ticket['created_at'])) ?>
                    </p>
                </div>
                <i class="fas fa-chevron-right text-gray-400"></i>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ($tickets['total_pages'] > 1): ?>
    <div class="p-4 border-t border-gray-200 flex items-center justify-between">
        <p class="text-sm text-gray-500">
            <?= __('page_of', ['current' => $tickets['current_page'], 'total' => $tickets['total_pages']]) ?>
        </p>
        <div class="flex gap-2">
            <?php if ($tickets['current_page'] > 1): ?>
            <a href="?page=<?= $tickets['current_page'] - 1 ?><?= $currentStatus ? "&status={$currentStatus}" : '' ?>"
               class="px-3 py-1 text-sm border border-gray-300 rounded hover:bg-gray-50"><?= __('previous') ?></a>
            <?php endif; ?>
            <?php if ($tickets['current_page'] < $tickets['total_pages']): ?>
            <a href="?page=<?= $tickets['current_page'] + 1 ?><?= $currentStatus ? "&status={$currentStatus}" : '' ?>"
               class="px-3 py-1 text-sm border border-gray-300 rounded hover:bg-gray-50"><?= __('next') ?></a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/customer.php';
