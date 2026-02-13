<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\KnowledgeBase;
use App\Models\Category;

class KnowledgeBaseController extends Controller
{
    private KnowledgeBase $kbModel;
    private Category $categoryModel;

    protected function init(): void
    {
        $this->kbModel = new KnowledgeBase($this->db);
        $this->kbModel->setCompanyId($this->companyId());

        $this->categoryModel = new Category($this->db);
        $this->categoryModel->setCompanyId($this->companyId());
    }

    /**
     * List all published KB articles (public view)
     */
    public function index(): void
    {
        $this->requireAuth();
        $this->init();

        $search = $this->request->input('search');
        $categoryId = $this->request->input('category');
        $page = max(1, (int) $this->request->input('page', 1));
        $perPage = 12;

        $total = $this->kbModel->countPublished($search, $categoryId);
        $articles = $this->kbModel->getPublished($perPage, ($page - 1) * $perPage, $search, $categoryId);
        $categories = $this->categoryModel->getActive();
        $featured = $this->kbModel->getFeatured(3);
        $popular = $this->kbModel->getPopular(5);

        $this->view('kb/index', [
            'articles' => $articles,
            'categories' => $categories,
            'featured' => $featured,
            'popular' => $popular,
            'search' => $search,
            'categoryId' => $categoryId,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => ceil($total / $perPage),
            ],
        ]);
    }

    /**
     * Show single article
     */
    public function show(string $slug): void
    {
        $this->requireAuth();
        $this->init();

        $article = $this->kbModel->findBySlug($slug);

        if (!$article) {
            $this->error('Article not found', 404);
            return;
        }

        // Only show published articles to non-agents
        if (!$article['is_published'] && !$this->auth->isAgent()) {
            $this->error('Article not found', 404);
            return;
        }

        // Increment views
        $this->kbModel->incrementViews($article['id']);

        // Get related articles from same category
        $related = [];
        if ($article['category_id']) {
            $related = $this->kbModel->getPublished(4, 0, null, $article['category_id']);
            // Remove current article from related
            $related = array_filter($related, fn($a) => $a['id'] !== $article['id']);
            $related = array_slice($related, 0, 3);
        }

        $this->view('kb/show', [
            'article' => $article,
            'related' => $related,
        ]);
    }

    /**
     * Show create form
     */
    public function create(): void
    {
        $this->requireAuth();
        $this->init();

        $categories = $this->categoryModel->getActive();

        $this->view('kb/create', [
            'categories' => $categories,
        ]);
    }

    /**
     * Sanitize HTML content - allow only safe tags
     */
    private function sanitizeHtml(string $html): string
    {
        // Allowed HTML tags
        $allowed = '<p><br><h1><h2><h3><h4><h5><h6><strong><b><em><i><u><s><strike>'
                 . '<ul><ol><li><blockquote><pre><code><a><img><table><thead><tbody><tr><th><td>'
                 . '<span><div><hr>';

        // Strip disallowed tags
        $clean = strip_tags($html, $allowed);

        // Remove any onclick, onerror, etc. event handlers
        $clean = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $clean);

        // Remove javascript: URLs
        $clean = preg_replace('/href\s*=\s*["\']javascript:[^"\']*["\']/i', 'href="#"', $clean);

        return $clean;
    }

    /**
     * Store new article
     */
    public function store(): void
    {
        $this->requireAuth();
        $this->init();

        $data = $this->request->only(['title', 'content', 'category_id', 'tags', 'is_published', 'is_featured']);

        // Validate
        if (empty($data['title']) || strlen($data['title']) < 3) {
            $this->response->withError('Title is required (minimum 3 characters)');
            $this->redirect($this->app->url('kb/create'));
            return;
        }

        if (empty($data['content']) || strlen($data['content']) < 10) {
            $this->response->withError('Content is required (minimum 10 characters)');
            $this->redirect($this->app->url('kb/create'));
            return;
        }

        // Sanitize HTML content
        $content = $this->sanitizeHtml($data['content']);

        // Generate slug
        $slug = $this->kbModel->generateSlug($data['title']);

        // Generate excerpt from plain text
        $excerpt = strip_tags($content);
        $excerpt = html_entity_decode($excerpt);
        $excerpt = trim(preg_replace('/\s+/', ' ', $excerpt));
        $excerpt = strlen($excerpt) > 200 ? substr($excerpt, 0, 200) . '...' : $excerpt;

        // Process tags
        $tags = null;
        if (!empty($data['tags'])) {
            $tagList = array_map('trim', explode(',', $data['tags']));
            $tags = json_encode(array_filter($tagList));
        }

        // Only admins/agents can set featured, customers can only publish
        $isFeatured = $this->auth->isAgent() && !empty($data['is_featured']) ? 1 : 0;
        $isPublished = !empty($data['is_published']) ? 1 : 1; // Default to published

        $articleId = $this->kbModel->create([
            'company_id' => $this->companyId(),
            'author_id' => $this->auth->id(),
            'category_id' => $data['category_id'] ?: null,
            'title' => $data['title'],
            'slug' => $slug,
            'content' => $content,
            'excerpt' => $excerpt,
            'tags' => $tags,
            'is_published' => $isPublished,
            'is_featured' => $isFeatured,
            'published_at' => date('Y-m-d H:i:s'),
        ]);

        $this->response->withSuccess('Article published successfully');
        $this->redirect($this->app->url("kb/{$slug}"));
    }

    /**
     * Show edit form
     */
    public function edit(string $id): void
    {
        $this->requireAuth();
        $this->init();

        $article = $this->kbModel->findWithAuthor((int) $id);

        if (!$article) {
            $this->error('Article not found', 404);
            return;
        }

        // Only author or agents can edit
        if ($article['author_id'] !== $this->auth->id() && !$this->auth->isAgent()) {
            $this->error('Unauthorized', 403);
            return;
        }

        $categories = $this->categoryModel->getActive();

        $this->view('kb/edit', [
            'article' => $article,
            'categories' => $categories,
        ]);
    }

    /**
     * Update article
     */
    public function update(string $id): void
    {
        $this->requireAuth();
        $this->init();

        $article = $this->kbModel->find((int) $id);

        if (!$article) {
            $this->error('Article not found', 404);
            return;
        }

        // Only author or agents can edit
        if ($article['author_id'] !== $this->auth->id() && !$this->auth->isAgent()) {
            $this->error('Unauthorized', 403);
            return;
        }

        $data = $this->request->only(['title', 'content', 'category_id', 'tags', 'is_published', 'is_featured']);

        // Validate
        if (empty($data['title']) || strlen($data['title']) < 3) {
            $this->response->withError('Title is required (minimum 3 characters)');
            $this->redirect($this->app->url("kb/{$id}/edit"));
            return;
        }

        // Generate new slug if title changed
        $slug = $article['slug'];
        if ($data['title'] !== $article['title']) {
            $slug = $this->kbModel->generateSlug($data['title']);
        }

        // Sanitize HTML content
        $content = $this->sanitizeHtml($data['content']);

        // Generate excerpt from plain text
        $excerpt = strip_tags($content);
        $excerpt = html_entity_decode($excerpt);
        $excerpt = trim(preg_replace('/\s+/', ' ', $excerpt));
        $excerpt = strlen($excerpt) > 200 ? substr($excerpt, 0, 200) . '...' : $excerpt;

        // Process tags
        $tags = null;
        if (!empty($data['tags'])) {
            $tagList = array_map('trim', explode(',', $data['tags']));
            $tags = json_encode(array_filter($tagList));
        }

        $updateData = [
            'title' => $data['title'],
            'slug' => $slug,
            'content' => $content,
            'excerpt' => $excerpt,
            'category_id' => $data['category_id'] ?: null,
            'tags' => $tags,
            'is_published' => !empty($data['is_published']) ? 1 : 0,
        ];

        // Only agents can set featured
        if ($this->auth->isAgent()) {
            $updateData['is_featured'] = !empty($data['is_featured']) ? 1 : 0;
        }

        $this->kbModel->update((int) $id, $updateData);

        $this->response->withSuccess('Article updated successfully');
        $this->redirect($this->app->url("kb/{$slug}"));
    }

    /**
     * Delete article
     */
    public function delete(string $id): void
    {
        $this->requireAuth();
        $this->init();

        $article = $this->kbModel->find((int) $id);

        if (!$article) {
            if ($this->request->isAjax()) {
                $this->error('Article not found', 404);
                return;
            }
            $this->response->withError('Article not found');
            $this->redirect($this->app->url('kb'));
            return;
        }

        // Only author or agents can delete
        if ($article['author_id'] !== $this->auth->id() && !$this->auth->isAgent()) {
            if ($this->request->isAjax()) {
                $this->error('Unauthorized', 403);
                return;
            }
            $this->response->withError('Unauthorized');
            $this->redirect($this->app->url('kb'));
            return;
        }

        $this->kbModel->delete((int) $id);

        if ($this->request->isAjax()) {
            $this->success([], 'Article deleted successfully');
            return;
        }

        $this->response->withSuccess('Article deleted successfully');
        $this->redirect($this->app->url('kb'));
    }

    /**
     * My articles (for authors)
     */
    public function myArticles(): void
    {
        $this->requireAuth();
        $this->init();

        $articles = $this->kbModel->getByAuthor($this->auth->id());

        $this->view('kb/my-articles', [
            'articles' => $articles,
        ]);
    }
}
