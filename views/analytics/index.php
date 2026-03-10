<?php
$pageTitle = __('v_analytics_reports');
ob_start();
?>

<div class="space-y-6">
    <!-- Header with Period Selector and Export -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800"><?= __('v_analytics_reports') ?></h1>
            <p class="text-sm text-gray-600 mt-1"><?= __('v_based_on_itil_iso_20000_international_standards') ?></p>
        </div>
        <div class="flex gap-3">
            <!-- Period Selector -->
            <select onchange="window.location.href='<?= $app->url('analytics') ?>?period='+this.value"
                    class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                <option value="7" <?= $period === '7' ? 'selected' : '' ?>><?= __('v_last_7_days') ?></option>
                <option value="30" <?= $period === '30' ? 'selected' : '' ?>><?= __('v_last_30_days') ?></option>
                <option value="90" <?= $period === '90' ? 'selected' : '' ?>><?= __('v_last_90_days') ?></option>
            </select>

            <!-- Export Button -->
            <a href="<?= $app->url("analytics/export?format=csv&period={$period}") ?>"
               class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center gap-2">
                <i class="fas fa-file-export"></i> <?= __('v_export_csv') ?>
            </a>
        </div>
    </div>

    <!-- KPI Cards (ISO 20000 Standard) -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <!-- Total Tickets -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 font-medium"><?= __('v_total_tickets') ?></p>
                    <p class="text-3xl font-bold text-gray-900 mt-2"><?= number_format($kpiSummary['total_created']) ?></p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-ticket-alt text-blue-600 text-xl"></i>
                </div>
            </div>
            <p class="text-sm text-gray-500 mt-3">
                <?= number_format($kpiSummary['total_resolved']) ?> <?= __('v_resolved') ?>
            </p>
        </div>

        <!-- Resolution Rate -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 font-medium"><?= __('v_resolution_rate') ?></p>
                    <p class="text-3xl font-bold text-gray-900 mt-2"><?= number_format($kpiSummary['resolution_rate'], 1) ?>%</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
            </div>
            <p class="text-sm text-gray-500 mt-3"><?= __('v_iso_20000_kpi') ?></p>
        </div>

        <!-- Response SLA Compliance -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 font-medium"><?= __('v_response_sla') ?></p>
                    <p class="text-3xl font-bold <?= $kpiSummary['response_sla_compliance'] >= 90 ? 'text-green-600' : ($kpiSummary['response_sla_compliance'] >= 75 ? 'text-yellow-600' : 'text-red-600') ?> mt-2">
                        <?= number_format($kpiSummary['response_sla_compliance'], 1) ?>%
                    </p>
                </div>
                <div class="w-12 h-12 <?= $kpiSummary['response_sla_compliance'] >= 90 ? 'bg-green-100' : 'bg-yellow-100' ?> rounded-full flex items-center justify-center">
                    <i class="fas fa-clock <?= $kpiSummary['response_sla_compliance'] >= 90 ? 'text-green-600' : 'text-yellow-600' ?> text-xl"></i>
                </div>
            </div>
            <p class="text-sm text-gray-500 mt-3"><?= __('v_itil_standard') ?></p>
        </div>

        <!-- Resolution SLA Compliance -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600 font-medium"><?= __('v_resolution_sla') ?></p>
                    <p class="text-3xl font-bold <?= $kpiSummary['resolution_sla_compliance'] >= 90 ? 'text-green-600' : ($kpiSummary['resolution_sla_compliance'] >= 75 ? 'text-yellow-600' : 'text-red-600') ?> mt-2">
                        <?= number_format($kpiSummary['resolution_sla_compliance'], 1) ?>%
                    </p>
                </div>
                <div class="w-12 h-12 <?= $kpiSummary['resolution_sla_compliance'] >= 90 ? 'bg-green-100' : 'bg-yellow-100' ?> rounded-full flex items-center justify-center">
                    <i class="fas fa-hourglass-end <?= $kpiSummary['resolution_sla_compliance'] >= 90 ? 'text-green-600' : 'text-yellow-600' ?> text-xl"></i>
                </div>
            </div>
            <p class="text-sm text-gray-500 mt-3"><?= __('v_itil_standard') ?></p>
        </div>
    </div>

    <!-- Additional Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Avg Response Time -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <p class="text-sm text-gray-600 font-medium"><?= __('v_avg_response_time') ?></p>
                <i class="fas fa-bolt text-indigo-600"></i>
            </div>
            <p class="text-2xl font-bold text-gray-900">
                <?= number_format($kpiSummary['avg_response_time']) ?> <?= __('v_min') ?>
            </p>
            <p class="text-sm text-gray-500 mt-2">
                <?= number_format($kpiSummary['avg_response_time'] / 60, 1) ?> <?= __('v_hours_average') ?>
            </p>
        </div>

        <!-- Avg Resolution Time -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <p class="text-sm text-gray-600 font-medium"><?= __('v_avg_resolution_time') ?></p>
                <i class="fas fa-stopwatch text-indigo-600"></i>
            </div>
            <p class="text-2xl font-bold text-gray-900">
                <?= number_format($kpiSummary['avg_resolution_time']) ?> <?= __('v_min') ?>
            </p>
            <p class="text-sm text-gray-500 mt-2">
                <?= number_format($kpiSummary['avg_resolution_time'] / 60, 1) ?> <?= __('v_hours_average') ?>
            </p>
        </div>

        <!-- Customer Satisfaction -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <p class="text-sm text-gray-600 font-medium"><?= __('v_customer_satisfaction') ?></p>
                <i class="fas fa-smile text-indigo-600"></i>
            </div>
            <p class="text-2xl font-bold text-gray-900">
                <?= number_format($kpiSummary['avg_satisfaction'], 2) ?> / 5.00
            </p>
            <div class="flex mt-2">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="fas fa-star <?= $i <= round($kpiSummary['avg_satisfaction']) ? 'text-yellow-400' : 'text-gray-300' ?>"></i>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Ticket Volume Trend -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4"><?= __('v_ticket_volume_trend') ?></h3>
            <div style="height: 300px;">
                <canvas id="ticketVolumeChart"></canvas>
            </div>
        </div>

        <!-- SLA Compliance Trend -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4"><?= __('v_sla_compliance_trend') ?></h3>
            <div style="height: 300px;">
                <canvas id="slaComplianceChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Response Time Trend -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4"><?= __('v_average_response_resolution_time_minutes') ?></h3>
        <div style="height: 250px;">
            <canvas id="responseTimeChart"></canvas>
        </div>
    </div>

    <!-- Category Breakdown -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4"><?= __('v_tickets_by_category') ?></h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= __('v_category') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= __('v_tickets') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= __('v_avg_response') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= __('v_avg_resolution') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= __('v_sla_compliance') ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($categoryBreakdown as $cat): ?>
                    <?php
                    $totalSla = $cat['response_met'] + $cat['response_breached'];
                    $compliance = $totalSla > 0 ? round(($cat['response_met'] / $totalSla) * 100, 1) : 0;
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <span class="w-3 h-3 rounded-full mr-2" style="background-color: <?= htmlspecialchars($cat['category_color'] ?? '#6B7280') ?>"></span>
                                <?= htmlspecialchars($cat['category_name'] ?: __('v_uncategorized')) ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?= number_format($cat['ticket_count']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?= number_format($cat['avg_response_time'] ?? 0, 1) ?> <?= __('v_min') ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?= number_format($cat['avg_resolution_time'] ?? 0, 1) ?> <?= __('v_min') ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full
                                <?= $compliance >= 90 ? 'bg-green-100 text-green-800' : ($compliance >= 75 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                <?= number_format($compliance, 1) ?>%
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Agent Performance -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4"><?= __('v_agent_performance') ?></h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= __('v_agent') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= __('v_tickets') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= __('v_resolved_2') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= __('v_avg_response') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= __('v_avg_resolution') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= __('v_sla_success') ?></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?= __('v_rating') ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($agentPerformance as $agent): ?>
                    <?php
                    $totalSla = $agent['response_sla_met'] + $agent['response_sla_breached'];
                    $slaSuccess = $totalSla > 0 ? round(($agent['response_sla_met'] / $totalSla) * 100, 1) : 0;
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div>
                                <p class="font-medium text-gray-900"><?= htmlspecialchars($agent['agent_name']) ?></p>
                                <p class="text-sm text-gray-500"><?= htmlspecialchars($agent['agent_email']) ?></p>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?= number_format($agent['tickets_handled']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?= number_format($agent['tickets_resolved']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?= number_format($agent['avg_response_time'] ?? 0, 1) ?> <?= __('v_min') ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?= number_format($agent['avg_resolution_time'] ?? 0, 1) ?> <?= __('v_min') ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full
                                <?= $slaSuccess >= 90 ? 'bg-green-100 text-green-800' : ($slaSuccess >= 75 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                <?= number_format($slaSuccess, 1) ?>%
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if ($agent['avg_satisfaction']): ?>
                            <div class="flex items-center">
                                <span class="text-sm font-medium text-gray-900 mr-1">
                                    <?= number_format($agent['avg_satisfaction'], 2) ?>
                                </span>
                                <i class="fas fa-star text-yellow-400 text-xs"></i>
                            </div>
                            <?php else: ?>
                            <span class="text-sm text-gray-400"><?= __('v_n_a') ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Ticket Volume Chart
const ticketVolumeCtx = document.getElementById('ticketVolumeChart').getContext('2d');
new Chart(ticketVolumeCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode($trendData['labels']) ?>,
        datasets: [
            {
                label: <?= json_encode(__('v_created')) ?>,
                data: <?= json_encode($trendData['tickets_created']) ?>,
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.3,
                fill: true
            },
            {
                label: <?= json_encode(__('v_resolved_2')) ?>,
                data: <?= json_encode($trendData['tickets_resolved']) ?>,
                borderColor: 'rgb(34, 197, 94)',
                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                tension: 0.3,
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// SLA Compliance Chart
const slaComplianceCtx = document.getElementById('slaComplianceChart').getContext('2d');
new Chart(slaComplianceCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode($trendData['labels']) ?>,
        datasets: [
            {
                label: <?= json_encode(__('v_response_sla')) ?>,
                data: <?= json_encode($trendData['response_compliance']) ?>,
                borderColor: 'rgb(168, 85, 247)',
                backgroundColor: 'rgba(168, 85, 247, 0.1)',
                tension: 0.3,
                fill: true
            },
            {
                label: <?= json_encode(__('v_resolution_sla')) ?>,
                data: <?= json_encode($trendData['resolution_compliance']) ?>,
                borderColor: 'rgb(236, 72, 153)',
                backgroundColor: 'rgba(236, 72, 153, 0.1)',
                tension: 0.3,
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                ticks: {
                    callback: function(value) {
                        return value + '%';
                    }
                }
            }
        }
    }
});

// Response Time Chart
const responseTimeCtx = document.getElementById('responseTimeChart').getContext('2d');
new Chart(responseTimeCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($trendData['labels']) ?>,
        datasets: [
            {
                label: <?= json_encode(__('v_avg_response_time')) ?>,
                data: <?= json_encode($trendData['avg_response_time']) ?>,
                backgroundColor: 'rgba(99, 102, 241, 0.8)',
                borderColor: 'rgb(99, 102, 241)',
                borderWidth: 1
            },
            {
                label: <?= json_encode(__('v_avg_resolution_time')) ?>,
                data: <?= json_encode($trendData['avg_resolution_time']) ?>,
                backgroundColor: 'rgba(244, 63, 94, 0.8)',
                borderColor: 'rgb(244, 63, 94)',
                borderWidth: 1
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value + ' <?= __('v_min') ?>';
                    }
                }
            }
        }
    }
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
