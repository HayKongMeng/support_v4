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

        // Pass current user for role-based filtering
        $tickets = $this->ticketModel->getFiltered($filters, $page, 25, $this->user());

        $categories = $this->categoryModel->getActive();

        $userModel = new User($this->db);
        $userModel->setCompanyId($this->companyId());
        $agents = $userModel->getAgents();

        $this->view('tickets/index', [
            'tickets' => $tickets,
            'categories' => $categories,
            'agents' => $agents,
            'filters' => $filters,
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
        if (empty($data['subject'])) $errors['subject'] = 'Subject is required';
        if (empty($data['description'])) $errors['description'] = 'Description is required';
        if (empty($data['requester_email'])) $errors['requester_email'] = 'Requester email is required';
        if (empty($data['requester_name'])) $errors['requester_name'] = 'Requester name is required';

        if (!empty($errors)) {
            if ($this->request->isAjax()) {
                $this->error('Validation failed', 422, $errors);
                return;
            }
            $_SESSION['validation_errors'] = $errors;
            $this->response->withError('Please fix the errors')->withInput();
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
            $this->success(['ticket_id' => $ticketId, 'ticket_number' => $ticketNumber], 'Ticket created successfully');
            return;
        }

        $this->response->withSuccess("Ticket {$ticketNumber} created successfully");
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

        $messages = $this->messageModel->getByTicket((int) $id);
        $activities = $this->activityModel->getByTicket((int) $id, 20);
        $categories = $this->categoryModel->getActive();

        $userModel = new User($this->db);
        $userModel->setCompanyId($this->companyId());
        $agents = $userModel->getAgents();

        $cannedModel = new CannedResponse($this->db);
        $cannedModel->setCompanyId($this->companyId());
        $cannedResponses = $cannedModel->getActive($ticket['category_id']);

        // Get survey data if exists (gracefully handle missing table)
        $survey = null;
        try {
            $survey = $this->db->selectOne(
                "SELECT * FROM ticket_surveys WHERE ticket_id = ?",
                [(int) $id]
            );
        } catch (\Exception $e) {
            // Table doesn't exist yet, surveys will be disabled
            error_log("Survey table not found: " . $e->getMessage());
        }

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
            $this->error('Ticket not found', 404);
            return;
        }

        $message = $this->request->input('message');
        $isInternal = (bool) $this->request->input('is_internal', false);

        if (empty($message) && empty($_FILES['attachments']['name'][0])) {
            $this->error('Message or attachment is required', 422);
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
                    $this->error('File upload failed: ' . $e->getMessage(), 422);
                    return;
                }
                $this->response->withError('File upload failed: ' . $e->getMessage());
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

        // Log activity
        $actionType = $isInternal ? 'internal_note_added' : 'reply_added';
        $attachmentCount = count($attachments);
        $description = $isInternal ? 'Internal note added' : 'Reply sent to customer';
        if ($attachmentCount > 0) {
            $description .= " with {$attachmentCount} attachment(s)";
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
        }

        if ($this->request->isAjax()) {
            $this->success(['message_id' => $messageId, 'attachments' => $attachments], 'Reply added successfully');
            return;
        }

        $this->response->withSuccess('Reply added successfully');
        $this->redirect($this->app->url("tickets/{$id}"));
    }

    public function updateStatus(string $id): void
    {
        $this->requireAgent();
        $this->init();

        $ticket = $this->ticketModel->find((int) $id);
        if (!$ticket) {
            $this->error('Ticket not found', 404);
            return;
        }

        $status = $this->request->input('status');
        $validStatuses = ['open', 'pending', 'in_progress', 'resolved', 'closed'];

        if (!in_array($status, $validStatuses)) {
            $this->error('Invalid status', 422);
            return;
        }

        $oldStatus = $ticket['status'];
        $this->ticketModel->updateStatus((int) $id, $status);

        // Update SLA tracking if resolved
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
            $this->sendTicketSurvey($ticket);
        }

        if ($this->request->isAjax()) {
            $this->success([], 'Status updated successfully');
            return;
        }

        $this->response->withSuccess('Status updated');
        $this->redirect($this->app->url("tickets/{$id}"));
    }

    public function assign(string $id): void
    {
        $this->requireAgent();
        $this->init();

        $ticket = $this->ticketModel->find((int) $id);
        if (!$ticket) {
            $this->error('Ticket not found', 404);
            return;
        }

        $assignedTo = $this->request->input('assigned_to');
        $assignedTo = $assignedTo ? (int) $assignedTo : null;

        $this->ticketModel->assign((int) $id, $assignedTo);

        // Log activity
        $description = $assignedTo ? 'Ticket assigned' : 'Ticket unassigned';
        $this->activityModel->log(
            $this->companyId(),
            (int) $id,
            $this->auth->id(),
            'ticket_assigned',
            $description
        );

        if ($this->request->isAjax()) {
            $this->success([], 'Assignment updated');
            return;
        }

        $this->response->withSuccess('Assignment updated');
        $this->redirect($this->app->url("tickets/{$id}"));
    }

    public function pickup(string $id): void
    {
        $this->requireAgent();
        $this->init();

        $ticket = $this->ticketModel->find((int) $id);
        if (!$ticket) {
            $this->error('Ticket not found', 404);
            return;
        }

        // Check if ticket is already assigned
        if ($ticket['assigned_to']) {
            if ($this->request->isAjax()) {
                $this->error('Ticket is already assigned', 400);
                return;
            }
            $this->response->withError('This ticket is already assigned to someone');
            $this->redirect($this->app->url('tickets'));
            return;
        }

        // Assign ticket to current user
        $this->ticketModel->assign((int) $id, $this->auth->id());

        // Log activity
        $this->activityModel->log(
            $this->companyId(),
            (int) $id,
            $this->auth->id(),
            'ticket_picked_up',
            'Ticket picked up by ' . $this->auth->user()['name']
        );

        if ($this->request->isAjax()) {
            $this->success([], 'Ticket assigned to you');
            return;
        }

        $this->response->withSuccess('Ticket assigned to you! You can now work on it.');
        $this->redirect($this->app->url("tickets/{$id}"));
    }

    public function updatePriority(string $id): void
    {
        $this->requireAgent();
        $this->init();

        $ticket = $this->ticketModel->find((int) $id);
        if (!$ticket) {
            $this->error('Ticket not found', 404);
            return;
        }

        $priority = $this->request->input('priority');
        $validPriorities = ['low', 'medium', 'high', 'urgent'];

        if (!in_array($priority, $validPriorities)) {
            $this->error('Invalid priority', 422);
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
            "Priority changed from {$oldPriority} to {$priority}",
            ['priority' => $oldPriority],
            ['priority' => $priority]
        );

        if ($this->request->isAjax()) {
            $this->success([], 'Priority updated');
            return;
        }

        $this->response->withSuccess('Priority updated');
        $this->redirect($this->app->url("tickets/{$id}"));
    }

    public function updateCategory(string $id): void
    {
        $this->requireAgent();
        $this->init();

        $ticket = $this->ticketModel->find((int) $id);
        if (!$ticket) {
            $this->error('Ticket not found', 404);
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
            'Category updated'
        );

        if ($this->request->isAjax()) {
            $this->success([], 'Category updated');
            return;
        }

        $this->response->withSuccess('Category updated');
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
                    $attachmentNote = !empty($attachments) ? "\n\n📎 " . count($attachments) . " attachment(s)" : "";
                    $text = "📩 <b>New reply on ticket #{$ticket['ticket_number']}</b>\n\n" .
                            "<b>Subject:</b> {$ticket['subject']}\n\n" .
                            "<b>Message:</b>\n{$preview}{$attachmentNote}\n\n" .
                            "Use /mytickets to view details.";
                    break;

                case 'resolved':
                    $text = "✅ <b>Ticket #{$ticket['ticket_number']} resolved</b>\n\n" .
                            "<b>Subject:</b> {$ticket['subject']}\n\n" .
                            "If you need further help, reply to reopen the ticket.";
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
            error_log("Telegram notification failed: " . $e->getMessage());
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
            error_log("Telegram file not found: {$filePath}");
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
            'caption' => "📎 Attachment from ticket #{$ticketNumber}"
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
            error_log("Telegram file send error: {$error}");
        } else {
            $result = json_decode($response, true);
            if (!($result['ok'] ?? false)) {
                error_log("Telegram file API error: " . ($result['description'] ?? 'Unknown'));
            }
        }
    }

    /**
     * Send satisfaction survey to customer via Telegram
     */
    private function sendTicketSurvey(array $ticket): void
    {
        error_log("sendTicketSurvey: Starting for ticket ID {$ticket['id']}");

        try {
            // Get customer's telegram chat_id
            $user = $this->db->selectOne(
                "SELECT id, telegram_chat_id FROM users WHERE id = ?",
                [$ticket['requester_id']]
            );

            if (!$user || !$user['telegram_chat_id']) {
                error_log("sendTicketSurvey: User not linked to Telegram (requester_id: {$ticket['requester_id']})");
                return; // User not linked to Telegram
            }

            error_log("sendTicketSurvey: Found user with chat_id: {$user['telegram_chat_id']}");

            // Get Telegram config
            $config = $this->db->selectOne(
                "SELECT bot_token FROM telegram_configs WHERE company_id = ? AND is_active = 1",
                [$this->companyId()]
            );

            if (!$config || !$config['bot_token']) {
                error_log("sendTicketSurvey: Telegram not configured for company {$this->companyId()}");
                return; // Telegram not configured
            }

            error_log("sendTicketSurvey: Found bot token, checking for existing survey");

            // Check if survey already sent for this ticket (handle missing table gracefully)
            try {
                $existingSurvey = $this->db->selectOne(
                    "SELECT id FROM ticket_surveys WHERE ticket_id = ?",
                    [$ticket['id']]
                );

                if ($existingSurvey) {
                    error_log("sendTicketSurvey: Survey already exists for ticket {$ticket['id']}");
                    return; // Survey already sent
                }

                error_log("sendTicketSurvey: Creating survey record");

                // Create survey record
                $this->db->insert('ticket_surveys', [
                    'company_id' => $this->companyId(),
                    'ticket_id' => $ticket['id'],
                    'user_id' => $user['id']
                ]);

                error_log("sendTicketSurvey: Survey record created, sending to Telegram");
            } catch (\Exception $e) {
                // Table doesn't exist yet - skip survey tracking but still send the survey
                error_log("sendTicketSurvey: Survey table not found, sending survey without tracking: " . $e->getMessage());
            }

            $chatId = $user['telegram_chat_id'];
            $botToken = $config['bot_token'];

            // Build survey message with rating buttons
            $text = "📊 <b>How was your experience?</b>\n\n" .
                    "Ticket: #{$ticket['ticket_number']}\n" .
                    "Subject: {$ticket['subject']}\n\n" .
                    "Please rate our support:";

            // Rating keyboard
            $keyboard = [
                [
                    ['text' => '⭐⭐⭐⭐⭐ Excellent', 'callback_data' => "survey:5:{$ticket['id']}"],
                    ['text' => '⭐⭐⭐⭐ Good', 'callback_data' => "survey:4:{$ticket['id']}"]
                ],
                [
                    ['text' => '⭐⭐⭐ Okay', 'callback_data' => "survey:3:{$ticket['id']}"],
                    ['text' => '⭐⭐ Poor', 'callback_data' => "survey:2:{$ticket['id']}"]
                ],
                [
                    ['text' => '⭐ Very Poor', 'callback_data' => "survey:1:{$ticket['id']}"]
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
                error_log("sendTicketSurvey: Curl error: {$error}");
            } else {
                $result = json_decode($response, true);
                if ($result['ok'] ?? false) {
                    error_log("sendTicketSurvey: Survey sent successfully to chat {$chatId}");
                } else {
                    error_log("sendTicketSurvey: Telegram API error: " . ($result['description'] ?? 'Unknown'));
                }
            }

        } catch (\Exception $e) {
            error_log("sendTicketSurvey EXCEPTION: " . $e->getMessage());
        }
    }
}
