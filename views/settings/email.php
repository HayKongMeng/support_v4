<?php
$pageTitle = __('v_email_settings');
ob_start();
?>

<div class="max-w-3xl">
    <div class="bg-white rounded-xl shadow-sm">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800"><?= __('v_email_configuration') ?></h2>
            <p class="text-sm text-gray-500 mt-1"><?= __('v_configure_imap_smtp_for_automatic_ticket_creation_from_email') ?></p>
        </div>

        <form action="<?= $app->url('settings/email') ?>" method="POST" class="p-6 space-y-6">
            <!-- Enable/Disable -->
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                <div>
                    <h3 class="font-medium text-gray-900"><?= __('v_email_integration') ?></h3>
                    <p class="text-sm text-gray-500"><?= __('v_automatically_create_tickets_from_incoming_emails') ?></p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="is_active" value="1"
                           <?= ($emailConfig['is_active'] ?? false) ? 'checked' : '' ?>
                           class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                </label>
            </div>

            <hr>

            <!-- IMAP Settings -->
            <h3 class="text-lg font-medium text-gray-800"><?= __('v_incoming_mail_imap') ?></h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="imap_host" class="block text-sm font-medium text-gray-700 mb-2"><?= __('v_imap_host') ?></label>
                    <input type="text" id="imap_host" name="imap_host"
                           value="<?= htmlspecialchars($emailConfig['imap_host'] ?? '') ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                           placeholder="imap.gmail.com">
                </div>
                <div>
                    <label for="imap_port" class="block text-sm font-medium text-gray-700 mb-2"><?= __('v_imap_port') ?></label>
                    <input type="number" id="imap_port" name="imap_port"
                           value="<?= htmlspecialchars($emailConfig['imap_port'] ?? '993') ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="imap_username" class="block text-sm font-medium text-gray-700 mb-2"><?= __('v_imap_username') ?></label>
                    <input type="text" id="imap_username" name="imap_username"
                           value="<?= htmlspecialchars($emailConfig['imap_username'] ?? '') ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                           placeholder="support@yourcompany.com">
                </div>
                <div>
                    <label for="imap_password" class="block text-sm font-medium text-gray-700 mb-2"><?= __('v_imap_password') ?></label>
                    <input type="password" id="imap_password" name="imap_password"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                           placeholder="<?= $emailConfig ? '••••••••' : '' ?>">
                    <?php if ($emailConfig): ?>
                    <p class="text-xs text-gray-500 mt-1"><?= __('v_leave_blank_to_keep_current_password') ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="imap_encryption" class="block text-sm font-medium text-gray-700 mb-2"><?= __('v_encryption') ?></label>
                    <select id="imap_encryption" name="imap_encryption"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <option value="ssl" <?= ($emailConfig['imap_encryption'] ?? 'ssl') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                        <option value="tls" <?= ($emailConfig['imap_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                        <option value="none" <?= ($emailConfig['imap_encryption'] ?? '') === 'none' ? 'selected' : '' ?>><?= __('v_none') ?></option>
                    </select>
                </div>
            </div>

            <hr>

            <!-- SMTP Settings -->
            <h3 class="text-lg font-medium text-gray-800"><?= __('v_outgoing_mail_smtp') ?></h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="smtp_host" class="block text-sm font-medium text-gray-700 mb-2"><?= __('v_smtp_host') ?></label>
                    <input type="text" id="smtp_host" name="smtp_host"
                           value="<?= htmlspecialchars($emailConfig['smtp_host'] ?? '') ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                           placeholder="smtp.gmail.com">
                </div>
                <div>
                    <label for="smtp_port" class="block text-sm font-medium text-gray-700 mb-2"><?= __('v_smtp_port') ?></label>
                    <input type="number" id="smtp_port" name="smtp_port"
                           value="<?= htmlspecialchars($emailConfig['smtp_port'] ?? '587') ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="smtp_username" class="block text-sm font-medium text-gray-700 mb-2"><?= __('v_smtp_username') ?></label>
                    <input type="text" id="smtp_username" name="smtp_username"
                           value="<?= htmlspecialchars($emailConfig['smtp_username'] ?? '') ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="smtp_password" class="block text-sm font-medium text-gray-700 mb-2"><?= __('v_smtp_password') ?></label>
                    <input type="password" id="smtp_password" name="smtp_password"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                           placeholder="<?= $emailConfig ? '••••••••' : '' ?>">
                </div>
                <div>
                    <label for="smtp_encryption" class="block text-sm font-medium text-gray-700 mb-2"><?= __('v_encryption') ?></label>
                    <select id="smtp_encryption" name="smtp_encryption"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <option value="tls" <?= ($emailConfig['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                        <option value="ssl" <?= ($emailConfig['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                        <option value="none" <?= ($emailConfig['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>><?= __('v_none') ?></option>
                    </select>
                </div>
            </div>

            <hr>

            <!-- From Address -->
            <h3 class="text-lg font-medium text-gray-800"><?= __('v_sender_information') ?></h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="from_email" class="block text-sm font-medium text-gray-700 mb-2"><?= __('v_from_email') ?></label>
                    <input type="email" id="from_email" name="from_email"
                           value="<?= htmlspecialchars($emailConfig['from_email'] ?? '') ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                           placeholder="support@yourcompany.com">
                </div>
                <div>
                    <label for="from_name" class="block text-sm font-medium text-gray-700 mb-2"><?= __('v_from_name') ?></label>
                    <input type="text" id="from_name" name="from_name"
                           value="<?= htmlspecialchars($emailConfig['from_name'] ?? '') ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                           placeholder="<?= __('v_support_team') ?>">
                </div>
            </div>

            <div class="flex justify-end pt-6 border-t border-gray-200">
                <button type="submit"
                        class="px-6 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700">
                    <?= __('v_save_email_settings') ?>
                </button>
            </div>
        </form>
    </div>

    <!-- CRON Info -->
    <div class="mt-6 p-4 bg-blue-50 rounded-lg">
        <h4 class="font-medium text-blue-900 mb-2"><?= __('v_cron_job_setup') ?></h4>
        <p class="text-sm text-blue-700 mb-2"><?= __('v_to_process_incoming_emails_automatically_set_up_a_cron_job') ?></p>
        <code class="block p-2 bg-blue-100 rounded text-sm text-blue-800">
            */5 * * * * php <?= $app->basePath('cron/email_processor.php') ?>
        </code>
        <p class="text-xs text-blue-600 mt-2"><?= __('v_this_runs_every_5_minutes_to_check_for_new_emails') ?></p>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/app.php';
