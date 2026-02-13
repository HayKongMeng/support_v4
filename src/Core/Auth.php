<?php

namespace App\Core;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth
{
    private Database $db;
    private ?array $user = null;
    private ?array $company = null;
    private string $jwtSecret;

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->jwtSecret = $_ENV['JWT_SECRET'] ?? 'default-secret-change-me';
        $this->loadUserFromSession();
    }

    private function loadUserFromSession(): void
    {
        if (isset($_SESSION['user_id'])) {
            $this->user = $this->db->selectOne(
                "SELECT u.*, c.name as company_name, c.slug as company_slug
                 FROM users u
                 JOIN companies c ON u.company_id = c.id
                 WHERE u.id = ? AND u.is_active = 1",
                [$_SESSION['user_id']]
            );

            if ($this->user) {
                $this->company = $this->db->selectOne(
                    "SELECT * FROM companies WHERE id = ?",
                    [$this->user['company_id']]
                );
            }
        }
    }

    public function attempt(string $email, string $password, int $companyId = null): bool
    {
        $sql = "SELECT u.*, c.name as company_name, c.slug as company_slug
                FROM users u
                JOIN companies c ON u.company_id = c.id
                WHERE u.email = ? AND u.is_active = 1";
        $params = [$email];

        if ($companyId) {
            $sql .= " AND u.company_id = ?";
            $params[] = $companyId;
        }

        $user = $this->db->selectOne($sql, $params);

        if ($user && password_verify($password, $user['password_hash'])) {
            $this->login($user);
            return true;
        }

        return false;
    }

    public function login(array $user): void
    {
        $_SESSION['user_id'] = $user['id'];
        $this->user = $user;

        $this->company = $this->db->selectOne(
            "SELECT * FROM companies WHERE id = ?",
            [$user['company_id']]
        );

        // Update last login
        $this->db->update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
    }

    public function logout(): void
    {
        unset($_SESSION['user_id']);
        $this->user = null;
        $this->company = null;
        session_destroy();
    }

    public function check(): bool
    {
        return $this->user !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function user(): ?array
    {
        return $this->user;
    }

    public function company(): ?array
    {
        return $this->company;
    }

    public function id(): ?int
    {
        return $this->user['id'] ?? null;
    }

    public function companyId(): ?int
    {
        return $this->user['company_id'] ?? null;
    }

    public function isRole(string $role): bool
    {
        return $this->user && $this->user['role'] === $role;
    }

    public function isAdmin(): bool
    {
        return $this->isRole('admin') || $this->isRole('super_admin');
    }

    public function isAgent(): bool
    {
        return in_array($this->user['role'] ?? '', ['admin', 'super_admin', 'agent', 'front_office_agent', 'back_office_agent']);
    }

    public function isFrontOfficeAgent(): bool
    {
        return $this->isRole('front_office_agent');
    }

    public function isBackOfficeAgent(): bool
    {
        return $this->isRole('back_office_agent');
    }

    public function isRestrictedAgent(): bool
    {
        return in_array($this->user['role'] ?? '', ['agent', 'front_office_agent', 'back_office_agent']);
    }

    public function isCustomer(): bool
    {
        return $this->isRole('customer');
    }

    // JWT Token methods for API
    public function createToken(array $user, int $expiry = 86400): string
    {
        $payload = [
            'iss' => $_ENV['APP_URL'] ?? 'support-desk',
            'sub' => $user['id'],
            'company_id' => $user['company_id'],
            'role' => $user['role'],
            'iat' => time(),
            'exp' => time() + $expiry,
        ];

        return JWT::encode($payload, $this->jwtSecret, 'HS256');
    }

    public function validateToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            return (array) $decoded;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function authenticateFromToken(): bool
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            $payload = $this->validateToken($token);

            if ($payload && isset($payload['sub'])) {
                $user = $this->db->selectOne(
                    "SELECT u.*, c.name as company_name, c.slug as company_slug
                     FROM users u
                     JOIN companies c ON u.company_id = c.id
                     WHERE u.id = ? AND u.is_active = 1",
                    [$payload['sub']]
                );

                if ($user) {
                    $this->user = $user;
                    $this->company = $this->db->selectOne(
                        "SELECT * FROM companies WHERE id = ?",
                        [$user['company_id']]
                    );
                    return true;
                }
            }
        }

        return false;
    }

    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    }
}
