<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Category;
use App\Models\User;

class CategoryController extends Controller
{
    private Category $categoryModel;

    protected function init(): void
    {
        $this->categoryModel = new Category($this->db);
        $this->categoryModel->setCompanyId($this->companyId());
    }

    public function index(): void
    {
        $this->requireAdmin();
        $this->init();

        $categories = $this->categoryModel->getActive();
        $categoryStats = $this->categoryModel->getTicketCounts();

        $this->view('settings/categories', [
            'categories' => $categories,
            'categoryStats' => $categoryStats,
        ]);
    }

    public function store(): void
    {
        $this->requireAdmin();
        $this->init();

        $data = $this->request->only([
            'name', 'description', 'color', 'auto_assign_to',
            'keywords', 'sla_response_hours', 'sla_resolve_hours'
        ]);

        // Validate
        if (empty($data['name'])) {
            if ($this->request->isAjax()) {
                $this->error(__('category_name_required'), 422);
                return;
            }
            $this->response->withError(__('category_name_required'));
            $this->redirect($this->app->url('settings/categories'));
            return;
        }

        // Process keywords
        if (!empty($data['keywords'])) {
            $keywords = array_map('trim', explode(',', $data['keywords']));
            $data['keywords'] = json_encode(array_filter($keywords));
        }

        $categoryId = $this->categoryModel->create([
            'company_id' => $this->companyId(),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? '#6366f1',
            'auto_assign_to' => $data['auto_assign_to'] ?: null,
            'keywords' => $data['keywords'] ?? null,
            'sla_response_hours' => $data['sla_response_hours'] ?? 24,
            'sla_resolve_hours' => $data['sla_resolve_hours'] ?? 72,
            'is_active' => 1,
        ]);

        if ($this->request->isAjax()) {
            $this->success(['id' => $categoryId], __('category_created_success'));
            return;
        }

        $this->response->withSuccess(__('category_created_success'));
        $this->redirect($this->app->url('settings/categories'));
    }

    public function update(string $id): void
    {
        $this->requireAdmin();
        $this->init();

        $category = $this->categoryModel->find((int) $id);
        if (!$category) {
            $this->error(__('category_not_found'), 404);
            return;
        }

        $data = $this->request->only([
            'name', 'description', 'color', 'auto_assign_to',
            'keywords', 'sla_response_hours', 'sla_resolve_hours', 'is_active'
        ]);

        // Process keywords
        if (isset($data['keywords'])) {
            if (!empty($data['keywords'])) {
                $keywords = array_map('trim', explode(',', $data['keywords']));
                $data['keywords'] = json_encode(array_filter($keywords));
            } else {
                $data['keywords'] = null;
            }
        }

        $this->categoryModel->update((int) $id, $data);

        if ($this->request->isAjax()) {
            $this->success([], __('category_updated_success'));
            return;
        }

        $this->response->withSuccess(__('category_updated_success'));
        $this->redirect($this->app->url('settings/categories'));
    }

    public function delete(string $id): void
    {
        $this->requireAdmin();
        $this->init();

        $category = $this->categoryModel->find((int) $id);
        if (!$category) {
            $this->error(__('category_not_found'), 404);
            return;
        }

        // Soft delete - just deactivate
        $this->categoryModel->update((int) $id, ['is_active' => 0]);

        if ($this->request->isAjax()) {
            $this->success([], __('category_deleted_success'));
            return;
        }

        $this->response->withSuccess(__('category_deleted_success'));
        $this->redirect($this->app->url('settings/categories'));
    }

    public function apiList(): void
    {
        $this->requireAgent();
        $this->init();

        $categories = $this->categoryModel->getActive();
        $this->success($categories);
    }
}
