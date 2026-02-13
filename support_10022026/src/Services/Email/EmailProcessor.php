<?php

namespace App\Services\Email;

use App\Core\Database;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\ActivityLog;
use App\Services\AI\TicketClassifier;

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

        if (!$mailbox) {
            throw new \Exception("Failed to connect to IMAP server");
        }

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

        return @imap_open(
            $mailboxPath,
            $config['imap_username'],
            $config['imap_password']
        );
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

        if ($structure->type === 0) {
            // Plain text
            $body = imap_fetchbody($mailbox, $emailNumber, 1);
            $body = $this->decodeBody($body, $structure->encoding);
        } elseif ($structure->type === 1) {
            // Multipart
            foreach ($structure->parts as $partNumber => $part) {
                if ($part->subtype === 'PLAIN') {
                    $body = imap_fetchbody($mailbox, $emailNumber, $partNumber + 1);
                    $body = $this->decodeBody($body, $part->encoding);
                    break;
                }
            }
        }

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

        // Create ticket
        $ticketId = $ticketModel->create([
            'company_id' => $config['company_id'],
            'ticket_number' => $ticketNumber,
            'subject' => $subject,
            'description' => $body,
            'status' => 'open',
            'priority' => $classification['priority'] ?? 'medium',
            'source' => 'email',
            'category_id' => $classification['category_id'] ?? $config['default_category_id'],
            'requester_id' => $userId,
            'requester_email' => $email,
            'requester_name' => $name,
            'ai_suggested_category' => $classification['category_id'] ?? null,
            'ai_suggested_priority' => $classification['priority'] ?? null,
            'ai_confidence_score' => $classification['confidence'] ?? null,
        ]);

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
