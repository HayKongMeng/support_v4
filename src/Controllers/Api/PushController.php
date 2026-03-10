<?php

namespace App\Controllers\Api;

use App\Core\Controller;

class PushController extends Controller
{
    /**
     * Subscribe user to push notifications
     * POST /api/push/subscribe
     */
    public function subscribe()
    {
        $user = $this->auth->user();

        if (!$user) {
            return $this->response->json(['success' => false, 'error' => __('unauthorized')], 401);
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || !isset($data['endpoint']) || !isset($data['keys'])) {
            return $this->response->json(['success' => false, 'error' => __('push_invalid_subscription_data')], 400);
        }

        try {
            // Check if endpoint already exists
            $existing = $this->db->selectOne(
                "SELECT id FROM push_subscriptions WHERE endpoint = ?",
                [$data['endpoint']]
            );

            if ($existing) {
                // Update existing subscription
                $this->db->update(
                    'push_subscriptions',
                    [
                        'auth_token' => $data['keys']['auth'] ?? '',
                        'p256dh_key' => $data['keys']['p256dh'] ?? '',
                        'is_active' => 1,
                        'updated_at' => date('Y-m-d H:i:s')
                    ],
                    'endpoint = ?',
                    [$data['endpoint']]
                );
            } else {
                // Insert new subscription
                $this->db->insert('push_subscriptions', [
                    'user_id' => $user['id'],
                    'endpoint' => $data['endpoint'],
                    'auth_token' => $data['keys']['auth'] ?? '',
                    'p256dh_key' => $data['keys']['p256dh'] ?? '',
                    'is_active' => 1
                ]);
            }

            return $this->response->json(['success' => true, 'message' => __('push_subscription_successful')]);
        } catch (\Exception $e) {
            return $this->response->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Unsubscribe user from push notifications
     * POST /api/push/unsubscribe
     */
    public function unsubscribe()
    {
        $user = $this->auth->user();

        if (!$user) {
            return $this->response->json(['success' => false, 'error' => __('unauthorized')], 401);
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || !isset($data['endpoint'])) {
            return $this->response->json(['success' => false, 'error' => __('invalid_request')], 400);
        }

        try {
            $this->db->update(
                'push_subscriptions',
                ['is_active' => 0],
                'user_id = ? AND endpoint = ?',
                [$user['id'], $data['endpoint']]
            );

            return $this->response->json(['success' => true, 'message' => __('push_unsubscription_successful')]);
        } catch (\Exception $e) {
            return $this->response->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get push public key for subscription
     * GET /api/push/public-key
     */
    public function publicKey()
    {
        $publicKey = $_ENV['PUSH_PUBLIC_KEY'] ?? null;

        if (!$publicKey) {
            return $this->response->json(['success' => false, 'error' => __('push_not_configured')], 500);
        }

        return $this->response->json(['success' => true, 'publicKey' => $publicKey]);
    }

    /**
     * Send test push notification
     * POST /api/push/test
     */
    public function testNotification()
    {
        $user = $this->auth->user();

        if (!$user) {
            return $this->response->json(['success' => false, 'error' => __('unauthorized')], 401);
        }

        try {
            $pushService = new \App\Services\PushNotificationService($this->db);
            $result = $pushService->sendToUsers(
                [$user['id']],
                '🧪 Test Notification',
                'This is a test push notification. If you see this, push notifications are working!',
                ['type' => 'test']
            );

            return $this->response->json([
                'success' => $result['success'],
                'message' => $result['success'] 
                    ? __('push_test_notification_sent')
                    : __('push_test_notification_failed'),
                'details' => $result
            ]);
        } catch (\Exception $e) {
            return $this->response->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
