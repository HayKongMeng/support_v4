<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;
use App\Models\Company;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        if ($this->auth->check()) {
            $this->redirectToDashboard();
            return;
        }
        $this->view('auth/login');
    }

    public function login(): void
    {
        $email = $this->request->input('email');
        $password = $this->request->input('password');

        if (empty($email) || empty($password)) {
            $this->response->withError(__('auth_email_password_required'))->withInput();
            $this->redirect($this->app->url('login'));
            return;
        }

        if ($this->auth->attempt($email, $password)) {
            $this->redirectToDashboard();
            return;
        }

        $this->response->withError(__('auth_invalid_email_or_password'))->withInput();
        $this->redirect($this->app->url('login'));
    }

    public function showRegister(): void
    {
        if ($this->auth->check()) {
            $this->redirectToDashboard();
            return;
        }
        $this->view('auth/register');
    }

    public function register(): void
    {
        $data = $this->request->only(['name', 'email', 'password', 'password_confirmation', 'company_name']);

        // Validate
        $errors = [];
        if (empty($data['name'])) $errors['name'] = __('name_required');
        if (empty($data['email'])) $errors['email'] = __('email_required');
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = __('invalid_email_format');
        if (empty($data['password'])) $errors['password'] = __('password_required');
        if (strlen($data['password']) < 6) $errors['password'] = __('password_min_chars', ['min' => 6]);
        if ($data['password'] !== $data['password_confirmation']) $errors['password'] = __('passwords_do_not_match');
        if (empty($data['company_name'])) $errors['company_name'] = __('company_name_required');

        if (!empty($errors)) {
            $_SESSION['validation_errors'] = $errors;
            $this->response->withError(__('please_fix_errors'))->withInput();
            $this->redirect($this->app->url('register'));
            return;
        }

        // Create company
        $companyModel = new Company($this->db);
        $companyId = $companyModel->create([
            'name' => $data['company_name'],
            'slug' => $companyModel->generateSlug($data['company_name']),
            'settings' => json_encode([
                'ticket_prefix' => 'TKT',
                'ai_categorization_enabled' => true,
            ]),
        ]);

        // Create admin user
        $userModel = new User($this->db);
        $userId = $userModel->create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => $this->auth->hashPassword($data['password']),
            'role' => 'admin',
            'is_active' => 1,
        ]);

        // Create default categories
        $this->createDefaultCategories($companyId);

        // Log in the user
        $user = $userModel->find($userId);
        $this->auth->login($user);

        $this->response->withSuccess(__('auth_account_created_welcome'));
        $this->redirect($this->app->url('dashboard'));
    }

    private function createDefaultCategories(int $companyId): void
    {
        $categories = [
            ['name' => 'Technical Support', 'color' => '#ef4444', 'keywords' => ['error', 'bug', 'crash', 'not working', 'broken']],
            ['name' => 'Billing', 'color' => '#22c55e', 'keywords' => ['invoice', 'payment', 'bill', 'refund', 'subscription']],
            ['name' => 'Sales', 'color' => '#3b82f6', 'keywords' => ['buy', 'purchase', 'quote', 'demo', 'pricing']],
            ['name' => 'General Inquiry', 'color' => '#8b5cf6', 'keywords' => ['question', 'information', 'help', 'how to']],
        ];

        foreach ($categories as $category) {
            $this->db->insert('categories', [
                'company_id' => $companyId,
                'name' => $category['name'],
                'color' => $category['color'],
                'keywords' => json_encode($category['keywords']),
                'is_active' => 1,
            ]);
        }
    }

    public function logout(): void
    {
        $this->auth->logout();
        $this->redirect($this->app->url('login'));
    }

    private function redirectToDashboard(): void
    {
        if ($this->auth->isCustomer()) {
            $this->redirect($this->app->url('customer/tickets'));
        } else {
            $this->redirect($this->app->url('dashboard'));
        }
    }

    // API endpoints for JWT authentication
    public function apiLogin(): void
    {
        $email = $this->request->input('email');
        $password = $this->request->input('password');

        if (empty($email) || empty($password)) {
            $this->error(__('auth_email_password_required'), 400);
            return;
        }

        $userModel = new User($this->db);
        $user = $userModel->findByEmail($email);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->error(__('auth_invalid_credentials'), 401);
            return;
        }

        if (!$user['is_active']) {
            $this->error(__('auth_account_disabled'), 403);
            return;
        }

        $token = $this->auth->createToken($user);

        $this->success([
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ],
        ], __('auth_login_successful'));
    }
}
