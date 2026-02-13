<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Ticket;
use App\Models\Category;
use App\Models\ActivityLog;
use App\Models\User;

class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireAgent();

        $ticketModel = new Ticket($this->db);
        $ticketModel->setCompanyId($this->companyId());

        $categoryModel = new Category($this->db);
        $categoryModel->setCompanyId($this->companyId());

        $activityModel = new ActivityLog($this->db);
        $activityModel->setCompanyId($this->companyId());

        // Get ticket stats (with role-based filtering)
        $stats = $ticketModel->getStats($this->user());

        // Get tickets by status for chart (with role-based filtering)
        $ticketsByPeriod = $ticketModel->getStatsByPeriod('day', 30, $this->user());

        // Get category distribution
        $categoryStats = $categoryModel->getTicketCounts();

        // Get recent activity
        $recentActivity = $activityModel->getRecent(10);

        // Get recent tickets (with role-based filtering)
        $recentTickets = $ticketModel->getFiltered([], 1, 5, $this->user());

        // Get unassigned tickets count (with role-based filtering)
        $unassignedTickets = $ticketModel->getFiltered(['assigned_to' => 'unassigned'], 1, 5, $this->user());

        // Get SLA near breach tickets (within 60 minutes)
        $slaNearBreach = $ticketModel->getSlaNearBreach(60, $this->user());

        // Get SLA stats
        $slaStats = $ticketModel->getSlaStats($this->user());

        $this->view('dashboard/index', [
            'stats' => $stats,
            'ticketsByPeriod' => $ticketsByPeriod,
            'categoryStats' => $categoryStats,
            'recentActivity' => $recentActivity,
            'recentTickets' => $recentTickets['items'],
            'unassignedTickets' => $unassignedTickets['items'],
            'slaNearBreach' => $slaNearBreach,
            'slaStats' => $slaStats,
        ]);
    }

    public function getStats(): void
    {
        $this->requireAgent();

        $ticketModel = new Ticket($this->db);
        $ticketModel->setCompanyId($this->companyId());

        $stats = $ticketModel->getStats($this->user());
        $this->success($stats);
    }

    public function getChartData(): void
    {
        $this->requireAgent();

        $period = $this->request->query('period', 'day');
        $days = (int) $this->request->query('days', 30);

        $ticketModel = new Ticket($this->db);
        $ticketModel->setCompanyId($this->companyId());

        $data = $ticketModel->getStatsByPeriod($period, $days, $this->user());
        $this->success($data);
    }
}
