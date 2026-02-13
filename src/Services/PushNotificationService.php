<?php

namespace App\Services;

class PushNotificationService
{
    private $publicKey;
    private $privateKey;
    private $db;

    public function __construct($db = null)
    {
        $this->publicKey = $_ENV['PUSH_PUBLIC_KEY'] ?? null;
        $this->privateKey = $_ENV['PUSH_PRIVATE_KEY'] ?? null;
        $this->db = $db;
        
        error_log("[PushService] __construct called");
        error_log("[PushService] Public key set: " . ($this->publicKey ? "YES (" . strlen($this->publicKey) . " chars)" : "NO"));
        error_log("[PushService] Private key set: " . ($this->privateKey ? "YES" : "NO"));
        error_log("[PushService] DB available: " . ($this->db ? "YES" : "NO"));
    }

    /**
     * Send push notification to specific users
     */
    public function sendToUsers($userIds, $title, $message, $data = [])
    {
        $debugFile = dirname(__DIR__, 2) . '/storage/logs/miniapp_debug.log';
        $timestamp = date('Y-m-d H:i:s');
        
        if (!$this->publicKey || !$this->privateKey) {
            file_put_contents($debugFile, "[{$timestamp}] [PushService] Push keys not configured. Public: " . ($this->publicKey ? 'YES' : 'NO') . ", Private: " . ($this->privateKey ? 'YES' : 'NO') . "\n", FILE_APPEND);
            return ['success' => false, 'error' => 'Push keys not configured'];
        }

        if (!$this->db) {
            file_put_contents($debugFile, "[{$timestamp}] [PushService] Database connection not available\n", FILE_APPEND);
            return ['success' => false, 'error' => 'Database connection not available'];
        }

        file_put_contents($debugFile, "[{$timestamp}] [PushService] Getting subscriptions for user IDs: " . json_encode($userIds) . "\n", FILE_APPEND);
        $subscriptions = $this->getActiveSubscriptionsForUsers($userIds);
        file_put_contents($debugFile, "[{$timestamp}] [PushService] Found " . count($subscriptions) . " active subscriptions\n", FILE_APPEND);
        
        $results = [];

        foreach ($subscriptions as $subscription) {
            file_put_contents($debugFile, "[{$timestamp}] [PushService] Processing subscription for user: " . ($subscription['user_id'] ?? 'UNKNOWN') . "\n", FILE_APPEND);
            $result = $this->sendToSubscription($subscription, $title, $message, $data);
            $results[] = $result;
            file_put_contents($debugFile, "[{$timestamp}] [PushService] Send result: " . json_encode($result) . "\n", FILE_APPEND);

            // Mark as inactive if endpoint no longer valid (410 Gone)
            if (!$result['success'] && ($result['error_code'] ?? null) === 410) {
                file_put_contents($debugFile, "[{$timestamp}] [PushService] Marking subscription as inactive (410 Gone)\n", FILE_APPEND);
                $this->db->update(
                    'push_subscriptions',
                    ['is_active' => 0],
                    'endpoint = ?',
                    [$subscription['endpoint']]
                );
            }
        }

        return [
            'success' => true,
            'sent' => count(array_filter($results, fn($r) => $r['success'] ?? false)),
            'failed' => count(array_filter($results, fn($r) => !($r['success'] ?? false))),
            'details' => $results
        ];
    }

    /**
     * Get active subscriptions for users
     */
    private function getActiveSubscriptionsForUsers($userIds)
    {
        $debugFile = dirname(__DIR__, 2) . '/storage/logs/miniapp_debug.log';
        $timestamp = date('Y-m-d H:i:s');
        
        if (empty($userIds) || !$this->db) {
            file_put_contents($debugFile, "[{$timestamp}] [PushService] Skipping subscriptions query - UserIds empty: " . (empty($userIds) ? 'YES' : 'NO') . ", DB available: " . ($this->db ? 'YES' : 'NO') . "\n", FILE_APPEND);
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $query = "SELECT * FROM push_subscriptions WHERE user_id IN ($placeholders) AND is_active = 1";
        file_put_contents($debugFile, "[{$timestamp}] [PushService] Executing query: $query with params: " . json_encode($userIds) . "\n", FILE_APPEND);
        
        try {
            $results = $this->db->select($query, $userIds);
            file_put_contents($debugFile, "[{$timestamp}] [PushService] Query returned " . count($results) . " rows\n", FILE_APPEND);
            
            if (empty($results)) {
                file_put_contents($debugFile, "[{$timestamp}] [PushService] NO SUBSCRIPTIONS FOUND for user IDs: " . json_encode($userIds) . "\n", FILE_APPEND);
                file_put_contents($debugFile, "[{$timestamp}] [PushService] Checking total subscriptions in database...\n", FILE_APPEND);
                $allSubs = $this->db->select("SELECT user_id, COUNT(*) as count FROM push_subscriptions GROUP BY user_id", []);
                file_put_contents($debugFile, "[{$timestamp}] [PushService] All subscriptions by user: " . json_encode($allSubs) . "\n", FILE_APPEND);
                
                // Also check if table is empty
                $totalCount = $this->db->selectOne("SELECT COUNT(*) as cnt FROM push_subscriptions", []);
                file_put_contents($debugFile, "[{$timestamp}] [PushService] Total push_subscriptions in DB: " . ($totalCount['cnt'] ?? 0) . "\n", FILE_APPEND);
            }
            
            return $results;
        } catch (\Exception $e) {
            file_put_contents($debugFile, "[{$timestamp}] [PushService] Database query error: " . $e->getMessage() . "\n", FILE_APPEND);
            return [];
        }
    }

    /**
     * Send push notification to individual subscription
     */
    private function sendToSubscription($subscription, $title, $message, $data = [])
    {
        try {
            $endpoint = $subscription['endpoint'] ?? $subscription->endpoint ?? null;
            if (!$endpoint) {
                return [
                    'success' => false,
                    'error' => 'No endpoint found in subscription'
                ];
            }

            error_log("[PushService] Sending to endpoint: " . substr($endpoint, 0, 50) . "...");

            $payload = json_encode([
                'title' => $title,
                'body' => $message,
                'icon' => '/favicon.ico',
                'badge' => '/favicon.ico',
                'tag' => 'ticket-notification',
                'requireInteraction' => true,
                'data' => array_merge([
                    'url' => $_ENV['APP_URL'] ?? 'https://localhost',
                    'timestamp' => time()
                ], $data)
            ]);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $endpoint,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'TTL: 24',
                    'Urgency: high',
                    'Authorization: vapid t=' . $this->getVapidToken() . ', k=' . $this->publicKey
                ],
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_TIMEOUT => 10
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 201) {
                return ['success' => true];
            }

            return [
                'success' => false,
                'error_code' => $httpCode,
                'response' => $response
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate VAPID JWT token
     */
    private function getVapidToken()
    {
        $header = [
            'typ' => 'JWT',
            'alg' => 'ES256'
        ];

        $payload = [
            'aud' => 'https://fcm.googleapis.com',
            'exp' => time() + 3600,
            'sub' => 'mailto:contact@example.com'
        ];

        // Simple base64 encoding for auth header
        $headerEncoded = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
        $payloadEncoded = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');

        return $headerEncoded . '.' . $payloadEncoded;
    }

    /**
     * Send to all active agents
     */
    public function sendToAgents($agents, $title, $message, $data = [])
    {
        $userIds = array_map(fn($agent) => $agent['id'] ?? $agent->id, $agents);
        return $this->sendToUsers($userIds, $title, $message, $data);
    }
}
