<?php

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\Ticket;

class SearchController extends Controller
{
    /**
     * GET /api/search?q=...
     *
     * Returns up to 8 ticket results matching the query.
     * Respects company isolation and agent-level row restrictions.
     */
    public function search(): void
    {
        $q = trim($this->request->query('q', ''));

        if (strlen($q) < 2) {
            $this->success(['results' => []], 'ok');
            return;
        }

        $ticketModel = new Ticket($this->db);
        $ticketModel->setCompanyId($this->auth->companyId());

        $user = $this->auth->isRestrictedAgent() ? $this->auth->user() : null;

        $result = $ticketModel->getFiltered(
            ['search' => $q],
            1,
            8,
            $user
        );

        $simplified = array_map(function ($t) {
            return [
                'id'             => $t['id'],
                'ticket_number'  => $t['ticket_number'],
                'subject'        => $t['subject'],
                'status'         => $t['status'],
                'priority'       => $t['priority'],
                'requester_name' => $t['requester_name'] ?? $t['requester_name_full'] ?? '',
                'category_name'  => $t['category_name'] ?? '',
                'category_color' => $t['category_color'] ?? '#6b7280',
            ];
        }, $result['items']);

        $this->success(['results' => $simplified], 'ok');
    }
}
