<?php
namespace App\Services\Telegram;

use App\Core\Database;
use App\Models\User;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\Category;
use App\Models\ActivityLog;
use App\Services\AI\TicketClassifier;
use App\Services\Telegram\TelegramAgentNotifier;
use App\Services\Workflow\WorkflowRouter;

class TelegramWebhook
{
    private Database $db;
    private int $companyId;
    private TelegramBot $bot;
    private array $config;

    // User state storage (in real app, use Redis or DB)
    private const STATE_NONE = 'none';
    private const STATE_AWAITING_EMAIL = 'awaiting_email';
    private const STATE_AWAITING_SUBJECT = 'awaiting_subject';
    private const STATE_AWAITING_DESCRIPTION = 'awaiting_description';
    private const STATE_AWAITING_TICKET_REPLY = 'awaiting_ticket_reply';

    public function __construct(Database $db, int $companyId)
    {
        $this->db = $db;
        $this->companyId = $companyId;
        $this->loadConfig();

        if ($this->config) {
            $this->bot = new TelegramBot($db, $this->config['bot_token']);
        }
    }

    private function loadConfig(): void
    {
        error_log("[TelegramWebhook] Loading config for company_id: {$this->companyId}");

        $this->config = $this->db->selectOne(
            "SELECT * FROM telegram_configs WHERE company_id = ? AND is_active = 1",
            [$this->companyId]
        );

        if ($this->config) {
            error_log("[TelegramWebhook] Config loaded successfully, bot_token prefix: " . substr($this->config['bot_token'], 0, 10) . "...");
        } else {
            error_log("[TelegramWebhook] WARNING: No active config found for company {$this->companyId}");

            // Debug: check if any config exists
            $anyConfig = $this->db->selectOne(
                "SELECT id, company_id, is_active FROM telegram_configs WHERE company_id = ?",
                [$this->companyId]
            );
            if ($anyConfig) {
                error_log("[TelegramWebhook] Found config but is_active=" . $anyConfig['is_active']);
            } else {
                error_log("[TelegramWebhook] No config exists at all for company {$this->companyId}");
            }
        }
    }

    private function getMiniAppUrl(): string
    {
        $appUrl = rtrim((string) ($_ENV['APP_URL'] ?? ''), '/');
        if ($appUrl !== '') {
            $normalized = preg_replace('#/public$#', '', $appUrl);
            $baseUrl = is_string($normalized) && $normalized !== '' ? $normalized : $appUrl;
            return $baseUrl . '/telegram-app.html';
        }

        $forwardedProto = (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
        $proto = $forwardedProto !== ''
            ? trim(explode(',', $forwardedProto)[0])
            : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');

        $forwardedHost = (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? '');
        $hostRaw = $forwardedHost !== '' ? $forwardedHost : (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $host = trim(explode(',', $hostRaw)[0]);

        return $proto . '://' . $host . '/telegram-app.html';
    }

    /**
     * Handle incoming webhook
     */
    public function handle(array $update): void
    {
        error_log("[TelegramWebhook] handle() called for company {$this->companyId}");

        if (!$this->config) {
            error_log("[TelegramWebhook] ERROR: No config found for company {$this->companyId}");
            return;
        }

        try {
            error_log("[TelegramWebhook] Config loaded, processing update");

            // Handle callback queries (button clicks)
            if (isset($update['callback_query'])) {
                error_log("[TelegramWebhook] Processing callback query");
                $this->handleCallbackQuery($update['callback_query']);
                return;
            }

            // Handle regular messages
            if (isset($update['message'])) {
                error_log("[TelegramWebhook] Processing message: " . ($update['message']['text'] ?? 'no text'));
                $this->handleMessage($update['message']);
            }
        } catch (\Throwable $e) {
            error_log("[TelegramWebhook] CRITICAL ERROR in handle(): " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            error_log("[TelegramWebhook] Stack trace: " . $e->getTraceAsString());
        }
    }

    /**
     * Handle incoming message
     */
    private function handleMessage(array $message): void
    {
        try {
            $chatId = (int) ($message['chat']['id'] ?? 0);
            $text = (string) ($message['text'] ?? '');
            $telegramUserInfo = [
                'username' => $message['from']['username'] ?? null,
                'first_name' => $message['from']['first_name'] ?? '',
                'last_name' => $message['from']['last_name'] ?? '',
            ];

            if ($chatId <= 0) {
                return;
            }

            error_log("[TelegramWebhook] handleMessage: chat_id={$chatId}, text='{$text}'");

            if (strpos($text, '/') === 0) {
                $this->handleCommand($chatId, $text, $telegramUserInfo);
                return;
            }

            $this->clearUserState($chatId);
            $this->sendMiniAppPrompt(
                $chatId,
                'Please use the Mini App for support actions.'
            );
        } catch (\Throwable $e) {
            error_log("[TelegramWebhook] ERROR in handleMessage: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
        }
    }

    /**
     * Handle commands
     */
    private function handleCommand(int $chatId, string $text, array $telegramUserInfo): void
    {
        try {
            $parts = explode(' ', trim($text));
            $command = strtolower($parts[0] ?? '');
            $args = array_slice($parts, 1);

            error_log("[TelegramWebhook] handleCommand: {$command} for chat_id: {$chatId}");

            if ($command === '/start') {
                $this->handleStart($chatId, $telegramUserInfo, $args[0] ?? null);
                return;
            }

            $this->clearUserState($chatId);
            $this->sendMiniAppPrompt(
                $chatId,
                'Telegram commands are disabled. Please use the Mini App.'
            );
        } catch (\Throwable $e) {
            error_log("[TelegramWebhook] ERROR in handleCommand: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
        }
    }

    /**
     * Handle /start command
     */
    private function handleStart(int $chatId, array $telegramUserInfo, ?string $startParam = null): void
    {
        error_log("[TelegramWebhook] handleStart called for chat_id: {$chatId}, username: {$telegramUserInfo['username']}");

        try {
            $bindResult = $this->handleStartBindLink($chatId, $startParam, $telegramUserInfo);
            if (($bindResult['handled'] ?? false) && !($bindResult['success'] ?? false)) {
                return;
            }

            $user = $bindResult['user'] ?? $this->getUserByChatId($chatId);
            $welcomeMsg = $this->config['welcome_message'] ??
                "Welcome to our Support Bot! I can help you create and manage support tickets.";

            error_log("[TelegramWebhook] User found: " . ($user ? 'yes' : 'no') . ", sending welcome message");

            if ($user) {
                $keyboard = [
                    [['text' => 'Open Mini App', 'web_app' => ['url' => $this->getMiniAppUrl()]]]
                ];

                $this->bot->sendMessageWithKeyboard($chatId,
                    "{$welcomeMsg}\n\n" .
                    "Welcome, <b>{$user['name']}</b>! Your Telegram account is linked.\n\n" .
                    "Use the button below to open Mini App.",
                    $keyboard);
            } else {
                $this->sendMiniAppPrompt(
                    $chatId,
                    "{$welcomeMsg}\n\n" .
                    "Use the Mini App button below to continue."
                );
            }
            error_log("[TelegramWebhook] Welcome message sent successfully");
        } catch (\Exception $e) {
            error_log("[TelegramWebhook] ERROR in handleStart: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            try {
                $this->bot->sendMessage($chatId, "Sorry, an error occurred. Please try again later.");
            } catch (\Exception $e2) {
                error_log("[TelegramWebhook] CRITICAL: Could not send error message: " . $e2->getMessage());
            }
        }
    }

    private function sendMiniAppPrompt(int $chatId, string $message): void
    {
        $keyboard = [
            [['text' => 'Open Mini App', 'web_app' => ['url' => $this->getMiniAppUrl()]]]
        ];

        $this->bot->sendMessageWithKeyboard($chatId, $message, $keyboard);
    }

    /**
     * Handle /help command
     */
    private function handleHelp(int $chatId): void
    {
        try {
            error_log("[TelegramWebhook] handleHelp called for chat_id: {$chatId}");
            $this->bot->sendMessage($chatId,
                "<b>Available commands</b>\n\n" .
                "/start - Start the bot\n" .
                "/link - Link your support account by email\n" .
                "/newticket - Create a new support ticket\n" .
                "/mytickets - View your tickets\n" .
                "/status [ticket#] - Check ticket status\n" .
                "/help - Show this help message");
            error_log("[TelegramWebhook] handleHelp completed successfully");
        } catch (\Throwable $e) {
            error_log("[TelegramWebhook] ERROR in handleHelp: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
        }
    }

    /**
     * Handle /link command
     */
    private function handleLink(int $chatId, array $telegramUserInfo): void
    {
        try {
            error_log("[TelegramWebhook] handleLink called for chat_id: {$chatId}");
            $this->setUserState($chatId, self::STATE_AWAITING_EMAIL, [
                'telegram_user_info' => $telegramUserInfo,
            ]);
            $this->bot->sendMessage($chatId,
                "Please enter your email address to link your support account.");
            error_log("[TelegramWebhook] handleLink completed successfully");
        } catch (\Throwable $e) {
            error_log("[TelegramWebhook] ERROR in handleLink: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
        }
    }

    /**
     * Handle email input for linking
     */
    private function handleEmailInput(int $chatId, string $email, array $telegramUserInfo): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->bot->sendMessage($chatId,
                "That does not look like a valid email address. Please try again.");
            return;
        }

        $state = $this->getUserState($chatId);
        $storedUserInfo = $state['data']['telegram_user_info'] ?? $telegramUserInfo;

        $displayName = trim(($storedUserInfo['first_name'] ?? '') . ' ' . ($storedUserInfo['last_name'] ?? ''));
        if (empty($displayName) && !empty($storedUserInfo['username'])) {
            $displayName = $storedUserInfo['username'];
        }
        if (empty($displayName)) {
            $displayName = 'Telegram User';
        }

        $userModel = new User($this->db);
        $user = $userModel->findByEmail($email, $this->companyId);

        if (!$user) {
            $this->db->insert('users', [
                'company_id' => $this->companyId,
                'email' => $email,
                'name' => $displayName,
                'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
                'role' => 'customer',
                'telegram_chat_id' => $chatId,
                'is_active' => 1,
            ]);
        } else {
            if (($user['name'] ?? '') === 'Telegram User') {
                $this->db->update('users', ['name' => $displayName], 'id = ?', [$user['id']]);
            }
            $userModel->linkTelegram((int) $user['id'], $chatId, $storedUserInfo['username'] ?? null);
        }

        $this->clearUserState($chatId);
        $this->bot->sendMessage($chatId,
            "Your account has been linked successfully.\n\n" .
            "You can now:\n" .
            "- Create a ticket with /newticket\n" .
            "- View your tickets with /mytickets\n" .
            "- Receive ticket updates on Telegram");
    }

    /**
     * Handle /newticket command
     */
    private function handleNewTicket(int $chatId): void
    {
        $user = $this->getUserByChatId($chatId);

        if (!$user) {
            $this->bot->sendMessage($chatId,
                "Please link your account first using /link");
            return;
        }

        $this->setUserState($chatId, self::STATE_AWAITING_SUBJECT);
        $this->bot->sendMessage($chatId,
            "Let's create a new support ticket.\n\n" .
            "<b>Step 1/2:</b> What is the subject of your issue?");
    }

    /**
     * Handle subject input
     */
    private function handleSubjectInput(int $chatId, string $subject, array $state): void
    {
        if (strlen($subject) < 5) {
            $this->bot->sendMessage($chatId,
                "ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¾Ã‚Â¼ÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¢ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¸Ã¢â‚¬Â¹ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¡ÃƒÂ¡Ã…Â¾Ã‚Â¼ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã¢â‚¬ËœÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã‚Â¢ÃƒÂ¡Ã…Â¾Ã‚Â·ÃƒÂ¡Ã…Â¾Ã‚Â (ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â°ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¾Ã‚Â ÃƒÂ¡Ã…Â¸Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¦ÃƒÂ¡Ã…Â¾Ã…Â½ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â¹ 5 ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¾Ã‚Â½ÃƒÂ¡Ã…Â¾Ã‚Â¢ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¾Ã…Â¡):");
            return;
        }

        $this->setUserState($chatId, self::STATE_AWAITING_DESCRIPTION, ['subject' => $subject]);
        $this->bot->sendMessage($chatId,
            "<b>ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¡ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã‚Â ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ 2/2:</b> ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¾Ã‚Â¼ÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¾Ã¢â‚¬â€œÃƒÂ¡Ã…Â¾Ã‚Â·ÃƒÂ¡Ã…Â¾Ã¢â‚¬â€œÃƒÂ¡Ã…Â¾Ã…Â½ÃƒÂ¡Ã…Â¸Ã…â€™ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã‚Â¢ÃƒÂ¡Ã…Â¾Ã‚Â·ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¾Ã‚Â¢ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬â€œÃƒÂ¡Ã…Â¾Ã‚Â¸ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã¢â‚¬Â°ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã‚Â ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â¹ÃƒÂ¡Ã…Â¾Ã‚Â¢ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬:");
    }

    /**
     * Handle description input and create ticket
     */
    private function handleDescriptionInput(int $chatId, string $description, array $state): void
    {
        if (strlen($description) < 10) {
            $this->bot->sendMessage($chatId,
                "ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¾Ã‚Â¼ÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¢ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¸Ã¢â‚¬Â¹ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¡ÃƒÂ¡Ã…Â¾Ã‚Â¼ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã¢â‚¬â€œÃƒÂ¡Ã…Â¸Ã‚ÂÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¸Ã…â€™ÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã‚Â¢ÃƒÂ¡Ã…Â¾Ã‚Â·ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã‚Â¢ÃƒÂ¡Ã…Â¾Ã‚Â·ÃƒÂ¡Ã…Â¾Ã‚Â (ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â°ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¾Ã‚Â ÃƒÂ¡Ã…Â¸Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¦ÃƒÂ¡Ã…Â¾Ã…Â½ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â¹ 10 ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¾Ã‚Â½ÃƒÂ¡Ã…Â¾Ã‚Â¢ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¾Ã…Â¡):");
            return;
        }

        $user = $this->getUserByChatId($chatId);
        $subject = $state['data']['subject'];

        // Create ticket
        $ticketModel = new Ticket($this->db);
        $ticketModel->setCompanyId($this->companyId);
        $ticketNumber = $ticketModel->generateTicketNumber();

        // AI Classification
        $classifier = new TicketClassifier($this->db, $this->companyId);
        $classification = $classifier->classify($subject . ' ' . $description);

        // Validate priority against allowed ENUM values
        $validPriorities = ['low', 'medium', 'high', 'urgent'];
        $priority = $classification['priority'] ?? 'medium';
        if (!in_array($priority, $validPriorities)) {
            $priority = 'medium';
        }
        $aiPriority = $classification['priority'] ?? null;
        if ($aiPriority && !in_array($aiPriority, $validPriorities)) {
            $aiPriority = null;
        }

        $categoryId = isset($classification['category_id']) ? (int) $classification['category_id'] : 0;
        if ($categoryId <= 0) {
            $defaultCategory = isset($this->config['default_category_id']) ? (int) $this->config['default_category_id'] : 0;
            $categoryId = $defaultCategory > 0 ? $defaultCategory : null;
        }

        $workflowResolved = null;
        $assignedTo = null;
        try {
            $workflowRouter = new WorkflowRouter($this->db, $this->companyId);
            $workflowResolved = $workflowRouter->resolveForTicket('telegram', $categoryId, (int) $user['id']);
            $assignedTo = $workflowResolved['assignee_id'] ?? null;
        } catch (\Throwable $e) {
            error_log("[TelegramWebhook] Workflow resolve failed: " . $e->getMessage());
        }

        $ticketId = $ticketModel->create([
            'company_id' => $this->companyId,
            'ticket_number' => $ticketNumber,
            'subject' => $subject,
            'description' => $description,
            'status' => 'open',
            'priority' => $priority,
            'source' => 'telegram',
            'category_id' => $categoryId,
            'assigned_to' => $assignedTo,
            'requester_id' => $user['id'],
            'requester_email' => $user['email'],
            'requester_name' => $user['name'],
            'ai_suggested_category' => $classification['category_id'] ?? null,
            'ai_suggested_priority' => $aiPriority,
            'ai_confidence_score' => $classification['confidence'] ?? null,
        ]);

        if (is_array($workflowResolved)) {
            try {
                $workflowRouter ??= new WorkflowRouter($this->db, $this->companyId);
                $workflowRouter->startTicketWorkflow($ticketId, (int) $user['id'], $workflowResolved);
            } catch (\Throwable $e) {
                error_log("[TelegramWebhook] Workflow start failed: " . $e->getMessage());
            }
        }

        // Add message
        $messageModel = new TicketMessage($this->db);
        $messageModel->addReply($ticketId, $user['id'], $description, false, 'telegram');

        // Log activity
        $activityModel = new ActivityLog($this->db);
        $activityModel->log(
            $this->companyId,
            $ticketId,
            $user['id'],
            'ticket_created',
            "Ticket {$ticketNumber} created via Telegram"
        );

        // Notify agents/admins about new ticket
        $this->notifyAgents(
            $ticketId,
            'new_ticket',
            "New ticket #{$ticketNumber}",
            "{$user['name']}: {$subject}",
            [
                'source' => 'telegram',
                'requester' => $user['name'] ?? 'Customer',
            ]
        );

        $this->clearUserState($chatId);
        $this->bot->sendMessage($chatId,
            "ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã…Â½ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¾Ã‚Â»ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¡ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚Â½ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â¹ÃƒÂ¡Ã…Â¾Ã‚Â¢ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã‚Â!\n\n" .
            "<b>ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¸Ã‚ÂÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã…Â½ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¾Ã‚Â»ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¡ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚Â½ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢:</b> {$ticketNumber}\n" .
            "<b>ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã¢â‚¬Ëœ:</b> {$subject}\n\n" .
            "ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚Â¹ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¡ÃƒÂ¡Ã…Â¾Ã‚Â¼ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã…Â ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã…Â½ÃƒÂ¡Ã…Â¾Ã‚Â¹ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¾Ã‚Â±ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ÃƒÂ¡Ã…Â¾Ã‚Â¢ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â¦ÃƒÂ¡Ã…Â¾Ã¢â‚¬â€œÃƒÂ¡Ã…Â¸Ã‚ÂÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¾Ã…Â ÃƒÂ¡Ã…Â¸Ã¢â‚¬Å¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬Â");
    }

    /**
     * Handle /mytickets command
     */
    private function handleMyTickets(int $chatId): void
    {
        $user = $this->getUserByChatId($chatId);

        if (!$user) {
            $this->bot->sendMessage($chatId,
                "Please link your email first using /link");
            return;
        }

        $tickets = $this->db->select(
            "SELECT * FROM tickets
             WHERE company_id = ? AND requester_id = ? AND status NOT IN ('closed')
             ORDER BY created_at DESC
             LIMIT 10",
            [$this->companyId, $user['id']]
        );

        if (empty($tickets)) {
            $this->bot->sendMessage($chatId,
                "ÃƒÂ¡Ã…Â¾Ã‚Â¢ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¾Ã‚Â·ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã…Â½ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¾Ã‚Â»ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¡ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚Â½ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã¢â‚¬ËœÃƒÂ¡Ã…Â¾Ã‚Â¹ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¸Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã¢â‚¬ËœÃƒÂ¡Ã…Â¾Ã‚Â¹ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¸Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â\n\nÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã‚Â¾ /newticket ÃƒÂ¡Ã…Â¾Ã…Â ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã‚Â¸ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¾Ã…Â½ÃƒÂ¡Ã…Â¸Ã‚ÂÃƒÂ¡Ã…Â¾Ã…Â ÃƒÂ¡Ã…Â¾Ã‚Â¼ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¦ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â");
            return;
        }

        // Build inline keyboard with tickets
        $keyboard = [];
        foreach ($tickets as $ticket) {
            $status = ucfirst(str_replace('_', ' ', $ticket['status']));
            $keyboard[] = [[
                'text' => "#{$ticket['ticket_number']} - {$status}",
                'callback_data' => "view_ticket:{$ticket['id']}",
            ]];
        }

        $this->bot->sendMessageWithKeyboard($chatId,
            $this->bot->formatTicketList($tickets),
            $keyboard);
    }

    /**
     * Handle /status command
     */
    private function handleStatus(int $chatId, ?string $ticketNumber): void
    {
        $user = $this->getUserByChatId($chatId);

        if (!$user) {
            $this->bot->sendMessage($chatId,
                "ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¾Ã‚Â¼ÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¾Ã¢â‚¬â€ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¡ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬Â¹ÃƒÂ¡Ã…Â¾Ã‚Â¢ÃƒÂ¡Ã…Â¸Ã…Â ÃƒÂ¡Ã…Â¾Ã‚Â¸ÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¸Ã¢â‚¬Å¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â¹ÃƒÂ¡Ã…Â¾Ã‚Â¢ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã…Â ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã‚Â¼ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¾Ã…Â ÃƒÂ¡Ã…Â¸Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã‚Â¾ /link");
            return;
        }

        if (!$ticketNumber) {
            $this->bot->sendMessage($chatId,
                "ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¾Ã‚Â¼ÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¢ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¸Ã¢â‚¬Â¹ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¡ÃƒÂ¡Ã…Â¾Ã‚Â¼ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¸Ã‚ÂÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã…Â½ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¾Ã‚Â»ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¡ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚Â½ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã‚Â§ÃƒÂ¡Ã…Â¾Ã¢â‚¬ËœÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã‚Â ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã…Â½ÃƒÂ¡Ã…Â¸Ã‚Â: /status TKT-000001");
            return;
        }

        $ticket = $this->db->selectOne(
            "SELECT * FROM tickets
             WHERE company_id = ? AND ticket_number = ? AND requester_id = ?",
            [$this->companyId, strtoupper($ticketNumber), $user['id']]
        );

        if (!$ticket) {
            $this->bot->sendMessage($chatId, "ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã…Â½ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¾Ã‚Â»ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¡ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚Â½ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¾Ã‚Â·ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã‚Â¼ÃƒÂ¡Ã…Â¾Ã…â€œÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã†â€™ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â°ÃƒÂ¡Ã…Â¾Ã¢â‚¬ËœÃƒÂ¡Ã…Â¸Ã‚ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã‚Â¢ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã¢â‚¬Â°ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¦ÃƒÂ¡Ã…Â¾Ã‚Â¼ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¸Ã‚ÂÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã…Â½ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¾Ã‚Â»ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¡ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚Â½ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã‚Â¹ÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã‚Â¼ÃƒÂ¡Ã…Â¾Ã…â€œÃƒÂ¡Ã…Â¸Ã¢â‚¬Â");
            return;
        }

        $keyboard = [
            [['text' => 'Reply to Ticket', 'callback_data' => "reply_ticket:{$ticket['id']}"]],
        ];

        $this->bot->sendMessageWithKeyboard($chatId,
            $this->bot->formatTicket($ticket),
            $keyboard);
    }

    /**
     * Handle callback queries
     */
    private function handleCallbackQuery(array $query): void
    {
        $chatId = (int) ($query['message']['chat']['id'] ?? 0);
        $callbackId = (string) ($query['id'] ?? '');

        if ($callbackId !== '') {
            $this->bot->answerCallbackQuery($callbackId);
        }

        if ($chatId > 0) {
            $this->clearUserState($chatId);
            $this->sendMiniAppPrompt(
                $chatId,
                'Please use the Mini App for support actions.'
            );
        }
    }

    /**
     * View ticket details
     */
    private function viewTicket(int $chatId, int $ticketId): void
    {
        $user = $this->getUserByChatId($chatId);

        $ticket = $this->db->selectOne(
            "SELECT * FROM tickets WHERE id = ? AND requester_id = ?",
            [$ticketId, $user['id']]
        );

        if (!$ticket) {
            $this->bot->sendMessage($chatId, "ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã…Â½ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¾Ã‚Â»ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¡ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚Â½ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¾Ã‚Â·ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã‚Â¼ÃƒÂ¡Ã…Â¾Ã…â€œÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã†â€™ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â°ÃƒÂ¡Ã…Â¾Ã¢â‚¬ËœÃƒÂ¡Ã…Â¸Ã‚ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬Â");
            return;
        }

        // Get latest message
        $latestMessage = $this->db->selectOne(
            "SELECT tm.*, u.name as user_name, u.role
             FROM ticket_messages tm
             LEFT JOIN users u ON tm.user_id = u.id
             WHERE tm.ticket_id = ? AND tm.is_internal = 0
             ORDER BY tm.created_at DESC
             LIMIT 1",
            [$ticketId]
        );

        $text = $this->bot->formatTicket($ticket);

        if ($latestMessage) {
            $from = in_array($latestMessage['role'], ['admin', 'agent']) ? 'Support' : 'You';
            $text .= "\n\n<b>Latest Reply ({$from}):</b>\n" .
                htmlspecialchars(substr($latestMessage['message'], 0, 300)) .
                (strlen($latestMessage['message']) > 300 ? '...' : '');
        }

        $keyboard = [
            [['text' => 'Reply', 'callback_data' => "reply_ticket:{$ticketId}"]],
        ];

        $this->bot->sendMessageWithKeyboard($chatId, $text, $keyboard);
    }

    /**
     * Start ticket reply flow
     */
    private function startTicketReply(int $chatId, int $ticketId): void
    {
        $this->setUserState($chatId, self::STATE_AWAITING_TICKET_REPLY, ['ticket_id' => $ticketId]);
        $this->bot->sendMessage($chatId,
            "ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¾Ã‚Â¼ÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¸Ã‚ÂÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â¹ÃƒÂ¡Ã…Â¾Ã‚Â¢ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬:");
    }

    /**
     * Handle ticket reply
     */
    private function handleTicketReply(int $chatId, string $message, array $state): void
    {
        $user = $this->getUserByChatId($chatId);
        $ticketId = $state['data']['ticket_id'];

        $ticket = $this->db->selectOne(
            "SELECT * FROM tickets WHERE id = ? AND requester_id = ?",
            [$ticketId, $user['id']]
        );

        if (!$ticket) {
            $this->clearUserState($chatId);
            $this->bot->sendMessage($chatId, "ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã…Â½ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¾Ã‚Â»ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¡ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚Â½ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¾Ã‚Â·ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã‚Â¼ÃƒÂ¡Ã…Â¾Ã…â€œÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã†â€™ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â°ÃƒÂ¡Ã…Â¾Ã¢â‚¬ËœÃƒÂ¡Ã…Â¸Ã‚ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬Â");
            return;
        }

        // Add reply
        $messageModel = new TicketMessage($this->db);
        $messageModel->addReply($ticketId, $user['id'], $message, false, 'telegram');

        // Reopen if resolved/closed
        if (in_array($ticket['status'], ['resolved', 'closed'])) {
            $this->db->update('tickets', ['status' => 'open'], 'id = ?', [$ticketId]);
        }

        // Log activity
        $activityModel = new ActivityLog($this->db);
        $activityModel->log(
            $this->companyId,
            $ticketId,
            $user['id'],
            'telegram_reply',
            'Customer replied via Telegram'
        );

        // Notify agents/admins about new reply
        $preview = strlen($message) > 100 ? substr($message, 0, 100) . '...' : $message;
        $this->notifyAgents(
            $ticketId,
            'telegram_reply',
            "New reply on #{$ticket['ticket_number']}",
            "{$user['name']}: {$preview}",
            [
                'source' => 'telegram',
                'requester' => $user['name'] ?? 'Customer',
            ]
        );

        if (!empty($ticket['assigned_to'])) {
            $assignedUser = $this->db->selectOne(
                "SELECT name FROM users WHERE id = ? AND company_id = ?",
                [$ticket['assigned_to'], $this->companyId]
            );
            $assignedName = $assignedUser['name'] ?? 'Agent';
            $appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
            $link = $appUrl ? $appUrl . "/tickets/{$ticketId}" : '';
            $subject = htmlspecialchars($ticket['subject'] ?? '', ENT_QUOTES | ENT_HTML5);
            $requesterName = htmlspecialchars($ticket['requester_name'] ?? '', ENT_QUOTES | ENT_HTML5);
            $requesterEmail = htmlspecialchars($ticket['requester_email'] ?? '', ENT_QUOTES | ENT_HTML5);
            $assignedLabel = htmlspecialchars($assignedName, ENT_QUOTES | ENT_HTML5);
            $status = ucfirst(str_replace('_', ' ', $ticket['status'] ?? 'open'));
            $priority = ucfirst($ticket['priority'] ?? 'medium');
            $previewSafe = htmlspecialchars($preview, ENT_QUOTES | ENT_HTML5);
            $text = "ÃƒÂ°Ã…Â¸Ã¢â‚¬â„¢Ã‚Â¬ <b>New Customer Reply</b>\n" .
                "ÃƒÂ°Ã…Â¸Ã¢â‚¬Â Ã¢â‚¬Â <b>Ticket:</b> #{$ticket['ticket_number']}\n" .
                "ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã‚Â <b>Subject:</b> {$subject}\n" .
                "ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã…â€™ <b>Status:</b> {$status}\n" .
                "ÃƒÂ°Ã…Â¸Ã…Â¡Ã‚Â¦ <b>Priority:</b> {$priority}\n" .
                "ÃƒÂ°Ã…Â¸Ã¢â‚¬ËœÃ‚Â¤ <b>Requester:</b> {$requesterName} ({$requesterEmail})\n" .
                "ÃƒÂ°Ã…Â¸Ã…Â½Ã‚Â¯ <b>Assigned to:</b> {$assignedLabel}\n" .
                "ÃƒÂ¢Ã…â€œÃ¢â‚¬Â°ÃƒÂ¯Ã‚Â¸Ã‚Â <b>Message:</b> {$previewSafe}";
            if ($link) {
                $text .= "\nLink: {$link}";
            }
            $notifier = new TelegramAgentNotifier($this->db, $this->companyId);
            $notifier->notifyAssignedAgent($ticket, $text);
        }

        $this->clearUserState($chatId);
        $this->bot->sendMessage($chatId,
            "ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â¹ÃƒÂ¡Ã…Â¾Ã‚Â¢ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã…Â ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã‚Â¡ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¸Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¸Ã¢â‚¬Â¹ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã…Â½ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¾Ã‚Â»ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¡ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚Â½ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ #{$ticket['ticket_number']}ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â\n\n" .
            "ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚Â¹ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¡ÃƒÂ¡Ã…Â¾Ã‚Â¼ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã…Â ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã…Â½ÃƒÂ¡Ã…Â¾Ã‚Â¹ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¾Ã‚Â±ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ÃƒÂ¡Ã…Â¾Ã‚Â¢ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â¦ÃƒÂ¡Ã…Â¾Ã¢â‚¬â€œÃƒÂ¡Ã…Â¸Ã‚ÂÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¾Ã…Â ÃƒÂ¡Ã…Â¸Ã¢â‚¬Å¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬Â");
    }

    /**
     * Send notification to user
     */
    public function sendTicketNotification(array $ticket, string $type = 'reply'): void
    {
        // Get user's Telegram chat ID
        $user = $this->db->selectOne(
            "SELECT telegram_chat_id FROM users WHERE id = ?",
            [$ticket['requester_id']]
        );

        if (!$user || !$user['telegram_chat_id']) {
            return;
        }

        $chatId = $user['telegram_chat_id'];

        switch ($type) {
            case 'reply':
                $text = "ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¾Ã‚Â¸ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã…Â½ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¾Ã‚Â»ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¡ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚Â½ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â¹ÃƒÂ¡Ã…Â¾Ã‚Â¢ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ #{$ticket['ticket_number']}!\n\n" .
                    "<b>ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã¢â‚¬Ëœ:</b> {$ticket['subject']}\n\n" .
                    "ÃƒÂ¡Ã…Â¾Ã¢â‚¬â€œÃƒÂ¡Ã…Â¾Ã‚Â·ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚Â·ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã…Â½ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¾Ã‚Â»ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¡ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚Â½ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ÃƒÂ¡Ã…Â¾Ã…Â ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã‚Â¸ÃƒÂ¡Ã…Â¾Ã…Â ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã…Â½ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã¢â‚¬â€œÃƒÂ¡Ã…Â¸Ã‚ÂÃƒÂ¡Ã…Â¾Ã¢â‚¬Â°ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¸Ã‚ÂÃƒÂ¡Ã…Â¾Ã¢â‚¬Â°ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â";
                break;

            case 'resolved':
                $text = "ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã…Â½ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¾Ã‚Â»ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¡ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚Â½ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â¹ÃƒÂ¡Ã…Â¾Ã‚Â¢ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ #{$ticket['ticket_number']} ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã…Â ÃƒÂ¡Ã…Â¸Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â¡ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢!\n\n" .
                    "<b>ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã¢â‚¬Ëœ:</b> {$ticket['subject']}\n\n" .
                    "ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¾Ã‚Â·ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã‚Â¢ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã‚Â¼ÃƒÂ¡Ã…Â¾Ã…â€œÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¡ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚Â½ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã‚Â¢ÃƒÂ¡Ã…Â¾Ã‚Â·ÃƒÂ¡Ã…Â¾Ã‚Â ÃƒÂ¡Ã…Â¾Ã‚Â¢ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã‚Â¢ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¦ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ÃƒÂ¡Ã…Â¾Ã…Â ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã‚Â¸ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã‚Â¡ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¾Ã…â€œÃƒÂ¡Ã…Â¾Ã‚Â·ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â°ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚Â¼ÃƒÂ¡Ã…Â¾Ã…â€œÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã…Â½ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¾Ã‚Â»ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¡ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚Â½ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â";
                break;

            default:
                return;
        }

        $keyboard = [
            [['text' => 'ÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂºÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã…Â½ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¾Ã‚Â»ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¡ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚Â½ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢', 'callback_data' => "view_ticket:{$ticket['id']}"]],
        ];

        $this->bot->sendMessageWithKeyboard($chatId, $text, $keyboard);
    }
    
    /**
     * Handle file messages (images and other files)
     */
    private function handleFileMessage(int $chatId, array $message, array $telegramUserInfo): void
    {
        try {
            $user = $this->getUserByChatId($chatId);
            
            if (!$user) {
                $this->bot->sendMessage($chatId,
                    "ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¾Ã‚Â¼ÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¾Ã¢â‚¬â€ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¡ÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬Â¹ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¡ÃƒÂ¡Ã…Â¾Ã…Â½ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚Â¸ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â¹ÃƒÂ¡Ã…Â¾Ã‚Â¢ÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã…Â ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã‚Â¼ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¾Ã…Â ÃƒÂ¡Ã…Â¸Ã¢â‚¬Å¾ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã‚Â¾ /link");
                return;
            }
            
            // Get user state to see if they're replying to a ticket
            $state = $this->getUserState($chatId);
            $caption = $message['caption'] ?? '';
            
            // Get file info based on type
            $fileId = null;
            $fileName = null;
            $mimeType = null;
            $fileSize = 0;
            
            if (isset($message['photo'])) {
                // Get largest photo
                $photo = end($message['photo']);
                $fileId = $photo['file_id'];
                $fileName = 'photo_' . time() . '.jpg';
                $mimeType = 'image/jpeg';
                $fileSize = $photo['file_size'] ?? 0;
            } elseif (isset($message['document'])) {
                $doc = $message['document'];
                $fileId = $doc['file_id'];
                $fileName = $doc['file_name'] ?? 'document_' . time();
                $mimeType = $doc['mime_type'] ?? 'application/octet-stream';
                $fileSize = $doc['file_size'] ?? 0;
            } elseif (isset($message['voice'])) {
                $voice = $message['voice'];
                $fileId = $voice['file_id'];
                $mimeType = $voice['mime_type'] ?? 'audio/ogg';
                $fileName = 'voice_' . time() . $this->guessExtension($mimeType, '.ogg');
                $fileSize = $voice['file_size'] ?? 0;
            } elseif (isset($message['audio'])) {
                $audio = $message['audio'];
                $fileId = $audio['file_id'];
                $mimeType = $audio['mime_type'] ?? 'audio/mpeg';
                $fileName = $audio['file_name'] ?? ('audio_' . time() . $this->guessExtension($mimeType, '.mp3'));
                $fileSize = $audio['file_size'] ?? 0;
            }
            
            if (!$fileId) {
                error_log("[TelegramWebhook] No valid file found in message");
                return;
            }
            
            error_log("[TelegramWebhook] Processing file: {$fileName}, size: {$fileSize}");
            
            // Check if user is replying to a ticket
            if ($state['state'] === self::STATE_AWAITING_TICKET_REPLY) {
                $ticketId = $state['data']['ticket_id'];
                $this->handleFileReply($chatId, $ticketId, $fileId, $fileName, $mimeType, $fileSize, $caption, $user);
            } else {
                // Not in a ticket context, inform user
                $this->bot->sendMessage($chatId,
                    "Please use /mytickets to select a ticket, then send your file.\nOr use /newticket to create a new ticket.");
            }
            
        } catch (\Exception $e) {
            error_log("[TelegramWebhook] ERROR in handleFileMessage: " . $e->getMessage());
            $this->bot->sendMessage($chatId, "Error processing the file.");
        }
    }
    
    /**
     * Handle file attachment when replying to ticket
     */
    private function handleFileReply(int $chatId, int $ticketId, string $fileId, string $fileName, string $mimeType, int $fileSize, string $caption, array $user): void
    {
        try {
            // Get file path from Telegram
            $fileInfo = $this->bot->getFile($fileId);
            
            if (!$fileInfo || !isset($fileInfo['file_path'])) {
                error_log("[TelegramWebhook] Failed to get file info from Telegram");
                $this->bot->sendMessage($chatId, "Error downloading the file.");
                return;
            }
            
            $filePath = $fileInfo['file_path'];
            $fileUrl = "https://api.telegram.org/file/bot{$this->config['bot_token']}/{$filePath}";
            
            error_log("[TelegramWebhook] File URL: {$fileUrl}");
            
            // Download file
            $fileContent = @file_get_contents($fileUrl);
            
            if ($fileContent === false) {
                error_log("[TelegramWebhook] Failed to download file");
                $this->bot->sendMessage($chatId, "Error downloading the file.");
                return;
            }
            
            // Create upload directory
            $uploadDir = BASE_PATH . "/public/uploads/tickets/{$ticketId}/" . date('Y/m');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generate unique filename
            $ext = pathinfo($fileName, PATHINFO_EXTENSION);
            $baseName = pathinfo($fileName, PATHINFO_FILENAME);
            $uniqueName = $baseName . '_' . uniqid() . ($ext ? '.' . $ext : '');
            $localPath = $uploadDir . '/' . $uniqueName;
            
            // Save file
            if (file_put_contents($localPath, $fileContent) === false) {
                error_log("[TelegramWebhook] Failed to save file");
                $this->bot->sendMessage($chatId, "Error saving the file.");
                return;
            }
            
            error_log("[TelegramWebhook] File saved: {$localPath}");
            
            // Relative path for database
            $relativePath = "tickets/{$ticketId}/" . date('Y/m') . '/' . $uniqueName;
            
            // Verify ticket belongs to user
            $ticket = $this->db->selectOne(
                "SELECT * FROM tickets WHERE id = ? AND requester_id = ?",
                [$ticketId, $user['id']]
            );
            
            if (!$ticket) {
                $this->clearUserState($chatId);
                $this->bot->sendMessage($chatId, "ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã…Â½ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã…Â¸ÃƒÂ¡Ã…Â¾Ã‚Â»ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â¡ÃƒÂ¡Ã…Â¸Ã¢â‚¬Â ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚Â½ÃƒÂ¡Ã…Â¾Ã¢â€žÂ¢ÃƒÂ¡Ã…Â¾Ã‹Å“ÃƒÂ¡Ã…Â¾Ã‚Â·ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã‚ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬â„¢ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã‚Â¼ÃƒÂ¡Ã…Â¾Ã…â€œÃƒÂ¡Ã…Â¾Ã¢â‚¬ÂÃƒÂ¡Ã…Â¾Ã‚Â¶ÃƒÂ¡Ã…Â¾Ã¢â‚¬Å“ÃƒÂ¡Ã…Â¾Ã…Â¡ÃƒÂ¡Ã…Â¾Ã¢â€šÂ¬ÃƒÂ¡Ã…Â¾Ã†â€™ÃƒÂ¡Ã…Â¾Ã‚Â¾ÃƒÂ¡Ã…Â¾Ã¢â‚¬Â°ÃƒÂ¡Ã…Â¾Ã¢â‚¬ËœÃƒÂ¡Ã…Â¸Ã‚ÂÃƒÂ¡Ã…Â¸Ã¢â‚¬Â");
                return;
            }
            
            // Add message with attachment
            $messageText = $caption ?: $this->defaultAttachmentLabel($mimeType, $fileName);
            $messageModel = new TicketMessage($this->db);
            $messageId = $messageModel->addReply($ticketId, $user['id'], $messageText, false, 'telegram');
            
            // Save attachment to database
            $this->db->insert('attachments', [
                'ticket_id' => $ticketId,
                'message_id' => $messageId,
                'user_id' => $user['id'],
                'filename' => $uniqueName,
                'original_name' => $fileName,
                'mime_type' => $mimeType,
                'size' => strlen($fileContent),
                'path' => $relativePath,
            ]);
            
            // Reopen if resolved/closed
            if (in_array($ticket['status'], ['resolved', 'closed'])) {
                $this->db->update('tickets', ['status' => 'open'], 'id = ?', [$ticketId]);
            }
            
            // Log activity
            $activityModel = new ActivityLog($this->db);
            $activityModel->log(
                $this->companyId,
                $ticketId,
                $user['id'],
                'telegram_reply',
                'Customer sent file via Telegram: ' . $fileName
            );
            
            // Notify agents
            $preview = $caption ? substr($caption, 0, 100) . '...' : '[Attachment: ' . $fileName . ']';
            $this->notifyAgents(
                $ticketId,
                'telegram_reply',
                "New file on #{$ticket['ticket_number']}",
                "{$user['name']}: {$preview}",
                [
                    'source' => 'telegram',
                    'requester' => $user['name'] ?? 'Customer',
                    'has_attachment' => true,
                ]
            );

            if (!empty($ticket['assigned_to'])) {
                $assignedUser = $this->db->selectOne(
                    "SELECT name FROM users WHERE id = ? AND company_id = ?",
                    [$ticket['assigned_to'], $this->companyId]
                );
                $assignedName = $assignedUser['name'] ?? 'Agent';
                $appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
                $link = $appUrl ? $appUrl . "/tickets/{$ticketId}" : '';
                $subject = htmlspecialchars($ticket['subject'] ?? '', ENT_QUOTES | ENT_HTML5);
                $requesterName = htmlspecialchars($ticket['requester_name'] ?? '', ENT_QUOTES | ENT_HTML5);
                $requesterEmail = htmlspecialchars($ticket['requester_email'] ?? '', ENT_QUOTES | ENT_HTML5);
                $assignedLabel = htmlspecialchars($assignedName, ENT_QUOTES | ENT_HTML5);
                $status = ucfirst(str_replace('_', ' ', $ticket['status'] ?? 'open'));
                $priority = ucfirst($ticket['priority'] ?? 'medium');
                $previewSafe = htmlspecialchars($preview, ENT_QUOTES | ENT_HTML5);
                $text = "ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã…Â½ <b>New Customer Attachment</b>\n" .
                    "ÃƒÂ°Ã…Â¸Ã¢â‚¬Â Ã¢â‚¬Â <b>Ticket:</b> #{$ticket['ticket_number']}\n" .
                    "ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã‚Â <b>Subject:</b> {$subject}\n" .
                    "ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã…â€™ <b>Status:</b> {$status}\n" .
                    "ÃƒÂ°Ã…Â¸Ã…Â¡Ã‚Â¦ <b>Priority:</b> {$priority}\n" .
                    "ÃƒÂ°Ã…Â¸Ã¢â‚¬ËœÃ‚Â¤ <b>Requester:</b> {$requesterName} ({$requesterEmail})\n" .
                    "ÃƒÂ°Ã…Â¸Ã…Â½Ã‚Â¯ <b>Assigned to:</b> {$assignedLabel}\n" .
                    "ÃƒÂ¢Ã…â€œÃ¢â‚¬Â°ÃƒÂ¯Ã‚Â¸Ã‚Â <b>Message:</b> {$previewSafe}";
                if ($link) {
                    $text .= "\nLink: {$link}";
                }
                $notifier = new TelegramAgentNotifier($this->db, $this->companyId);
                $notifier->notifyAssignedAgent($ticket, $text);
            }
            
            $this->clearUserState($chatId);
            $this->bot->sendMessage($chatId,
                "ÃƒÂ¢Ã…â€œÃ¢â‚¬Â¦ File sent to ticket #{$ticket['ticket_number']} successfully!");
            
        } catch (\Exception $e) {
            error_log("[TelegramWebhook] ERROR in handleFileReply: " . $e->getMessage());
            $this->bot->sendMessage($chatId, "Error processing the file.");
        }
    }

    private function guessExtension(string $mimeType, string $fallback): string
    {
        if (stripos($mimeType, 'ogg') !== false) {
            return '.ogg';
        }

        if (stripos($mimeType, 'mpeg') !== false || stripos($mimeType, 'mp3') !== false) {
            return '.mp3';
        }

        if (stripos($mimeType, 'mp4') !== false || stripos($mimeType, 'm4a') !== false) {
            return '.m4a';
        }

        return $fallback;
    }

    private function defaultAttachmentLabel(string $mimeType, string $fileName): string
    {
        if (stripos($mimeType, 'image/') === 0) {
            return '[Image attachment: ' . $fileName . ']';
        }

        if (stripos($mimeType, 'audio/') === 0) {
            return '[Audio attachment: ' . $fileName . ']';
        }

        return '[Attachment: ' . $fileName . ']';
    }

    /**
     * Notify agents/admins in the dashboard
     */
    private function notifyAgents(int $ticketId, string $type, string $title, string $message, array $data = []): void
    {
        $ticket = $this->db->selectOne(
            "SELECT id, ticket_number, subject, assigned_to FROM tickets WHERE id = ?",
            [$ticketId]
        );

        if (!$ticket) {
            return;
        }

        // Prefer assigned agent if present
        $agents = [];
        if (!empty($ticket['assigned_to'])) {
            $agents = $this->db->select(
                "SELECT id FROM users WHERE id = ? AND is_active = 1",
                [$ticket['assigned_to']]
            );
        }

        if (empty($agents)) {
            $agents = $this->db->select(
                "SELECT id FROM users WHERE company_id = ? AND role IN ('admin','agent') AND is_active = 1",
                [$this->companyId]
            );
        }

        foreach ($agents as $agent) {
            $this->db->insert('notifications', [
                'user_id' => $agent['id'],
                'ticket_id' => $ticketId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => json_encode($data),
            ]);
        }
    }

    // State management helpers
    private function getUserState(int $chatId): array
    {
        $state = $this->db->selectOne(
            "SELECT * FROM telegram_user_states WHERE chat_id = ? AND company_id = ?",
            [$chatId, $this->companyId]
        );

        if (!$state) {
            return ['state' => self::STATE_NONE, 'data' => []];
        }

        return [
            'state' => $state['state'],
            'data' => json_decode($state['data'], true) ?? [],
        ];
    }

    private function setUserState(int $chatId, string $state, array $data = []): void
    {
        $existing = $this->db->selectOne(
            "SELECT id FROM telegram_user_states WHERE chat_id = ? AND company_id = ?",
            [$chatId, $this->companyId]
        );

        if ($existing) {
            $this->db->update('telegram_user_states', [
                'state' => $state,
                'data' => json_encode($data),
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$existing['id']]);
        } else {
            $this->db->insert('telegram_user_states', [
                'company_id' => $this->companyId,
                'chat_id' => $chatId,
                'state' => $state,
                'data' => json_encode($data),
            ]);
        }
    }

    private function clearUserState(int $chatId): void
    {
        $this->db->delete('telegram_user_states',
            'chat_id = ? AND company_id = ?',
            [$chatId, $this->companyId]
        );
    }

    private function handleStartBindLink(int $chatId, ?string $startParam, array $telegramUserInfo): array
    {
        if ($startParam === null || $startParam === '' || strpos($startParam, 'bind_') !== 0) {
            return ['handled' => false, 'success' => false, 'user' => null];
        }

        if (!preg_match('/^bind_(\d+)_(\d{10})_([A-Za-z0-9_-]{8,64})$/', $startParam, $matches)) {
            $this->bot->sendMessage($chatId, 'Invalid Telegram link. Please regenerate the link from Settings > Users.');
            return ['handled' => true, 'success' => false, 'user' => null];
        }

        $userId = (int) ($matches[1] ?? 0);
        $expiresAt = (int) ($matches[2] ?? 0);
        $signature = (string) ($matches[3] ?? '');

        if ($userId <= 0 || $expiresAt <= 0 || $signature === '') {
            $this->bot->sendMessage($chatId, 'Invalid Telegram link payload.');
            return ['handled' => true, 'success' => false, 'user' => null];
        }

        if ($expiresAt < time()) {
            $this->bot->sendMessage($chatId, 'This Telegram link has expired. Please generate a new one from Settings > Users.');
            return ['handled' => true, 'success' => false, 'user' => null];
        }

        if (!$this->isValidBindStartSignature($userId, $expiresAt, $signature)) {
            $this->bot->sendMessage($chatId, 'Invalid Telegram link signature. Please generate a new one.');
            return ['handled' => true, 'success' => false, 'user' => null];
        }

        $staffUser = $this->db->selectOne(
            "SELECT id, name, role, is_active
             FROM users
             WHERE id = ? AND company_id = ?
             LIMIT 1",
            [$userId, $this->companyId]
        );

        if (!$staffUser || (int) ($staffUser['is_active'] ?? 0) !== 1 || ($staffUser['role'] ?? '') === 'customer') {
            $this->bot->sendMessage($chatId, 'This account cannot be linked via staff Telegram link.');
            return ['handled' => true, 'success' => false, 'user' => null];
        }

        $existingLinked = $this->db->selectOne(
            "SELECT id, name
             FROM users
             WHERE telegram_chat_id = ?
               AND company_id = ?
               AND id != ?
             LIMIT 1",
            [$chatId, $this->companyId, $userId]
        );

        if ($existingLinked) {
            $existingName = htmlspecialchars((string) ($existingLinked['name'] ?? 'another account'), ENT_QUOTES | ENT_HTML5);
            $this->bot->sendMessage(
                $chatId,
                "This Telegram account is already linked to <b>{$existingName}</b>. Contact admin if you need to reassign it."
            );
            return ['handled' => true, 'success' => false, 'user' => null];
        }

        $this->db->update('users', [
            'telegram_chat_id' => $chatId,
            'telegram_username' => $telegramUserInfo['username'] ?? null,
        ], 'id = ? AND company_id = ?', [$userId, $this->companyId]);

        $linkedUser = $this->db->selectOne(
            "SELECT * FROM users WHERE id = ? AND company_id = ? AND is_active = 1",
            [$userId, $this->companyId]
        );

        if (!$linkedUser) {
            $this->bot->sendMessage($chatId, 'Failed to link account. Please try again.');
            return ['handled' => true, 'success' => false, 'user' => null];
        }

        $safeName = htmlspecialchars((string) ($linkedUser['name'] ?? 'User'), ENT_QUOTES | ENT_HTML5);
        $keyboard = [
            [['text' => 'Open Mini App', 'web_app' => ['url' => $this->getMiniAppUrl()]]]
        ];
        $this->bot->sendMessageWithKeyboard(
            $chatId,
            "Telegram linked to staff account <b>{$safeName}</b>.\nYou will now receive assignment notifications in this chat.",
            $keyboard
        );

        return ['handled' => true, 'success' => true, 'user' => $linkedUser];
    }

    private function isValidBindStartSignature(int $userId, int $expiresAt, string $signature): bool
    {
        $payload = $userId . ':' . $expiresAt;
        $secrets = array_values(array_unique(array_filter([
            (string) ($this->config['webhook_secret'] ?? ''),
            (string) ($this->config['bot_token'] ?? ''),
        ])));

        foreach ($secrets as $secret) {
            if ($secret === '') {
                continue;
            }

            $rawSignature = hash_hmac('sha256', $payload, $secret, true);
            $expected = rtrim(strtr(base64_encode(substr($rawSignature, 0, 16)), '+/', '-_'), '=');
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    private function getUserByChatId(int $chatId): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM users WHERE telegram_chat_id = ? AND company_id = ? AND is_active = 1",
            [$chatId, $this->companyId]
        );
    }
}

