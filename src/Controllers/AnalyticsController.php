<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Analytics;
use App\Models\SlaPolicy;

class AnalyticsController extends Controller
{
    private Analytics $analyticsModel;
    private SlaPolicy $slaModel;

    protected function init(): void
    {
        $this->analyticsModel = new Analytics($this->db);
        $this->analyticsModel->setCompanyId($this->companyId());

        $this->slaModel = new SlaPolicy($this->db);
        $this->slaModel->setCompanyId($this->companyId());
    }

    public function index(): void
    {
        $this->requireAgent();
        $this->init();

        // Get period from query (default 30 days)
        $period = $this->request->query('period', '30');
        $days = match ($period) {
            '7' => 7,
            '30' => 30,
            '90' => 90,
            default => 30
        };

        // Build missing snapshots on-demand so analytics is never empty.
        $this->analyticsModel->ensureDailySnapshots($this->companyId(), $days);

        // Get KPI summary
        $kpiSummary = $this->analyticsModel->getKpiSummary($this->companyId(), $days);

        // Get trend data for charts
        $trendData = $this->analyticsModel->getTrendData($this->companyId(), $days);

        // Get SLA compliance metrics
        $slaCompliance = $this->slaModel->getComplianceRate($this->companyId(), "{$days}days");

        // Get category breakdown
        $categoryBreakdown = $this->analyticsModel->getCategoryBreakdown($this->companyId(), $days);

        // Get agent performance
        $agentPerformance = $this->analyticsModel->getAgentPerformance($this->companyId(), $days);

        $this->view('analytics/index', [
            'period' => $period,
            'days' => $days,
            'kpiSummary' => $kpiSummary,
            'trendData' => $trendData,
            'slaCompliance' => $slaCompliance,
            'categoryBreakdown' => $categoryBreakdown,
            'agentPerformance' => $agentPerformance,
        ]);
    }

    /**
     * Export analytics report (CSV)
     */
    public function export(): void
    {
        $this->requireAgent();
        $this->init();

        $format = $this->request->query('format', 'csv');
        $period = $this->request->query('period', '30');
        $days = match ($period) {
            '7' => 7,
            '30' => 30,
            '90' => 90,
            default => 30
        };

        // Build missing snapshots before export.
        $this->analyticsModel->ensureDailySnapshots($this->companyId(), $days);

        $kpiSummary = $this->analyticsModel->getKpiSummary($this->companyId(), $days);
        $categoryBreakdown = $this->analyticsModel->getCategoryBreakdown($this->companyId(), $days);
        $agentPerformance = $this->analyticsModel->getAgentPerformance($this->companyId(), $days);
        $slaCompliance = $this->slaModel->getComplianceRate($this->companyId(), "{$days}days");

        if ($format === 'csv') {
            $this->exportCsv($kpiSummary, $categoryBreakdown, $agentPerformance, $slaCompliance, $days);
            return;
        }

        $this->error(__('unsupported_format'), 400);
    }

    private function exportCsv($kpiSummary, $categoryBreakdown, $agentPerformance, $slaCompliance, $days): void
    {
        $filename = 'analytics_report_' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // Header
        fputcsv($output, ['Analytics Report']); 
        fputcsv($output, ['Generated', date('Y-m-d H:i:s')]);
        fputcsv($output, ['Period', "Last {$days} days"]);
        fputcsv($output, []);

        // KPI Summary
        fputcsv($output, ['=== KPI SUMMARY (ISO 20000 Standard) ===']);
        fputcsv($output, ['Metric', 'Value']);
        fputcsv($output, ['Total Tickets Created', $kpiSummary['total_created']]);
        fputcsv($output, ['Total Tickets Resolved', $kpiSummary['total_resolved']]);
        fputcsv($output, ['Total Tickets Closed', $kpiSummary['total_closed']]);
        fputcsv($output, ['Resolution Rate', $kpiSummary['resolution_rate'] . '%']);
        fputcsv($output, ['Response SLA Compliance', $kpiSummary['response_sla_compliance'] . '%']);
        fputcsv($output, ['Resolution SLA Compliance', $kpiSummary['resolution_sla_compliance'] . '%']);
        fputcsv($output, ['Avg Response Time (minutes)', $kpiSummary['avg_response_time']]);
        fputcsv($output, ['Avg Resolution Time (minutes)', $kpiSummary['avg_resolution_time']]);
        fputcsv($output, ['Avg Customer Satisfaction (1-5)', $kpiSummary['avg_satisfaction']]);
        fputcsv($output, ['Avg Tickets Per Agent', $kpiSummary['avg_tickets_per_agent']]);
        fputcsv($output, []);

        // SLA Compliance Details
        fputcsv($output, ['=== SLA COMPLIANCE (ITIL Standard) ===']);
        fputcsv($output, ['Metric', 'Value']);
        fputcsv($output, ['Total Tickets', $slaCompliance['total_tickets']]);
        fputcsv($output, ['Response SLA Met', $slaCompliance['response_met']]);
        fputcsv($output, ['Response SLA Breached', $slaCompliance['response_breached']]);
        fputcsv($output, ['Response Compliance Rate', $slaCompliance['response_compliance'] . '%']);
        fputcsv($output, ['Resolution SLA Met', $slaCompliance['resolution_met']]);
        fputcsv($output, ['Resolution SLA Breached', $slaCompliance['resolution_breached']]);
        fputcsv($output, ['Resolution Compliance Rate', $slaCompliance['resolution_compliance'] . '%']);
        fputcsv($output, []);

        // Category Breakdown
        fputcsv($output, ['=== TICKETS BY CATEGORY ===']);
        fputcsv($output, ['Category', 'Tickets', 'Avg Response (min)', 'Avg Resolution (min)', 'SLA Met', 'SLA Breached']);
        foreach ($categoryBreakdown as $cat) {
            fputcsv($output, [
                $cat['category_name'] ?: 'Uncategorized',
                $cat['ticket_count'],
                round($cat['avg_response_time'], 2),
                round($cat['avg_resolution_time'], 2),
                $cat['response_met'],
                $cat['response_breached']
            ]);
        }
        fputcsv($output, []);

        // Agent Performance
        fputcsv($output, ['=== AGENT PERFORMANCE ===']);
        fputcsv($output, ['Agent', 'Email', 'Tickets', 'Resolved', 'Avg Response (min)', 'Avg Resolution (min)', 'SLA Met', 'SLA Breached', 'Avg Rating']);
        foreach ($agentPerformance as $agent) {
            fputcsv($output, [
                $agent['agent_name'],
                $agent['agent_email'],
                $agent['tickets_handled'],
                $agent['tickets_resolved'],
                round($agent['avg_response_time'] ?? 0, 2),
                round($agent['avg_resolution_time'] ?? 0, 2),
                $agent['response_sla_met'],
                $agent['response_sla_breached'],
                round($agent['avg_satisfaction'] ?? 0, 2)
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * API endpoint for real-time SLA dashboard
     */
    public function slaStatus(): void
    {
        $this->requireAgent();
        $this->init();

        $ticketModel = new \App\Models\Ticket($this->db);
        $ticketModel->setCompanyId($this->companyId());

        // Get tickets near SLA breach (within 60 minutes)
        $nearBreach = $ticketModel->getSlaNearBreach(60, $this->auth->user());

        // Get overall SLA stats
        $slaStats = $ticketModel->getSlaStats($this->auth->user());

        $this->success([
            'near_breach' => $nearBreach,
            'stats' => $slaStats,
        ]);
    }
}
