<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\Category;
use App\Models\User;
use App\Models\ActivityLog;
use App\Models\CannedResponse;
use App\Services\AI\TicketClassifier;
use App\Helpers\FileUploader;
use App\Services\Telegram\TelegramAgentNotifier;

class TicketController extends Controller
{
    private Ticket $ticketModel;
    private TicketMessage $messageModel;
    private Category $categoryModel;
    private ActivityLog $activityModel;

    protected function init(): void
    {
        $this->ticketModel = new Ticket($this->db);
        $this->ticketModel->setCompanyId($this->companyId());

        $this->messageModel = new TicketMessage($this->db);

        $this->categoryModel = new Category($this->db);
        $this->categoryModel->setCompanyId($this->companyId());

        $this->activityModel = new ActivityLog($this->db);
        $this->activityModel->setCompanyId($this->companyId());
    }

    public function index(): void
    {
        $this->requireAgent();
        $this->init();

        $filters = $this->request->only(['status', 'priority', 'category_id', 'assigned_to', 'search', 'order_by', 'order_dir']);
        $page = (int) $this->request->query('page', 1);

        if ($this->auth->isRestrictedAgent()) {
            $filters['assigned_to'] = $this->auth->id();
        }

        $tickets = $this->ticketModel->getFiltered($filters, $page);
        $categories = $this->categoryModel->getActive();

        $userModel = new User($this->db);
        $userModel->setCompanyId($this->companyId());
        $agents = $userModel->getAgents();

        $this->view('tickets/index', [
            'tickets' => $tickets,
            'categories' => $categories,
            'agents' => $agents,
            'filters' => $filters,
            'isAgentOnly' => $this->auth->isRestrictedAgent(),
        ]);
    }

    public function create(): void
    {
        $this->requireAgent();
        $this->init();

        $categories = $this->categoryModel->getActive();

        $userModel = new User($this->db);
        $userModel->setCompanyId($this->companyId());
        $agents = $userModel->getAgents();

        $this->view('tickets/create', [
            'categories' => $categories,
            'agents' => $agents,
        ]);
    }

    public function store(): void
    {
        $this->requireAgent();
        $this->init();

        $data = $this->request->only([
            'subject', 'description', 'priority', 'category_id',
            'assigned_to', 'requester_email', 'requester_name'
        ]);

        // Validate
        $errors = [];
        if (empty($data['subject'])) $errors['subject'] = 'ប្រធានបទគឺត្រូវបាន';
        if (empty($data['description'])) $errors['description'] = 'ការពិពណ៌នាគឺត្រូវបាន';
        if (empty($data['requester_email'])) $errors['requester_email'] = 'អ៊ីមែលលេខសុំបានត្រូវបាន';
        if (empty($data['requester_name'])) $errors['requester_name'] = 'ឈ្មោះលេខសុំបានត្រូវបាន';

        if (!empty($errors)) {
            if ($this->request->isAjax()) {
                $this->error('ការផ្ទៀងផ្ទាត់បានបរាជ័យ', 422, $errors);
                return;
            }
            $_SESSION['validation_errors'] = $errors;
            $this->response->withError('សូមជួសជុលកំហុស')->withInput();
            $this->redirect($this->app->url('tickets/create'));
            return;
        }

        // Create or get requester user
        $userModel = new User($this->db);
        $requesterId = $userModel->createCustomerIfNotExists(
            $data['requester_email'],
            $data['requester_name'],
            $this->companyId()
        );

        // Generate ticket number
        $ticketNumber = $this->ticketModel->generateTicketNumber();

        // AI Classification
        $aiClassification = $this->classifyTicket($data['subject'], $data['description']);

        // Validate priority against allowed ENUM values
        $validPriorities = ['low', 'medium', 'high', 'urgent'];
        $priority = $data['priority'] ?? $aiClassification['priority'] ?? 'medium';
        if (!in_array($priority, $validPriorities)) {
            $priority = 'medium';
        }
        $aiPriority = $aiClassification['priority'] ?? null;
        if ($aiPriority && !in_array($aiPriority, $validPriorities)) {
            $aiPriority = null;
        }

        // Create ticket
        $ticketId = $this->ticketModel->create([
            'company_id' => $this->companyId(),
            'ticket_number' => $ticketNumber,
            'subject' => $data['subject'],
            'description' => $data['description'],
            'status' => 'open',
            'priority' => $priority,
            'source' => 'web',
            'category_id' => $data['category_id'] ?: ($aiClassification['category_id'] ?? null),
            'assigned_to' => $data['assigned_to'] ?: null,
            'requester_id' => $requesterId,
            'requester_email' => $data['requester_email'],
            'requester_name' => $data['requester_name'],
            'ai_suggested_category' => $aiClassification['category_id'] ?? null,
            'ai_suggested_priority' => $aiPriority,
            'ai_confidence_score' => $aiClassification['confidence'] ?? null,
        ]);

        // Add initial message
        $this->messageModel->addReply(
            $ticketId,
            $requesterId,
            $data['description'],
            false,
            'web'
        );

        // Log activity
        $this->activityModel->log(
            $this->companyId(),
            $ticketId,
            $this->auth->id(),
            'ticket_created',
            "Ticket {$ticketNumber} created"
        );

        if ($this->request->isAjax()) {
            $this->success(['ticket_id' => $ticketId, 'ticket_number' => $ticketNumber], 'សំណើសុំជំនួយបានបង្កើតដោយជោគជ័យ');
            return;
        }

        $this->response->withSuccess("សំណើសុំជំនួយ {$ticketNumber} បានបង្កើតដោយជោគជ័យ");
        $this->redirect($this->app->url("tickets/{$ticketId}"));
    }

    public function show(string $id): void
    {
        $this->requireAgent();
        $this->init();

        $ticket = $this->ticketModel->getWithRelations((int) $id);

        if (!$ticket) {
            http_response_code(404);
            $this->view('errors/404');
            return;
        }

        if (!$this->ensureAgentTicketAccess($ticket)) {
            return;
        }

        $messages = $this->messageModel->getByTicket((int) $id);
        $activities = $this->activityModel->getByTicket((int) $id, 20);
        $categories = $this->categoryModel->getActive();

        $userModel = new User($this->db);
        $userModel->setCompanyId($this->companyId());
        $agents = $userModel->getAgents();

        $cannedModel = new CannedResponse($this->db);
        $cannedModel->setCompanyId($this->companyId());
        $cannedResponses = $cannedModel->getActive($ticket['category_id']);

        // Get survey data if exists
        $survey = $this->db->selectOne(
            "SELECT * FROM ticket_surveys WHERE ticket_id = ?",
            [(int) $id]
        );

        $this->view('tickets/show', [
            'ticket' => $ticket,
            'messages' => $messages,
            'activities' => $activities,
            'categories' => $categories,
            'agents' => $agents,
            'cannedResponses' => $cannedResponses,
            'survey' => $survey,
        ]);
    }

    public function reply(string $id): void
    {
        $this->requireAgent();
        $this->init();

        $ticket = $this->ticketModel->find((int) $id);
        if (!$ticket) {
            $this->error('សំណើសុំជំនួយមិនត្រូវបានរកឃើញ', 404);
            return;
        }

        if (!$this->ensureAgentTicketAccess($ticket)) {
            return;
        }

        $message = $this->request->input('message');
        $isInternal = (bool) $this->request->input('is_internal', false);

        if (empty($message) && empty($_FILES['attachments']['name'][0])) {
            $this->error('សារឬឯកសារថតគឺត្រូវបាន', 422);
            return;
        }

        // Handle file uploads
        $attachments = [];
        if (!empty($_FILES['attachments']['name'][0])) {
            try {
                $uploader = new FileUploader();
                $uploadedFiles = $uploader->uploadMultiple($_FILES['attachments'], 'tickets/' . $id);
                $attachments = $uploadedFiles;

                // Save attachments to database
                foreach ($uploadedFiles as $file) {
                    $this->db->insert('attachments', [
                        'ticket_id' => (int) $id,
                        'user_id' => $this->auth->id(),
                        'filename' => $file['filename'],
                        'original_name' => $file['original_name'],
                        'mime_type' => $file['mime_type'],
                        'size' => $file['size'],
                        'path' => $file['path'],
                    ]);
                }
            } catch (\Exception $e) {
                if ($this->request->isAjax()) {
                    $this->error('ការផ្ទុកឯកសារបានបរាជ័យ: ' . $e->getMessage(), 422);
                    return;
                }
                $this->response->withError('ការផ្ទុកឯកសារបានបរាជ័យ: ' . $e->getMessage());
                $this->redirect($this->app->url("tickets/{$id}"));
                return;
            }
        }

        $messageId = $this->messageModel->addReply(
            (int) $id,
            $this->auth->id(),
            $message ?: '[Attachment]',
            $isInternal,
            'web',
            $attachments
        );

        // Update attachments with message_id
        if (!empty($attachments)) {
            $this->db->query(
                "UPDATE attachments SET message_id = ? WHERE ticket_id = ? AND message_id IS NULL AND user_id = ?",
                [$messageId, (int) $id, $this->auth->id()]
            );
        }

        if (!$isInternal && empty($ticket['first_response_at'])) {
            $this->ticketModel->update((int) $id, ['first_response_at' => date('Y-m-d H:i:s')]);
            $this->ticketModel->updateFirstResponseSla((int) $id);
        }

        // Log activity
        $actionType = $isInternal ? 'internal_note_added' : 'reply_added';
        $attachmentCount = count($attachments);
        $description = $isInternal ? 'ចំណាំខាងក្នុងបានបន្ថែម' : 'ការឆ្លើយតបផ្ញើទៅអ្នកប្រើប្រាស់';
        if ($attachmentCount > 0) {
            $description .= " ដែលមាន {$attachmentCount} ឯកសារ(s)";
        }

        $this->activityModel->log(
            $this->companyId(),
            (int) $id,
            $this->auth->id(),
            $actionType,
            $description
        );

        // Send Telegram notification if not internal note
        if (!$isInternal) {
            $this->sendTelegramNotification($ticket, 'reply', $message, $attachments);
            $this->sendWebPushNotification($ticket, 'reply', $message);
        }

        if ($this->request->isAjax()) {
            $this->success(['message_id' => $messageId, 'attachments' => $attachments], 'ការឆ្លើយតបបានដំឡើងដោយជោគជ័យ');
            return;
        }

        $this->response->withSuccess('ការឆ្លើយតបបានដំឡើងដោយជោគជ័យ');
        $this->redirect($this->app->url("tickets/{$id}"));
    }

    public function updateStatus(string $id): void
    {
        $this->requireAgent();
        $this->init();

        $ticket = $this->ticketModel->find((int) $id);
        if (!$ticket) {
            $this->error('សំណើសុំជំនួយមិនត្រូវបានរកឃើញ', 404);
            return;
        }

        if (!$this->ensureAgentTicketAccess($ticket)) {
            return;
        }

        $status = $this->request->input('status');
        $validStatuses = ['open', 'pending', 'in_progress', 'resolved', 'closed'];

        if (!in_array($status, $validStatuses)) {
            $this->error('ស្ថានភាពមិនមានសុពលភាព', 422);
            return;
        }

        $oldStatus = $ticket['status'];
        $this->ticketModel->updateStatus((int) $id, $status);

        if ($status === 'resolved') {
            $this->ticketModel->updateResolutionSla((int) $id);
        }

        // Log activity
        $this->activityModel->log(
            $this->companyId(),
            (int) $id,
            $this->auth->id(),
            'status_changed',
            "Status changed from {$oldStatus} to {$status}",
            ['status' => $oldStatus],
            ['status' => $status]
        );

        // Send Telegram notification and survey if resolved
        if ($status === 'resolved') {
            $this->sendTelegramNotification($ticket, 'resolved');
            $this->sendWebPushNotification($ticket, 'resolved');
            $this->sendTicketSurvey($ticket);
        }

        if ($this->request->isAjax()) {
            $this->success([], 'ស្ថានភាពបានធ្វើបច្ចុប្បន្នភាពដោយជោគជ័យ');
            return;
        }

        $this->response->withSuccess('ស្ថានភាពបានធ្វើបច្ចុប្បន្នភាព');
        $this->redirect($this->app->url("tickets/{$id}"));
    }

    public function assign(string $id): void
    {
        $this->requireAgent();
        $this->init();

        $ticket = $this->ticketModel->find((int) $id);
        if (!$ticket) {
            $this->error('សំណើសុំជំនួយមិនត្រូវបានរកឃើញ', 404);
            return;
        }

        if (!$this->ensureAgentTicketAccess($ticket)) {
            return;
        }

        $assignedTo = $this->request->input('assigned_to');
        $assignedTo = $assignedTo ? (int) $assignedTo : null;

        $this->ticketModel->assign((int) $id, $assignedTo);

        // Log activity
        $description = $assignedTo ? 'សំណើសុំជំនួយបានផ្តល់ឱ្យ' : 'សំណើសុំជំនួយមិនបានផ្តល់ឱ្យ';
        $this->activityModel->log(
            $this->companyId(),
            (int) $id,
            $this->auth->id(),
            'ticket_assigned',
            $description
        );

        if ($assignedTo) {
            $ticket['assigned_to'] = $assignedTo;
            $assignedUser = $this->db->selectOne(
                "SELECT name FROM users WHERE id = ? AND company_id = ?",
                [$assignedTo, $this->companyId()]
            );
            $assignedName = $assignedUser['name'] ?? 'Agent';
            $link = $this->app->url("tickets/{$id}");
            $subject = htmlspecialchars($ticket['subject'] ?? '', ENT_QUOTES | ENT_HTML5);
            $requesterName = htmlspecialchars($ticket['requester_name'] ?? '', ENT_QUOTES | ENT_HTML5);
            $requesterEmail = htmlspecialchars($ticket['requester_email'] ?? '', ENT_QUOTES | ENT_HTML5);
            $assignedLabel = htmlspecialchars($assignedName, ENT_QUOTES | ENT_HTML5);
            $status = ucfirst(str_replace('_', ' ', $ticket['status'] ?? 'open'));
            $priority = ucfirst($ticket['priority'] ?? 'medium');
            $text = "✅ <b>Ticket Assigned</b>\n" .
                "🆔 <b>Ticket:</b> #{$ticket['ticket_number']}\n" .
                "📝 <b>Subject:</b> {$subject}\n" .
                "📌 <b>Status:</b> {$status}\n" .
                "🚦 <b>Priority:</b> {$priority}\n" .
                "👤 <b>Requester:</b> {$requesterName} ({$requesterEmail})\n" .
                "🎯 <b>Assigned to:</b> {$assignedLabel}\n" .
                "🔗 <b>Link:</b> {$link}";
            $notifier = new TelegramAgentNotifier($this->db, $this->companyId());
            $notifier->notifyAssignedAgent($ticket, $text);
        }

        if ($this->request->isAjax()) {
            $this->success([], 'ការងារផ្តល់ឱ្យបានធ្វើបច្ចុប្បន្នភាព');
            return;
        }

        $this->response->withSuccess('ការងារផ្តល់ឱ្យបានធ្វើបច្ចុប្បន្នភាព');
        $this->redirect($this->app->url("tickets/{$id}"));
    }

    public function updatePriority(string $id): void
    {
        $this->requireAgent();
        $this->init();

        $ticket = $this->ticketModel->find((int) $id);
        if (!$ticket) {
            $this->error('សំណើសុំជំនួយមិនត្រូវបានរកឃើញ', 404);
            return;
        }

        if (!$this->ensureAgentTicketAccess($ticket)) {
            return;
        }

        $priority = $this->request->input('priority');
        $validPriorities = ['low', 'medium', 'high', 'urgent'];

        if (!in_array($priority, $validPriorities)) {
            $this->error('អগ្គាធារមិនមានសុពលភាព', 422);
            return;
        }

        $oldPriority = $ticket['priority'];
        $this->ticketModel->update((int) $id, ['priority' => $priority]);

        // Log activity
        $this->activityModel->log(
            $this->companyId(),
            (int) $id,
            $this->auth->id(),
            'priority_changed',
            "អតិភាពបានផ្លាស់ប្តូរពី {$oldPriority} ទៅ {$priority}",
            ['priority' => $oldPriority],
            ['priority' => $priority]
        );

        if ($this->request->isAjax()) {
            $this->success([], 'អតិភាពបានធ្វើបច្ចុប្បន្នភាព');
            return;
        }

        $this->response->withSuccess('អតិភាពបានធ្វើបច្ចុប្បន្នភាព');
        $this->redirect($this->app->url("tickets/{$id}"));
    }

    public function updateCategory(string $id): void
    {
        $this->requireAgent();
        $this->init();

        $ticket = $this->ticketModel->find((int) $id);
        if (!$ticket) {
            $this->error('សំណើសុំជំនួយមិនត្រូវបានរកឃើញ', 404);
            return;
        }

        if (!$this->ensureAgentTicketAccess($ticket)) {
            return;
        }

        $categoryId = $this->request->input('category_id');
        $categoryId = $categoryId ? (int) $categoryId : null;

        $this->ticketModel->update((int) $id, ['category_id' => $categoryId]);

        // Log activity
        $this->activityModel->log(
            $this->companyId(),
            (int) $id,
            $this->auth->id(),
            'category_changed',
            'ប្រភេទបានធ្វើបច្ចុប្បន្នភាព'
        );

        if ($this->request->isAjax()) {
            $this->success([], 'ប្រភេទបានធ្វើបច្ចុប្បន្នភាព');
            return;
        }

        $this->response->withSuccess('ប្រភេទបានធ្វើបច្ចុប្បន្នភាព');
        $this->redirect($this->app->url("tickets/{$id}"));
    }

    private function classifyTicket(string $subject, string $description): array
    {
        try {
            $classifier = new TicketClassifier($this->db, $this->companyId());
            return $classifier->classify($subject . ' ' . $description);
        } catch (\Exception $e) {
            return ['category_id' => null, 'priority' => 'medium', 'confidence' => 0];
        }
    }

    private function ensureAgentTicketAccess(array $ticket): bool
    {
        if (!$this->auth->isRestrictedAgent()) {
            return true;
        }

        if ((int) ($ticket['assigned_to'] ?? 0) !== (int) $this->auth->id()) {
            if ($this->request->isAjax() || $this->request->isJson()) {
                $this->error('Forbidden', 403);
                return false;
            }

            $this->response->withError('Forbidden');
            $this->redirect($this->app->url('tickets'));
            return false;
        }

        return true;
    }

    /**
     * Send Telegram notification to customer
     */
    private function sendTelegramNotification(array $ticket, string $type = 'reply', string $message = '', array $attachments = []): void
    {
        try {
            // Get Telegram config
            $config = $this->db->selectOne(
                "SELECT bot_token FROM telegram_configs WHERE company_id = ? AND is_active = 1",
                [$this->companyId()]
            );

            if (!$config || !$config['bot_token']) {
                return; // Telegram not configured
            }

            $botToken = $config['bot_token'];
            $chatIds = [];

            // Check if ticket was created from a group (has telegram_chat_id on ticket)
            if (!empty($ticket['telegram_chat_id'])) {
                $chatIds[] = $ticket['telegram_chat_id'];
            }

            // Also get customer's personal telegram chat_id
            $user = $this->db->selectOne(
                "SELECT telegram_chat_id FROM users WHERE id = ?",
                [$ticket['requester_id']]
            );

            if ($user && $user['telegram_chat_id'] && !in_array($user['telegram_chat_id'], $chatIds)) {
                $chatIds[] = $user['telegram_chat_id'];
            }

            if (empty($chatIds)) {
                return; // No Telegram destination
            }

            // Build notification message
            switch ($type) {
                case 'reply':
                    $preview = strlen($message) > 200 ? substr($message, 0, 200) . '...' : $message;
                    $attachmentNote = !empty($attachments) ? "\n\n📎 " . count($attachments) . " ឯកសារ(s)" : "";
                    $text = "📩 <b>ការឆ្លើយតបថ្មីលើសំណើសុំជំនួយ #{$ticket['ticket_number']}</b>\n\n" .
                            "<b>ប្រធានបទ:</b> {$ticket['subject']}\n\n" .
                            "<b>សារ:</b>\n{$preview}{$attachmentNote}\n\n" .
                            "ប្រើ /mytickets ដើម្បីមើលលម្អិត។";
                    break;

                case 'resolved':
                    $text = "✅ <b>សំណើសុំជំនួយ #{$ticket['ticket_number']} បានដោះស្រាយ</b>\n\n" .
                            "<b>ប្រធានបទ:</b> {$ticket['subject']}\n\n" .
                            "ប្រសិនបើអ្នកត្រូវការជំនួយលម្អិត អ្នកអាចឆ្លើយដើម្បីបើកឡើងវិញ។";
                    break;

                default:
                    return;
            }

            // Send to all destinations (private chat and/or group)
            foreach ($chatIds as $chatId) {
                // Send text message first
                $this->sendTelegramMessage($botToken, $chatId, $text);

                // Send attachments if any
                if (!empty($attachments) && $type === 'reply') {
                    foreach ($attachments as $attachment) {
                        $this->sendTelegramFile($botToken, $chatId, $attachment, $ticket['ticket_number']);
                    }
                }
            }

        } catch (\Exception $e) {
            error_log("ការជូនដំណឹង Telegram បានបរាជ័យ: " . $e->getMessage());
        }
    }

    /**
     * Send a text message via Telegram
     */
    private function sendTelegramMessage(string $botToken, $chatId, string $text): void
    {
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_TIMEOUT => 10,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Send a file via Telegram
     */
    private function sendTelegramFile(string $botToken, $chatId, array $attachment, string $ticketNumber): void
    {
        // Path from FileUploader is like "tickets/1/2026/01/file.jpg", needs uploads/ prefix
        $filePath = $this->app->basePath('public/uploads/' . $attachment['path']);

        if (!file_exists($filePath)) {
            error_log("ឯកសារ Telegram មិនត្រូវបានរកឃើញ: {$filePath}");
            return;
        }

        $mimeType = $attachment['mime_type'] ?? 'application/octet-stream';
        $isImage = strpos($mimeType, 'image/') === 0;

        // Use sendPhoto for images, sendDocument for other files
        $method = $isImage ? 'sendPhoto' : 'sendDocument';
        $fileParam = $isImage ? 'photo' : 'document';

        $url = "https://api.telegram.org/bot{$botToken}/{$method}";

        // Use CURLFile for file upload
        $cFile = new \CURLFile($filePath, $mimeType, $attachment['original_name'] ?? $attachment['filename']);

        $params = [
            'chat_id' => $chatId,
            $fileParam => $cFile,
            'caption' => "📎 ឯកសារពីសំណើសុំជំនួយ #{$ticketNumber}"
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("កំហុស Telegram ឯកសារផ្ញើ: {$error}");
        } else {
            $result = json_decode($response, true);
            if (!($result['ok'] ?? false)) {
                error_log("កំហុស Telegram API: " . ($result['description'] ?? 'មិនស្គាល់'));
            }
        }
    }

    /**
     * Send satisfaction survey to customer via Telegram
     */
    private function sendTicketSurvey(array $ticket): void
    {
        error_log("sendTicketSurvey: ការចាប់ផ្តើមសម្រាប់សំណើសុំជំនួយ ID {$ticket['id']}");

        try {
            // Get customer's telegram chat_id
            $user = $this->db->selectOne(
                "SELECT id, telegram_chat_id FROM users WHERE id = ?",
                [$ticket['requester_id']]
            );

            if (!$user || !$user['telegram_chat_id']) {
                error_log("sendTicketSurvey: ប្រើប្រាស់មិនបានតភ្ជាប់ទៅ Telegram (requester_id: {$ticket['requester_id']})");
                return; // User not linked to Telegram
            }

            error_log("sendTicketSurvey: រកឃើញប្រើប្រាស់ដែលមាន chat_id: {$user['telegram_chat_id']}");

            // Get Telegram config
            $config = $this->db->selectOne(
                "SELECT bot_token FROM telegram_configs WHERE company_id = ? AND is_active = 1",
                [$this->companyId()]
            );

            if (!$config || !$config['bot_token']) {
                error_log("sendTicketSurvey: Telegram មិនត្រូវបានកំណត់រចនាសម្ព័ន្ធសម្រាប់ក្រុមហ៊ុន {$this->companyId()}");
                return; // Telegram not configured
            }

            error_log("sendTicketSurvey: រកឃើញ bot token ពិនិត្យសម្រាប់ការស្ទង់មតិដែលមាន");

            // Check if survey already sent for this ticket
            $existingSurvey = $this->db->selectOne(
                "SELECT id FROM ticket_surveys WHERE ticket_id = ?",
                [$ticket['id']]
            );

            if ($existingSurvey) {
                error_log("sendTicketSurvey: ការស្ទង់មតិលទ្ធផលសម្រាប់សំណើសុំជំនួយ {$ticket['id']}");
                return; // Survey already sent
            }

            error_log("sendTicketSurvey: ការបង្កើតកាលត់សម្រាប់ការស្ទង់មតិ");

            // Create survey record
            $this->db->insert('ticket_surveys', [
                'company_id' => $this->companyId(),
                'ticket_id' => $ticket['id'],
                'user_id' => $user['id']
            ]);

            error_log("sendTicketSurvey: ក្រុងលេខថតលទ្ធផលបង្កើត ផ្ញើឱ្យ Telegram");

            $chatId = $user['telegram_chat_id'];
            $botToken = $config['bot_token'];

            // Build survey message with rating buttons
            $text = "📊 <b>តើបទពិសោធន៍របស់អ្នកលម្អិត?</b>\n\n" .
                    "សំណើសុំជំនួយ: #{$ticket['ticket_number']}\n" .
                    "ប្រធានបទ: {$ticket['subject']}\n\n" .
                    "សូមវាយតម្លៃការគាំទ្ភាពរបស់យើងខ្ញុំ:";

            // Rating keyboard
            $keyboard = [
                [
                    ['text' => '⭐⭐⭐⭐⭐ ពិសេស', 'callback_data' => "survey:5:{$ticket['id']}"],
                    ['text' => '⭐⭐⭐⭐ ល្អ', 'callback_data' => "survey:4:{$ticket['id']}"]
                ],
                [
                    ['text' => '⭐⭐⭐ ល្អ', 'callback_data' => "survey:3:{$ticket['id']}"],
                    ['text' => '⭐⭐ មិនល្អ', 'callback_data' => "survey:2:{$ticket['id']}"]
                ],
                [
                    ['text' => '⭐ មិនល្អប៉ុន្មាន', 'callback_data' => "survey:1:{$ticket['id']}"]
                ]
            ];

            // Send message with keyboard
            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
            $params = [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
            ];

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $params,
                CURLOPT_TIMEOUT => 10,
            ]);
            $response = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                error_log("sendTicketSurvey: កំហុស Curl: {$error}");
            } else {
                $result = json_decode($response, true);
                if ($result['ok'] ?? false) {
                    error_log("sendTicketSurvey: ការស្ទង់មតិផ្ញើដោយជោគជ័យឱ្យ chat {$chatId}");
                } else {
                    error_log("sendTicketSurvey: កំហុស Telegram API: " . ($result['description'] ?? 'មិនស្គាល់'));
                }
            }

        } catch (\Exception $e) {
            error_log("sendTicketSurvey ករណីលើកលែង: " . $e->getMessage());
        }
    }

    /**
     * Send web push notification to assigned agents
     */
    private function sendWebPushNotification(array $ticket, string $type = 'reply', string $message = ''): void
    {
        try {
            $pushService = new \App\Services\PushNotificationService($this->db);
            
            // Get agents assigned to this ticket
            $agents = $this->db->select(
                "SELECT id FROM users WHERE id = ? AND role IN ('agent', 'front_office_agent', 'back_office_agent', 'admin') AND is_active = 1",
                [$ticket['assigned_to']]
            );

            if (empty($agents)) {
                return; // No agents to notify
            }

            $agentIds = array_map(fn($a) => $a['id'], $agents);

            // Build notification
            switch ($type) {
                case 'reply':
                    $title = 'New Reply - Ticket #' . $ticket['ticket_number'];
                    $messagePreview = strlen($message) > 100 ? substr($message, 0, 100) . '...' : $message;
                    $body = 'Customer replied: ' . $messagePreview;
                    break;

                case 'resolved':
                    $title = 'Ticket Resolved - #' . $ticket['ticket_number'];
                    $body = $ticket['subject'];
                    break;

                default:
                    return;
            }

            // Send push notification
            $pushService->sendToUsers(
                $agentIds,
                $title,
                $body,
                [
                    'ticketId' => $ticket['id'],
                    'ticketNumber' => $ticket['ticket_number'],
                    'type' => $type
                ]
            );

        } catch (\Exception $e) {
            error_log("Web push notification failed: " . $e->getMessage());
        }
    }
}
