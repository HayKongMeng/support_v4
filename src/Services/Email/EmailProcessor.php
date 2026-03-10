<?php

namespace App\Services\Email;

use App\Core\Database;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\ActivityLog;
use App\Services\AI\TicketClassifier;
use App\Services\Workflow\WorkflowRouter;

class EmailProcessor
{
    private Database $db;
    private array $config;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Process emails for all active email configurations
     */
    public function processAll(): array
    {
        $results = [];

        $configs = $this->db->select(
            "SELECT ec.*, c.name as company_name
             FROM email_configs ec
             JOIN companies c ON ec.company_id = c.id
             WHERE ec.is_active = 1"
        );

        foreach ($configs as $config) {
            try {
                $processed = $this->processConfig($config);
                $results[] = [
                    'company' => $config['company_name'],
                    'processed' => $processed,
                    'status' => 'success',
                ];

                // Update last checked timestamp
                $this->db->update('email_configs', [
                    'last_checked_at' => date('Y-m-d H:i:s'),
                    'last_error' => null,
                ], 'id = ?', [$config['id']]);

            } catch (\Exception $e) {
                $results[] = [
                    'company' => $config['company_name'],
                    'error' => $e->getMessage(),
                    'status' => 'error',
                ];

                // Log error
                $this->db->update('email_configs', [
                    'last_checked_at' => date('Y-m-d H:i:s'),
                    'last_error' => $e->getMessage(),
                ], 'id = ?', [$config['id']]);
            }
        }

        return $results;
    }

    /**
     * Process emails for a specific configuration
     */
    private function processConfig(array $config): int
    {
        $processed = 0;

        // Connect to IMAP
        $mailbox = $this->connectImap($config);

        try {
            // Search for unread emails
            $emails = imap_search($mailbox, 'UNSEEN');

            if (!$emails) {
                imap_close($mailbox);
                return 0;
            }

            foreach ($emails as $emailNumber) {
                $this->processEmail($mailbox, $emailNumber, $config);
                $processed++;
            }

            imap_close($mailbox);

        } catch (\Exception $e) {
            imap_close($mailbox);
            throw $e;
        }

        return $processed;
    }

    /**
     * Connect to IMAP server
     */
    private function connectImap(array $config)
    {
        $encryption = $config['imap_encryption'] ?? 'ssl';
        $flags = '/imap';

        if ($encryption === 'ssl') {
            $flags .= '/ssl';
        } elseif ($encryption === 'tls') {
            $flags .= '/tls';
        }

        $flags .= '/novalidate-cert';

        $mailboxPath = "{{$config['imap_host']}:{$config['imap_port']}{$flags}}INBOX";

        // Clear prior IMAP error stack so we can report only this connection attempt.
        if (function_exists('imap_errors')) {
            imap_errors();
        }

        $mailbox = @imap_open(
            $mailboxPath,
            $config['imap_username'],
            $config['imap_password']
        );

        if ($mailbox !== false) {
            return $mailbox;
        }

        $errors = function_exists('imap_errors') ? (imap_errors() ?: []) : [];
        $lastError = !empty($errors) ? (string) end($errors) : (string) imap_last_error();
        if ($lastError === '') {
            $lastError = 'Unknown IMAP error';
        }

        throw new \Exception(sprintf(
            'Failed to connect to IMAP server (%s:%s, %s): %s',
            (string) $config['imap_host'],
            (string) $config['imap_port'],
            (string) $encryption,
            $lastError
        ));
    }

    /**
     * Process a single email
     */
    private function processEmail($mailbox, int $emailNumber, array $config): void
    {
        $header = imap_headerinfo($mailbox, $emailNumber);
        $structure = imap_fetchstructure($mailbox, $emailNumber);

        // Extract email data
        $fromEmail = $header->from[0]->mailbox . '@' . $header->from[0]->host;
        $fromName = isset($header->from[0]->personal)
            ? imap_utf8($header->from[0]->personal)
            : $fromEmail;
        $subject = isset($header->subject) ? imap_utf8($header->subject) : 'No Subject';
        $messageId = $header->message_id ?? null;
        $inReplyTo = $header->in_reply_to ?? null;
        $references = $header->references ?? null;

        // Get email body
        $body = $this->getEmailBody($mailbox, $emailNumber, $structure);
        $fromName = $this->sanitizeDbText($fromName);
        $subject = $this->sanitizeDbText($subject);
        $body = $this->sanitizeDbText($body);
        if ($body === '') {
            $body = 'No content';
        }

        // Check if this is a reply to an existing ticket
        $existingTicket = null;

        // Check by References/In-Reply-To header
        if ($inReplyTo || $references) {
            $existingTicket = $this->findTicketByEmailMessageId($inReplyTo ?? $references, $config['company_id']);
        }

        // Check by ticket number in subject (e.g., [TKT-000001])
        if (!$existingTicket && preg_match('/\[([A-Z]+-\d+)\]/', $subject, $matches)) {
            $ticketNumber = $matches[1];
            $existingTicket = $this->findTicketByNumber($ticketNumber, $config['company_id']);
        }

        if ($existingTicket) {
            // Add reply to existing ticket
            $this->addReplyToTicket($existingTicket, $fromEmail, $fromName, $body, $messageId, $config);
        } else {
            // Create new ticket
            $this->createTicketFromEmail($fromEmail, $fromName, $subject, $body, $messageId, $config);
        }

        // Mark email as seen
        imap_setflag_full($mailbox, (string)$emailNumber, "\\Seen");
    }

    /**
     * Get email body (plain text preferred)
     */
    private function getEmailBody($mailbox, int $emailNumber, $structure): string
    {
        $body = '';
        $charset = null;

        if ($structure->type === 0) {
            // Plain text
            $body = imap_fetchbody($mailbox, $emailNumber, 1);
            $body = $this->decodeBody($body, $structure->encoding);
            $charset = $this->extractCharset($structure);
        } elseif ($structure->type === 1) {
            // Multipart
            foreach ($structure->parts as $partNumber => $part) {
                if ($part->subtype === 'PLAIN') {
                    $body = imap_fetchbody($mailbox, $emailNumber, $partNumber + 1);
                    $body = $this->decodeBody($body, $part->encoding);
                    $charset = $this->extractCharset($part);
                    break;
                }
            }
        }

        $body = $this->normalizeToUtf8($body, $charset);

        // Clean up the body
        $body = trim($body);

        // Remove quoted replies (lines starting with >)
        $lines = explode("\n", $body);
        $cleanLines = array_filter($lines, function($line) {
            return strpos(trim($line), '>') !== 0;
        });
        $body = implode("\n", $cleanLines);

        return trim($body);
    }

    /**
     * Decode email body based on encoding
     */
    private function decodeBody(string $body, int $encoding): string
    {
        switch ($encoding) {
            case 0: // 7BIT
            case 1: // 8BIT
                return $body;
            case 2: // BINARY
                return $body;
            case 3: // BASE64
                return base64_decode($body);
            case 4: // QUOTED-PRINTABLE
                return quoted_printable_decode($body);
            default:
                return $body;
        }
    }

    /**
     * Extract charset from IMAP part metadata.
     */
    private function extractCharset($part): ?string
    {
        if (!is_object($part)) {
            return null;
        }

        foreach (['parameters', 'dparameters'] as $property) {
            if (!property_exists($part, $property) || !is_array($part->{$property})) {
                continue;
            }

            foreach ($part->{$property} as $param) {
                if (!is_object($param)) {
                    continue;
                }

                $name = strtoupper((string) ($param->attribute ?? ''));
                if ($name === 'CHARSET') {
                    $value = trim((string) ($param->value ?? ''));
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Convert arbitrary decoded email text into valid UTF-8 for database inserts.
     */
    private function normalizeToUtf8(string $text, ?string $preferredCharset = null): string
    {
        if ($text === '') {
            return $text;
        }

        if ($this->isValidUtf8($text)) {
            return $text;
        }

        $charsets = [];
        if (!empty($preferredCharset)) {
            $charsets[] = $preferredCharset;
        }
        $charsets = array_merge($charsets, ['ISO-8859-1', 'Windows-1252', 'UTF-8']);
        $charsets = array_values(array_unique(array_map('strtoupper', $charsets)));

        foreach ($charsets as $charset) {
            $converted = $this->convertEncoding($text, $charset);
            if ($converted !== null && $this->isValidUtf8($converted)) {
                return $converted;
            }
        }

        // Last resort: drop invalid byte sequences to keep DB write safe.
        $safe = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
        if (is_string($safe) && $safe !== '') {
            return $safe;
        }

        return preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $text) ?? '';
    }

    private function convertEncoding(string $text, string $fromCharset): ?string
    {
        $fromCharset = trim($fromCharset);
        if ($fromCharset === '') {
            return null;
        }

        $converted = @iconv($fromCharset, 'UTF-8//IGNORE', $text);
        if (is_string($converted) && $converted !== '') {
            return $converted;
        }

        if (function_exists('mb_convert_encoding')) {
            try {
                $converted = @mb_convert_encoding($text, 'UTF-8', $fromCharset);
                if (is_string($converted) && $converted !== '') {
                    return $converted;
                }
            } catch (\Throwable $e) {
                // ignore and continue trying other encodings
            }
        }

        return null;
    }

    private function isValidUtf8(string $text): bool
    {
        if (function_exists('mb_check_encoding')) {
            return mb_check_encoding($text, 'UTF-8');
        }

        return preg_match('//u', $text) === 1;
    }

    /**
     * Final guard before DB insert: normalize to UTF-8 and strip unsafe control bytes.
     */
    private function sanitizeDbText(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $text = str_replace("\0", '', $text);
        $text = $this->normalizeToUtf8($text);

        if (!$this->isValidUtf8($text)) {
            $safe = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
            $text = is_string($safe) ? $safe : '';
        }

        // Keep tab/newline/carriage return; remove other control chars.
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;
        return trim($text);
    }

    /**
     * Find ticket by email Message-ID
     */
    private function findTicketByEmailMessageId(string $messageId, int $companyId): ?array
    {
        $message = $this->db->selectOne(
            "SELECT tm.ticket_id, t.*
             FROM ticket_messages tm
             JOIN tickets t ON tm.ticket_id = t.id
             WHERE tm.email_message_id = ? AND t.company_id = ?",
            [$messageId, $companyId]
        );

        return $message;
    }

    /**
     * Find ticket by ticket number
     */
    private function findTicketByNumber(string $ticketNumber, int $companyId): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM tickets WHERE ticket_number = ? AND company_id = ?",
            [$ticketNumber, $companyId]
        );
    }

    /**
     * Add reply to existing ticket
     */
    private function addReplyToTicket(array $ticket, string $email, string $name, string $body, ?string $messageId, array $config): void
    {
        $name = $this->sanitizeDbText($name);
        $body = $this->sanitizeDbText($body);
        if ($body === '') {
            $body = 'No content';
        }

        // Get or create user
        $userModel = new User($this->db);
        $userId = $userModel->createCustomerIfNotExists($email, $name, $config['company_id']);

        // Add message
        $messageModel = new TicketMessage($this->db);
        $messageModel->create([
            'ticket_id' => $ticket['id'],
            'user_id' => $userId,
            'message' => $body,
            'message_html' => nl2br(htmlspecialchars($body)),
            'is_internal' => 0,
            'source' => 'email',
            'email_message_id' => $messageId,
        ]);

        // Reopen ticket if it was resolved/closed
        if (in_array($ticket['status'], ['resolved', 'closed'])) {
            $this->db->update('tickets', ['status' => 'open'], 'id = ?', [$ticket['id']]);
        }

        // Update ticket
        $this->db->update('tickets', ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$ticket['id']]);

        // Log activity
        $activityModel = new ActivityLog($this->db);
        $activityModel->log(
            $config['company_id'],
            $ticket['id'],
            $userId,
            'email_reply_received',
            'Reply received via email'
        );
    }

    /**
     * Create new ticket from email
     */
    private function createTicketFromEmail(string $email, string $name, string $subject, string $body, ?string $messageId, array $config): void
    {
        $name = $this->sanitizeDbText($name);
        $subject = $this->sanitizeDbText($subject);
        $body = $this->sanitizeDbText($body);
        if ($subject === '') {
            $subject = 'No Subject';
        }
        if ($body === '') {
            $body = 'No content';
        }

        // Get or create user
        $userModel = new User($this->db);
        $userId = $userModel->createCustomerIfNotExists($email, $name, $config['company_id']);

        // Generate ticket number
        $ticketModel = new Ticket($this->db);
        $ticketModel->setCompanyId($config['company_id']);
        $ticketNumber = $ticketModel->generateTicketNumber();

        // AI Classification
        $classifier = new TicketClassifier($this->db, $config['company_id']);
        $classification = $classifier->classify($subject . ' ' . $body);

        $categoryId = isset($classification['category_id']) ? (int) $classification['category_id'] : 0;
        if ($categoryId <= 0) {
            $defaultCategory = isset($config['default_category_id']) ? (int) $config['default_category_id'] : 0;
            $categoryId = $defaultCategory > 0 ? $defaultCategory : null;
        }

        $workflowResolved = null;
        $assignedTo = null;
        try {
            $workflowRouter = new WorkflowRouter($this->db, (int) $config['company_id']);
            $workflowResolved = $workflowRouter->resolveForTicket('email', $categoryId, $userId);
            $assignedTo = $workflowResolved['assignee_id'] ?? null;
        } catch (\Throwable $e) {
            error_log('[EmailProcessor] Routing resolve failed: ' . $e->getMessage());
        }

        // Create ticket
        $ticketId = $ticketModel->create([
            'company_id' => $config['company_id'],
            'ticket_number' => $ticketNumber,
            'subject' => $subject,
            'description' => $body,
            'status' => 'open',
            'priority' => $classification['priority'] ?? 'medium',
            'source' => 'email',
            'category_id' => $categoryId,
            'assigned_to' => $assignedTo,
            'requester_id' => $userId,
            'requester_email' => $email,
            'requester_name' => $name,
            'ai_suggested_category' => $classification['category_id'] ?? null,
            'ai_suggested_priority' => $classification['priority'] ?? null,
            'ai_confidence_score' => $classification['confidence'] ?? null,
        ]);

        if (is_array($workflowResolved)) {
            try {
                $workflowRouter ??= new WorkflowRouter($this->db, (int) $config['company_id']);
                $workflowRouter->startTicketWorkflow($ticketId, $userId, $workflowResolved);
            } catch (\Throwable $e) {
                error_log('[EmailProcessor] Routing start failed: ' . $e->getMessage());
            }
        }

        // Add initial message
        $messageModel = new TicketMessage($this->db);
        $messageModel->create([
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'message' => $body,
            'message_html' => nl2br(htmlspecialchars($body)),
            'is_internal' => 0,
            'source' => 'email',
            'email_message_id' => $messageId,
        ]);

        // Log activity
        $activityModel = new ActivityLog($this->db);
        $activityModel->log(
            $config['company_id'],
            $ticketId,
            $userId,
            'ticket_created',
            "Ticket {$ticketNumber} created from email"
        );

        // Send confirmation email
        $this->sendConfirmationEmail($email, $name, $ticketNumber, $subject, $config);
    }

    /**
     * Send ticket confirmation email
     */
    private function sendConfirmationEmail(string $email, string $name, string $ticketNumber, string $subject, array $config): void
    {
        try {
            $emailSender = new EmailSender($this->db, $config['company_id']);
            $emailSender->send(
                $email,
                $name,
                "Re: [{$ticketNumber}] {$subject}",
                "Your support ticket has been received.\n\n" .
                "Ticket Number: {$ticketNumber}\n" .
                "Subject: {$subject}\n\n" .
                "We will respond to your request as soon as possible.\n\n" .
                "Please keep this ticket number for your reference. You can reply to this email to add more information to your ticket.\n\n" .
                "Thank you for contacting us."
            );
        } catch (\Exception $e) {
            error_log("Failed to send confirmation email: " . $e->getMessage());
        }
    }
}
