<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\Category;
use App\Models\ActivityLog;
use App\Services\AI\TicketClassifier;
use App\Helpers\FileUploader;
use App\Services\Telegram\TelegramAgentNotifier;

class CustomerController extends Controller
{
    public function tickets(): void
    {
        $this->requireAuth();

        $ticketModel = new Ticket($this->db);
        $ticketModel->setCompanyId($this->companyId());

        $page = (int) $this->request->query('page', 1);
        $status = $this->request->query('status');

        $filters = ['requester_id' => $this->auth->id()];
        if ($status) {
            $filters['status'] = $status;
        }

        $tickets = $ticketModel->getFiltered($filters, $page, 10);

        $this->view('customers/tickets', [
            'tickets' => $tickets,
            'currentStatus' => $status,
        ]);
    }

    public function createTicket(): void
    {
        $this->requireAuth();

        $categoryModel = new Category($this->db);
        $categoryModel->setCompanyId($this->companyId());
        $categories = $categoryModel->getActive();

        $this->view('customers/create_ticket', [
            'categories' => $categories,
        ]);
    }

    public function storeTicket(): void
    {
        $this->requireAuth();

        $data = $this->request->only(['subject', 'description', 'category_id']);

        // Validate
        if (empty($data['subject']) || empty($data['description'])) {
            $this->response->withError('Subject and description are required')->withInput();
            $this->redirect($this->app->url('customer/tickets/create'));
            return;
        }

        $ticketModel = new Ticket($this->db);
        $ticketModel->setCompanyId($this->companyId());

        $messageModel = new TicketMessage($this->db);

        // Generate ticket number
        $ticketNumber = $ticketModel->generateTicketNumber();

        // AI Classification
        $aiClassification = $this->classifyTicket($data['subject'], $data['description']);

        // Validate priority against allowed ENUM values
        $validPriorities = ['low', 'medium', 'high', 'urgent'];
        $priority = $aiClassification['priority'] ?? 'medium';
        if (!in_array($priority, $validPriorities)) {
            $priority = 'medium';
        }
        $aiPriority = $aiClassification['priority'] ?? null;
        if ($aiPriority && !in_array($aiPriority, $validPriorities)) {
            $aiPriority = null;
        }

        $user = $this->auth->user();

        // Create ticket
        $ticketId = $ticketModel->create([
            'company_id' => $this->companyId(),
            'ticket_number' => $ticketNumber,
            'subject' => $data['subject'],
            'description' => $data['description'],
            'status' => 'open',
            'priority' => $priority,
            'source' => 'web',
            'category_id' => $data['category_id'] ?: ($aiClassification['category_id'] ?? null),
            'requester_id' => $this->auth->id(),
            'requester_email' => $user['email'],
            'requester_name' => $user['name'],
            'ai_suggested_category' => $aiClassification['category_id'] ?? null,
            'ai_suggested_priority' => $aiPriority,
            'ai_confidence_score' => $aiClassification['confidence'] ?? null,
        ]);

        // Add initial message
        $messageModel->addReply(
            $ticketId,
            $this->auth->id(),
            $data['description'],
            false,
            'web'
        );

        // Log activity
        $activityModel = new ActivityLog($this->db);
        $activityModel->log(
            $this->companyId(),
            $ticketId,
            $this->auth->id(),
            'ticket_created',
            "Ticket {$ticketNumber} created via customer portal"
        );

        $this->response->withSuccess("Your ticket {$ticketNumber} has been submitted. We'll get back to you soon!");
        $this->redirect($this->app->url("customer/tickets/{$ticketId}"));
    }

    public function showTicket(string $id): void
    {
        $this->requireAuth();

        $ticketModel = new Ticket($this->db);
        $ticketModel->setCompanyId($this->companyId());

        $ticket = $ticketModel->getWithRelations((int) $id);

        // Verify ownership
        if (!$ticket || $ticket['requester_id'] != $this->auth->id()) {
            http_response_code(404);
            $this->view('errors/404');
            return;
        }

        $messageModel = new TicketMessage($this->db);
        $messages = $messageModel->getByTicket((int) $id, false); // Don't include internal notes

        $this->view('customers/show_ticket', [
            'ticket' => $ticket,
            'messages' => $messages,
        ]);
    }

    public function replyTicket(string $id): void
    {
        $this->requireAuth();

        $ticketModel = new Ticket($this->db);
        $ticketModel->setCompanyId($this->companyId());

        $ticket = $ticketModel->find((int) $id);

        // Verify ownership
        if (!$ticket || $ticket['requester_id'] != $this->auth->id()) {
            $this->error('Ticket not found', 404);
            return;
        }

        $message = $this->request->input('message');

        if (empty($message) && empty($_FILES['attachments']['name'][0])) {
            if ($this->request->isAjax()) {
                $this->error('Message or attachment is required', 422);
                return;
            }
            $this->response->withError('Message or attachment is required');
            $this->redirect($this->app->url("customer/tickets/{$id}"));
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
                $this->response->withError('File upload failed: ' . $e->getMessage());
                $this->redirect($this->app->url("customer/tickets/{$id}"));
                return;
            }
        }

        $messageModel = new TicketMessage($this->db);
        $messageId = $messageModel->addReply(
            (int) $id,
            $this->auth->id(),
            $message ?: '[Attachment]',
            false,
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

        // Reopen ticket if it was resolved/closed
        if (in_array($ticket['status'], ['resolved', 'closed'])) {
            $ticketModel->updateStatus((int) $id, 'open');
        }

        // Log activity
        $activityModel = new ActivityLog($this->db);
        $attachmentCount = count($attachments);
        $description = 'Customer replied to ticket';
        if ($attachmentCount > 0) {
            $description .= " with {$attachmentCount} attachment(s)";
        }
        $activityModel->log(
            $this->companyId(),
            (int) $id,
            $this->auth->id(),
            'customer_reply',
            $description
        );

        if (!empty($ticket['assigned_to'])) {
            $preview = $message ?: '[Attachment]';
            if (strlen($preview) > 200) {
                $preview = substr($preview, 0, 200) . '...';
            }
            $assignedUser = $this->db->selectOne(
                "SELECT name FROM users WHERE id = ? AND company_id = ?",
                [$ticket['assigned_to'], $this->companyId()]
            );
            $assignedName = $assignedUser['name'] ?? 'Agent';
            $link = $this->app->url("tickets/{$id}");
            $subject = htmlspecialchars($ticket['subject'] ?? '', ENT_QUOTES | ENT_HTML5);
            $requesterName = htmlspecialchars($ticket['requester_name'] ?? '', ENT_QUOTES | ENT_HTML5);
            $requesterEmail = htmlspecialchars($ticket['requester_email'] ?? '', ENT_QUOTES | ENT_HTML5);
            $assignedLabel = htmlspecialchars($assignedName, ENT_QUOTES | ENT_HTML5);
            $status = ucfirst(str_replace('_', ' ', $ticket['status'] ?? 'open'));
            $priority = ucfirst($ticket['priority'] ?? 'medium');
            $previewSafe = htmlspecialchars($preview, ENT_QUOTES | ENT_HTML5);
            $text = "💬 <b>New Customer Reply</b>\n" .
                "🆔 <b>Ticket:</b> #{$ticket['ticket_number']}\n" .
                "📝 <b>Subject:</b> {$subject}\n" .
                "📌 <b>Status:</b> {$status}\n" .
                "🚦 <b>Priority:</b> {$priority}\n" .
                "👤 <b>Requester:</b> {$requesterName} ({$requesterEmail})\n" .
                "🎯 <b>Assigned to:</b> {$assignedLabel}\n" .
                "✉️ <b>Message:</b> {$previewSafe}\n" .
                "🔗 <b>Link:</b> {$link}";
            $notifier = new TelegramAgentNotifier($this->db, $this->companyId());
            $notifier->notifyAssignedAgent($ticket, $text);
        }

        if ($this->request->isAjax()) {
            $this->success([], 'Reply sent successfully');
            return;
        }

        $this->response->withSuccess('Your reply has been sent');
        $this->redirect($this->app->url("customer/tickets/{$id}"));
    }

    public function profile(): void
    {
        $this->requireAuth();
        $this->view('customers/profile');
    }

    public function updateProfile(): void
    {
        $this->requireAuth();

        $data = $this->request->only(['name', 'phone', 'current_password', 'new_password']);

        $updates = [];

        if (!empty($data['name'])) {
            $updates['name'] = $data['name'];
        }

        if (isset($data['phone'])) {
            $updates['phone'] = $data['phone'];
        }

        // Password change
        if (!empty($data['new_password'])) {
            if (empty($data['current_password'])) {
                $this->response->withError('Current password is required to change password');
                $this->redirect($this->app->url('customer/profile'));
                return;
            }

            $user = $this->auth->user();
            if (!password_verify($data['current_password'], $user['password_hash'])) {
                $this->response->withError('Current password is incorrect');
                $this->redirect($this->app->url('customer/profile'));
                return;
            }

            $updates['password_hash'] = $this->auth->hashPassword($data['new_password']);
        }

        if (!empty($updates)) {
            $this->db->update('users', $updates, 'id = ?', [$this->auth->id()]);
            $this->response->withSuccess('Profile updated successfully');
        }

        $this->redirect($this->app->url('customer/profile'));
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
}
