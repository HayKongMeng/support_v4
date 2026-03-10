<?php

namespace App\Models;

class PushSubscription extends Model
{
    protected $table = 'push_subscriptions';
    protected $fillable = ['user_id', 'endpoint', 'auth_token', 'p256dh_key', 'is_active'];

    /**
     * Subscribe a user to push notifications
     */
    public static function subscribe($userId, $subscriptionData)
    {
        // Get database instance
        global $GLOBALS;
        $db = $GLOBALS['db'] ?? null;
        if (!$db) {
            throw new \Exception('Database instance not available');
        }

        // Check if endpoint already exists
        $existing = $db->selectOne(
            "SELECT * FROM push_subscriptions WHERE endpoint = ?",
            [$subscriptionData['endpoint']]
        );

        if ($existing) {
            $db->update('push_subscriptions', [
                'auth_token' => $subscriptionData['keys']['auth'],
                'p256dh_key' => $subscriptionData['keys']['p256dh'],
                'is_active' => 1,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'endpoint = ?', [$subscriptionData['endpoint']]);
            return $existing;
        }

        $db->insert('push_subscriptions', [
            'user_id' => $userId,
            'endpoint' => $subscriptionData['endpoint'],
            'auth_token' => $subscriptionData['keys']['auth'],
            'p256dh_key' => $subscriptionData['keys']['p256dh'],
            'is_active' => 1
        ]);

        return [
            'user_id' => $userId,
            'endpoint' => $subscriptionData['endpoint'],
            'auth_token' => $subscriptionData['keys']['auth'],
            'p256dh_key' => $subscriptionData['keys']['p256dh'],
            'is_active' => 1
        ];
    }

    /**
     * Unsubscribe a user from push notifications
     */
    public static function unsubscribe($userId, $endpoint)
    {
        global $GLOBALS;
        $db = $GLOBALS['db'] ?? null;
        if (!$db) {
            throw new \Exception('Database instance not available');
        }

        return $db->update(
            'push_subscriptions',
            ['is_active' => 0],
            'user_id = ? AND endpoint = ?',
            [$userId, $endpoint]
        );
    }

    /**
     * Get all active subscriptions for a user
     */
    public static function getActiveForUser($userId)
    {
        global $GLOBALS;
        $db = $GLOBALS['db'] ?? null;
        if (!$db) {
            throw new \Exception('Database instance not available');
        }

        return $db->select(
            "SELECT * FROM push_subscriptions WHERE user_id = ? AND is_active = 1",
            [$userId]
        );
    }

    /**
     * Get all active subscriptions for multiple users
     */
    public static function getActiveForUsers($userIds)
    {
        global $GLOBALS;
        $db = $GLOBALS['db'] ?? null;
        if (!$db) {
            throw new \Exception('Database instance not available');
        }

        if (empty($userIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        return $db->select(
            "SELECT * FROM push_subscriptions WHERE user_id IN ($placeholders) AND is_active = 1",
            $userIds
        );
    }
}
