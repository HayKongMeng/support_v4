<?php
$pageTitle = 'Telegram Settings';
ob_start();
?>

<div class="max-w-3xl">
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Telegram Bot Configuration</h2>
            <p class="text-sm text-gray-500 mt-1">Connect a Telegram bot to receive and manage support tickets</p>
        </div>

        <form action="<?= $app->url('settings/telegram') ?>" method="POST" class="p-6 space-y-6">
            <!-- Enable/Disable -->
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                <div>
                    <h3 class="font-medium text-gray-900">Telegram Integration</h3>
                    <p class="text-sm text-gray-500">Allow customers to create tickets via Telegram</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="is_active" value="1"
                           <?= ($telegramConfig['is_active'] ?? false) ? 'checked' : '' ?>
                           class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                </label>
            </div>

            <!-- Bot Token -->
            <div>
                <label for="bot_token" class="block text-sm font-medium text-gray-700 mb-2">Bot Token</label>
                <input type="text" id="bot_token" name="bot_token"
                       value="<?= htmlspecialchars($telegramConfig['bot_token'] ?? '') ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                       placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz">
                <p class="text-xs text-gray-500 mt-1">
                    Get your bot token from <a href="https://t.me/BotFather" target="_blank" class="text-indigo-600 hover:underline">@BotFather</a>
                </p>
            </div>

            <!-- Bot Username -->
            <div>
                <label for="bot_username" class="block text-sm font-medium text-gray-700 mb-2">Bot Username</label>
                <div class="flex">
                    <span class="inline-flex items-center px-3 text-gray-500 bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg">@</span>
                    <input type="text" id="bot_username" name="bot_username"
                           value="<?= htmlspecialchars($telegramConfig['bot_username'] ?? '') ?>"
                           class="flex-1 px-4 py-2 border border-gray-300 rounded-r-lg focus:ring-2 focus:ring-indigo-500"
                           placeholder="YourSupportBot">
                </div>
            </div>

            <!-- Welcome Message -->
            <div>
                <label for="welcome_message" class="block text-sm font-medium text-gray-700 mb-2">Welcome Message</label>
                <textarea id="welcome_message" name="welcome_message" rows="3"
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                          placeholder="Welcome to our Support Bot! How can we help you today?"><?= htmlspecialchars($telegramConfig['welcome_message'] ?? '') ?></textarea>
                <p class="text-xs text-gray-500 mt-1">This message is shown when users start the bot</p>
            </div>

            <div class="flex justify-end pt-6 border-t border-gray-200">
                <button type="submit"
                        class="px-6 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700">
                    Save Telegram Settings
                </button>
            </div>
        </form>
    </div>

    <!-- Webhook Setup -->
    <div class="mt-6 bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Webhook Configuration</h3>

        <div class="p-4 bg-gray-50 rounded-lg mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Webhook URL</label>
            <div class="flex">
                <input type="text" readonly value="<?= htmlspecialchars($webhookUrl) ?>"
                       class="flex-1 px-4 py-2 bg-white border border-gray-300 rounded-l-lg text-sm">
                <button type="button" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($webhookUrl) ?>')"
                        class="px-4 py-2 bg-gray-200 border border-l-0 border-gray-300 rounded-r-lg hover:bg-gray-300">
                    <i class="fas fa-copy"></i>
                </button>
            </div>
        </div>

        <div class="space-y-2 text-sm text-gray-600">
            <p><strong>To set up the webhook:</strong></p>
            <ol class="list-decimal list-inside space-y-1 ml-4">
                <li>Save your bot settings above</li>
                <li>Open this URL in your browser (replace YOUR_BOT_TOKEN):
                    <code class="block mt-1 p-2 bg-gray-100 rounded text-xs overflow-x-auto">
                        https://api.telegram.org/botYOUR_BOT_TOKEN/setWebhook?url=<?= urlencode($webhookUrl) ?>
                    </code>
                </li>
                <li>You should see a success message</li>
            </ol>
        </div>
    </div>

    <!-- Bot Commands -->
    <div class="mt-6 bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Available Bot Commands</h3>
        <div class="space-y-3 text-sm">
            <div class="flex">
                <code class="w-32 text-indigo-600">/start</code>
                <span class="text-gray-600">Start the bot and see welcome message</span>
            </div>
            <div class="flex">
                <code class="w-32 text-indigo-600">/link</code>
                <span class="text-gray-600">Link email account to Telegram</span>
            </div>
            <div class="flex">
                <code class="w-32 text-indigo-600">/newticket</code>
                <span class="text-gray-600">Create a new support ticket</span>
            </div>
            <div class="flex">
                <code class="w-32 text-indigo-600">/mytickets</code>
                <span class="text-gray-600">View your open tickets</span>
            </div>
            <div class="flex">
                <code class="w-32 text-indigo-600">/status [#]</code>
                <span class="text-gray-600">Check ticket status</span>
            </div>
            <div class="flex">
                <code class="w-32 text-indigo-600">/help</code>
                <span class="text-gray-600">Show help message</span>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
