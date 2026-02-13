<?php

namespace App\Controllers\Api;

use App\Core\App;

class NotificationController
{
    private App $app;

    public function __construct()
    {
        $this->app = App::getInstance();
    }

    /**
     * Get notifications for current user
     */
    public function index(): void
    {
        header('Content-Type: application/json');

        $userId = $this->app->auth()->id();
        $db = $this->app->db();

        // Get recent notifications (last 20)
        $notifications = $db->select(
            "SELECT n.*, t.ticket_number
             FROM notifications n
             LEFT JOIN tickets t ON n.ticket_id = t.id
             WHERE n.user_id = ?
             ORDER BY n.created_at DESC
             LIMIT 20",
            [$userId]
        );

        // Get unread count
        $unreadCount = $db->selectOne(
            "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND read_at IS NULL",
            [$userId]
        );

        echo json_encode([
            'success' => true,
            'data' => [
                'notifications' => $notifications,
                'unread_count' => (int) ($unreadCount['count'] ?? 0)
            ]
        ]);
    }

    /**
     * Mark a notification as read
     */
    public function markRead(string $id): void
    {
        header('Content-Type: application/json');

        $userId = $this->app->auth()->id();
        $db = $this->app->db();

        $db->query(
            "UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ?",
            [(int) $id, $userId]
        );

        echo json_encode(['success' => true]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllRead(): void
    {
        header('Content-Type: application/json');

        $userId = $this->app->auth()->id();
        $db = $this->app->db();

        $db->query(
            "UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL",
            [$userId]
        );

        echo json_encode(['success' => true]);
    }
}
