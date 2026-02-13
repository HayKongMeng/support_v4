<?php

namespace App\Services\Email;

use App\Core\Database;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailSender
{
    private Database $db;
    private int $companyId;
    private ?array $config = null;

    public function __construct(Database $db, int $companyId)
    {
        $this->db = $db;
        $this->companyId = $companyId;
        $this->loadConfig();
    }

    /**
     * Load email configuration for the company
     */
    private function loadConfig(): void
    {
        $this->config = $this->db->selectOne(
            "SELECT * FROM email_configs WHERE company_id = ? AND is_active = 1",
            [$this->companyId]
        );
    }

    /**
     * Send an email
     */
    public function send(string $toEmail, string $toName, string $subject, string $body, array $attachments = []): bool
    {
        if (!$this->config) {
            throw new \Exception("Email not configured for this company");
        }

        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['smtp_username'];
            $mail->Password = $this->config['smtp_password'];

            $encryption = $this->config['smtp_encryption'] ?? 'tls';
            if ($encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }

            $mail->Port = $this->config['smtp_port'] ?? 587;

            // Recipients
            $mail->setFrom($this->config['from_email'], $this->config['from_name']);
            $mail->addAddress($toEmail, $toName);
            $mail->addReplyTo($this->config['from_email'], $this->config['from_name']);

            // Content
            $mail->isHTML(false);
            $mail->Subject = $subject;
            $mail->Body = $body;

            // Attachments
            foreach ($attachments as $attachment) {
                if (isset($attachment['path']) && file_exists($attachment['path'])) {
                    $mail->addAttachment(
                        $attachment['path'],
                        $attachment['name'] ?? basename($attachment['path'])
                    );
                }
            }

            $mail->send();
            return true;

        } catch (Exception $e) {
            error_log("Email send failed: " . $mail->ErrorInfo);
            throw new \Exception("Failed to send email: " . $mail->ErrorInfo);
        }
    }

    /**
     * Send ticket notification to customer
     */
    public function sendTicketNotification(array $ticket, string $type = 'created'): bool
    {
        $templates = [
            'created' => [
                'subject' => "[{$ticket['ticket_number']}] Ticket Created: {$ticket['subject']}",
                'body' => "Hello {$ticket['requester_name']},\n\n" .
                    "Your support ticket has been created.\n\n" .
                    "Ticket Number: {$ticket['ticket_number']}\n" .
                    "Subject: {$ticket['subject']}\n\n" .
                    "We will respond to your request as soon as possible.\n\n" .
                    "You can reply to this email to add more information.\n\n" .
                    "Thank you for contacting us.",
            ],
            'replied' => [
                'subject' => "Re: [{$ticket['ticket_number']}] {$ticket['subject']}",
                'body' => "Hello {$ticket['requester_name']},\n\n" .
                    "There is a new reply to your support ticket.\n\n" .
                    "Ticket Number: {$ticket['ticket_number']}\n\n" .
                    "Please log in to your customer portal to view the response.\n\n" .
                    "You can also reply to this email to continue the conversation.\n\n" .
                    "Thank you.",
            ],
            'resolved' => [
                'subject' => "Re: [{$ticket['ticket_number']}] Ticket Resolved: {$ticket['subject']}",
                'body' => "Hello {$ticket['requester_name']},\n\n" .
                    "Your support ticket has been marked as resolved.\n\n" .
                    "Ticket Number: {$ticket['ticket_number']}\n" .
                    "Subject: {$ticket['subject']}\n\n" .
                    "If you have any further questions, please reply to this email to reopen the ticket.\n\n" .
                    "Thank you for contacting us.",
            ],
        ];

        if (!isset($templates[$type])) {
            return false;
        }

        return $this->send(
            $ticket['requester_email'],
            $ticket['requester_name'],
            $templates[$type]['subject'],
            $templates[$type]['body']
        );
    }

    /**
     * Send notification to agent about new ticket
     */
    public function sendAgentNotification(array $ticket, array $agent): bool
    {
        $subject = "[New Ticket] {$ticket['ticket_number']}: {$ticket['subject']}";
        $body = "Hello {$agent['name']},\n\n" .
            "A new support ticket has been assigned to you.\n\n" .
            "Ticket Number: {$ticket['ticket_number']}\n" .
            "Subject: {$ticket['subject']}\n" .
            "Priority: " . ucfirst($ticket['priority']) . "\n" .
            "From: {$ticket['requester_name']} ({$ticket['requester_email']})\n\n" .
            "Description:\n{$ticket['description']}\n\n" .
            "Please log in to respond to this ticket.";

        return $this->send($agent['email'], $agent['name'], $subject, $body);
    }

    /**
     * Queue an email for later sending
     */
    public function queue(string $toEmail, string $toName, string $subject, string $bodyHtml, string $bodyText = null): int
    {
        return $this->db->insert('email_queue', [
            'company_id' => $this->companyId,
            'to_email' => $toEmail,
            'to_name' => $toName,
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText ?? strip_tags($bodyHtml),
        ]);
    }

    /**
     * Process email queue
     */
    public function processQueue(int $limit = 50): int
    {
        $sent = 0;

        $emails = $this->db->select(
            "SELECT * FROM email_queue
             WHERE company_id = ? AND sent_at IS NULL AND attempts < 3
             ORDER BY created_at ASC
             LIMIT ?",
            [$this->companyId, $limit]
        );

        foreach ($emails as $email) {
            try {
                $this->send($email['to_email'], $email['to_name'] ?? '', $email['subject'], $email['body_text']);

                $this->db->update('email_queue', [
                    'sent_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [$email['id']]);

                $sent++;

            } catch (\Exception $e) {
                $this->db->update('email_queue', [
                    'attempts' => $email['attempts'] + 1,
                    'last_error' => $e->getMessage(),
                ], 'id = ?', [$email['id']]);
            }
        }

        return $sent;
    }
}
