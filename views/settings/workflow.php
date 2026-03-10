<?php
$pageTitle = __('wf_hierarchy_nav');
ob_start();

$routeModeLabels = [
    'workflow_default' => __('wf_route_use_default'),
    'staff_supervisor' => __('wf_route_staff_supervisor'),
    'department_queue' => __('wf_route_department_queue'),
];

$queueStrategyLabels = [
    'least_open' => __('wf_queue_least_open'),
    'round_robin' => __('wf_queue_round_robin'),
];

$miniAppStaffCount = count($miniAppStaffProfiles ?? []);
$incomingHandlerCount = count($incomingHandlerUsers ?? []);
$categoryRoutingCount = count($categoryRoutingRules ?? []);
$staffHierarchyCount = count($staffHierarchyRows ?? []);
?>

<div class="space-y-6">
    <section class="rounded-2xl bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-900 text-white shadow-sm overflow-hidden">
        <div class="p-6 md:p-7">
            <div class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs uppercase tracking-[0.18em] text-indigo-200"><?= __('wf_settings_label') ?></p>
                    <h1 class="mt-2 text-2xl md:text-3xl font-bold"><?= __('wf_console_title') ?></h1>
                    <p class="mt-2 text-sm text-indigo-100/90 max-w-2xl">
                        <?= __('wf_console_desc') ?>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="#category-routing" class="inline-flex items-center px-3 py-2 rounded-lg bg-white/10 hover:bg-white/20 text-sm font-medium">
                        <i class="fas fa-route mr-2"></i><?= __('wf_category_routing') ?>
                    </a>
                    <a href="#mappings" class="inline-flex items-center px-3 py-2 rounded-lg bg-white/10 hover:bg-white/20 text-sm font-medium">
                        <i class="fas fa-users mr-2"></i><?= __('wf_mappings') ?>
                    </a>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="rounded-xl bg-white/10 p-3">
                    <p class="text-xs text-indigo-100"><?= __('wf_incoming_handlers') ?></p>
                    <p class="text-xl font-semibold mt-1"><?= (int) $incomingHandlerCount ?></p>
                </div>
                <div class="rounded-xl bg-white/10 p-3">
                    <p class="text-xs text-indigo-100"><?= __('wf_miniapp_staff_profiles') ?></p>
                    <p class="text-xl font-semibold mt-1"><?= (int) $miniAppStaffCount ?></p>
                </div>
                <div class="rounded-xl bg-white/10 p-3">
                    <p class="text-xs text-indigo-100"><?= __('wf_staff_hierarchy') ?></p>
                    <p class="text-xl font-semibold mt-1"><?= (int) $staffHierarchyCount ?></p>
                </div>
                <div class="rounded-xl bg-white/10 p-3">
                    <p class="text-xs text-indigo-100"><?= __('wf_category_routing_rules') ?></p>
                    <p class="text-xl font-semibold mt-1"><?= (int) $categoryRoutingCount ?></p>
                </div>
            </div>
        </div>
    </section>

    <section id="category-routing" class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <article class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-5 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900"><?= __('wf_category_routing_matrix') ?></h2>
                <p class="text-sm text-gray-500 mt-1">
                    <?= __('wf_category_routing_matrix_desc') ?>
                </p>
            </div>

            <div class="p-5 bg-gray-50 border-b border-gray-200 text-sm text-gray-700 space-y-1">
                <p><strong><?= __('wf_priority') ?>:</strong> <?= __('wf_priority_help') ?></p>
                <p><strong><?= __('wf_staff_to_supervisor') ?>:</strong> <?= __('wf_staff_supervisor_help') ?></p>
                <p><strong><?= __('wf_department_queue') ?>:</strong> <?= __('wf_department_queue_help') ?></p>
            </div>

            <form action="<?= $app->url('settings/workflow/category-routing') ?>" method="POST" class="p-5 grid grid-cols-1 md:grid-cols-12 gap-3 items-end" x-data="{ routeMode: 'workflow_default' }">
                <div class="md:col-span-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2"><?= __('category') ?></label>
                    <select name="category_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <option value=""><?= __('wf_select_category') ?></option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-gray-700 mb-2"><?= __('wf_routing_mode') ?></label>
                    <select name="route_mode" x-model="routeMode" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <?php foreach ($allowedCategoryRouteModes as $mode): ?>
                        <option value="<?= htmlspecialchars($mode) ?>"><?= htmlspecialchars($routeModeLabels[$mode] ?? $mode) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-3" x-show="routeMode === 'department_queue'" x-cloak>
                    <label class="block text-sm font-medium text-gray-700 mb-2"><?= __('users_department_label') ?></label>
                    <select name="department_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <option value=""><?= __('wf_select_department') ?></option>
                        <?php foreach ($departments as $department): ?>
                        <option value="<?= (int) $department['id'] ?>"><?= htmlspecialchars($department['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-2" x-show="routeMode === 'department_queue'" x-cloak>
                    <label class="block text-sm font-medium text-gray-700 mb-2"><?= __('wf_queue_strategy') ?></label>
                    <select name="queue_strategy" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <?php foreach ($allowedQueueStrategies as $strategy): ?>
                        <option value="<?= htmlspecialchars($strategy) ?>"><?= htmlspecialchars($queueStrategyLabels[$strategy] ?? $strategy) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-1">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 mt-2 md:mt-0">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <?= __('v_active') ?>
                    </label>
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="w-full px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"><?= __('wf_save_rule') ?></button>
                </div>
            </form>
        </article>

        <article class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-5 border-b border-gray-200 flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900"><?= __('wf_configured_category_rules') ?></h2>
                    <p class="text-sm text-gray-500 mt-1"><?= __('wf_configured_category_rules_desc') ?></p>
                </div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">
                    <?= __('wf_rule_count', ['count' => (int) $categoryRoutingCount]) ?>
                </span>
            </div>

            <div class="max-h-[26rem] overflow-y-auto divide-y divide-gray-100">
                <?php if (empty($categoryRoutingRules)): ?>
                <p class="p-5 text-sm text-gray-500"><?= __('wf_no_category_routing_rules') ?></p>
                <?php endif; ?>

                <?php foreach ($categoryRoutingRules as $rule): ?>
                <div class="p-4 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars((string) ($rule['category_name'] ?? '')) ?></p>
                        <p class="text-sm text-gray-700 mt-1">
                            <?= __('wf_mode') ?>:
                            <span class="font-medium"><?= htmlspecialchars($routeModeLabels[$rule['route_mode']] ?? (string) $rule['route_mode']) ?></span>
                        </p>
                        <?php if (($rule['route_mode'] ?? '') === 'department_queue'): ?>
                        <p class="text-xs text-gray-500 mt-1">
                            <?= __('users_department_label') ?>: <?= htmlspecialchars((string) ($rule['department_name'] ?? '-')) ?>,
                            <?= __('wf_strategy') ?>: <?= htmlspecialchars($queueStrategyLabels[$rule['queue_strategy']] ?? (string) $rule['queue_strategy']) ?>
                        </p>
                        <?php endif; ?>
                        <p class="text-xs text-gray-400 mt-1">
                            <?= __('v_updated') ?>: <?= date('M j, Y g:i A', strtotime((string) ($rule['updated_at'] ?? 'now'))) ?>,
                            <?= __('v_status') ?>: <?= ((int) ($rule['is_active'] ?? 0) === 1) ? __('v_active') : __('v_inactive') ?>
                        </p>
                    </div>
                    <form action="<?= $app->url('settings/workflow/category-routing/' . (int) $rule['id'] . '/delete') ?>" method="POST" onsubmit="return confirm('<?= e(__('wf_delete_category_routing_confirm')) ?>')">
                        <button type="submit" class="px-3 py-1.5 text-xs font-medium bg-red-50 text-red-700 rounded-lg hover:bg-red-100"><?= __('delete') ?></button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </article>
    </section>

    <section id="mappings" class="space-y-4" x-data="{ mappingTab: 'incoming' }">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-2 flex flex-wrap gap-2">
            <button type="button"
                    @click="mappingTab = 'incoming'"
                    :class="mappingTab === 'incoming' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                <?= __('wf_incoming_handlers') ?> (<?= (int) $incomingHandlerCount ?>)
            </button>
            <button type="button"
                    @click="mappingTab = 'manage'"
                    :class="mappingTab === 'manage' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                <?= __('wf_miniapp_staff_mapping') ?> (<?= (int) $miniAppStaffCount ?>)
            </button>
            <button type="button"
                    @click="mappingTab = 'structure'"
                    :class="mappingTab === 'structure' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                <?= __('wf_staff_under_who') ?> (<?= (int) $staffHierarchyCount ?>)
            </button>
        </div>

        <div x-show="mappingTab === 'incoming'" x-cloak class="grid grid-cols-1 gap-6">
            <article class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-5 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900"><?= __('wf_incoming_customer_handlers') ?></h2>
                    <p class="text-sm text-gray-500 mt-1">
                        <?= __('wf_incoming_customer_handlers_desc') ?>
                    </p>
                </div>

                <form action="<?= $app->url('settings/workflow/incoming-handlers') ?>" method="POST" class="p-5 space-y-4">
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700">
                        <?= __('wf_auto_distribution_note') ?>
                    </div>

                    <div class="max-h-[24rem] overflow-y-auto rounded-xl border border-gray-200 divide-y divide-gray-100">
                        <?php if (empty($staffUsers)): ?>
                        <p class="p-4 text-sm text-gray-500"><?= __('wf_no_active_staff_users') ?></p>
                        <?php endif; ?>

                        <?php foreach ($staffUsers as $staff): ?>
                        <?php $staffId = (int) ($staff['id'] ?? 0); ?>
                        <label class="p-4 flex items-start gap-3 hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox"
                                   name="incoming_handler_user_ids[]"
                                   value="<?= $staffId ?>"
                                   <?= in_array($staffId, $incomingHandlerUserIds ?? [], true) ? 'checked' : '' ?>
                                   class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars((string) ($staff['name'] ?? '')) ?></p>
                                <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars((string) ($staff['email'] ?? '')) ?></p>
                                <p class="text-xs text-indigo-700 mt-1"><?= htmlspecialchars((string) ($staff['role'] ?? 'staff')) ?></p>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                            <?= __('wf_save_incoming_handlers') ?>
                        </button>
                    </div>
                </form>
            </article>
        </div>

        <div x-show="mappingTab === 'manage'" x-cloak class="grid grid-cols-1 gap-6">
            <article class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-5 border-b border-gray-200 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900"><?= __('wf_miniapp_staff_mapping') ?></h2>
                        <p class="text-sm text-gray-500 mt-1"><?= __('wf_miniapp_staff_mapping_desc') ?></p>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">
                        <?= __('wf_profile_count', ['count' => (int) $miniAppStaffCount]) ?>
                    </span>
                </div>

                <div class="max-h-[26rem] overflow-y-auto divide-y divide-gray-100">
                    <?php if (empty($miniAppStaffProfiles)): ?>
                    <p class="p-5 text-sm text-gray-500"><?= __('wf_no_miniapp_staff_profiles') ?></p>
                    <?php endif; ?>

                    <?php foreach ($miniAppStaffProfiles as $profile): ?>
                    <?php
                    $profileId = (int) ($profile['id'] ?? 0);
                    $selectedLinkedUserId = (int) ($profile['linked_user_id'] ?? 0);
                    $displayName = trim((string) ($profile['matched_name'] ?? ''));
                    if ($displayName === '') {
                        $displayName = __('wf_telegram_user_number', ['id' => (string) ($profile['telegram_user_id'] ?? '')]);
                    }
                    ?>
                    <div class="p-4">
                        <p class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($displayName) ?></p>
                        <p class="text-xs text-gray-500 mt-0.5"><?= __('wf_telegram_id') ?>: <?= htmlspecialchars((string) ($profile['telegram_user_id'] ?? '')) ?></p>
                        <?php if (!empty($profile['linked_user_name'])): ?>
                        <p class="text-xs text-gray-500 mt-1">
                            <?= __('wf_current') ?>: <?= htmlspecialchars($profile['linked_user_name']) ?>
                            (<?= htmlspecialchars((string) ($profile['linked_user_role'] ?? 'staff')) ?>)
                        </p>
                        <?php endif; ?>

                        <form action="<?= $app->url('settings/workflow/miniapp-staff-mapping') ?>" method="POST" class="mt-3 flex items-end gap-2">
                            <input type="hidden" name="profile_id" value="<?= $profileId ?>">
                            <div class="flex-1">
                                <label class="block text-xs font-medium text-gray-600 mb-1"><?= __('wf_mapped_staff_user') ?></label>
                                <select name="linked_user_id" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                    <option value=""><?= __('wf_not_mapped') ?></option>
                                    <?php foreach ($staffUsers as $staff): ?>
                                    <?php $staffId = (int) ($staff['id'] ?? 0); ?>
                                    <option value="<?= $staffId ?>" <?= $selectedLinkedUserId === $staffId ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string) ($staff['name'] ?? '')) ?> (<?= htmlspecialchars((string) ($staff['role'] ?? '')) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="px-3 py-2 text-sm font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"><?= __('v_save') ?></button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </article>
        </div>

        <article x-show="mappingTab === 'structure'" x-cloak class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-5 border-b border-gray-200 flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900"><?= __('wf_staff_org_sitemap') ?></h2>
                    <p class="text-sm text-gray-500 mt-1"><?= __('wf_staff_org_sitemap_desc') ?></p>
                </div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">
                    <?= __('wf_staff_count', ['count' => (int) $staffHierarchyCount]) ?>
                </span>
            </div>

            <?php if (empty($staffHierarchyRows)): ?>
            <p class="p-5 text-sm text-gray-500"><?= __('wf_no_staff_reporting_data') ?></p>
            <?php else: ?>
            <?php
            $staffById = [];
            foreach ($staffHierarchyRows as $row) {
                $staffId = (int) ($row['id'] ?? 0);
                if ($staffId > 0) {
                    $staffById[$staffId] = $row;
                }
            }

            $childrenBySupervisor = [];
            foreach ($staffHierarchyRows as $row) {
                $staffId = (int) ($row['id'] ?? 0);
                $supervisorId = (int) ($row['supervisor_id'] ?? 0);
                if (
                    $staffId > 0
                    && $supervisorId > 0
                    && $supervisorId !== $staffId
                    && isset($staffById[$supervisorId])
                ) {
                    $childrenBySupervisor[$supervisorId][] = $staffId;
                }
            }

            foreach ($childrenBySupervisor as &$childIds) {
                usort($childIds, static function (int $a, int $b) use ($staffById): int {
                    $nameA = (string) ($staffById[$a]['name'] ?? '');
                    $nameB = (string) ($staffById[$b]['name'] ?? '');
                    return strcasecmp($nameA, $nameB);
                });
            }
            unset($childIds);

            $rootIds = [];
            foreach ($staffById as $staffId => $row) {
                $supervisorId = (int) ($row['supervisor_id'] ?? 0);
                if ($supervisorId <= 0 || !isset($staffById[$supervisorId]) || $supervisorId === $staffId) {
                    $rootIds[] = $staffId;
                }
            }

            usort($rootIds, static function (int $a, int $b) use ($staffById): int {
                $nameA = (string) ($staffById[$a]['name'] ?? '');
                $nameB = (string) ($staffById[$b]['name'] ?? '');
                return strcasecmp($nameA, $nameB);
            });

            $renderedStaff = [];
            $renderStaffNode = null;
            $renderStaffNode = static function (int $staffId) use (&$renderStaffNode, &$renderedStaff, $staffById, $childrenBySupervisor): void {
                if (isset($renderedStaff[$staffId]) || !isset($staffById[$staffId])) {
                    return;
                }

                $renderedStaff[$staffId] = true;
                $staff = $staffById[$staffId];
                $children = $childrenBySupervisor[$staffId] ?? [];
                ?>
                <li class="relative">
                    <div class="rounded-xl border border-indigo-100 bg-white p-3 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars((string) ($staff['name'] ?? '')) ?></p>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-50 text-indigo-700">
                                <?= htmlspecialchars((string) ($staff['role'] ?? '')) ?>
                            </span>
                        </div>
                        <?php if (!empty($staff['department_names'])): ?>
                        <p class="mt-1 text-xs text-gray-500"><?= __('users_department_label') ?>: <?= htmlspecialchars((string) $staff['department_names']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($staff['department_manager_names'])): ?>
                        <p class="mt-1 text-xs text-gray-400"><?= __('wf_dept_manager') ?>: <?= htmlspecialchars((string) $staff['department_manager_names']) ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($children)): ?>
                    <ul class="mt-3 ml-4 pl-4 space-y-3 border-l-2 border-indigo-100">
                        <?php foreach ($children as $childId): ?>
                        <?php $renderStaffNode($childId); ?>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </li>
                <?php
            };
            ?>

            <div class="p-5">
                <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-slate-900"><?= __('wf_hierarchy_tree') ?></h3>
                        <span class="text-xs text-slate-600"><?= __('wf_hierarchy_tree_caption') ?></span>
                    </div>

                    <ul class="mt-4 space-y-4">
                        <?php foreach ($rootIds as $rootId): ?>
                        <?php $renderStaffNode($rootId); ?>
                        <?php endforeach; ?>
                    </ul>

                    <?php
                    $unplacedIds = array_values(array_filter(array_keys($staffById), static fn(int $id): bool => !isset($renderedStaff[$id])));
                    ?>
                    <?php if (!empty($unplacedIds)): ?>
                    <div class="mt-5 pt-4 border-t border-slate-200">
                        <p class="text-xs font-semibold text-slate-700 mb-3"><?= __('wf_other_linked_staff') ?></p>
                        <ul class="space-y-3">
                            <?php foreach ($unplacedIds as $unplacedId): ?>
                            <?php $renderStaffNode($unplacedId); ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </article>
    </section>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';



