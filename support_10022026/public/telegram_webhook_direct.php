<?php
/**
 * Direct Telegram Webhook Handler v3 - With Group Support
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

define('BASE_PATH', dirname(__DIR__));

function webhookLog($msg) {
    $dir = BASE_PATH . '/storage/logs';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    @file_put_contents($dir . '/telegram_direct.log', "[" . date('Y-m-d H:i:s') . "] {$msg}\n", FILE_APPEND);
}

function tgApi($token, $method, $params = []) {
    $ch = curl_init("https://api.telegram.org/bot{$token}/{$method}");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $params, CURLOPT_TIMEOUT => 30]);
    $res = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($res, true);
    if (!($data['ok'] ?? false)) webhookLog("TG API Error ({$method}): " . ($data['description'] ?? $res));
    return $data;
}

function sendMsg($token, $chatId, $text, $kb = null, $replyToMsgId = null) {
    $p = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($kb) $p['reply_markup'] = json_encode(['inline_keyboard' => $kb]);
    if ($replyToMsgId) $p['reply_to_message_id'] = $replyToMsgId;
    return tgApi($token, 'sendMessage', $p);
}

function downloadFile($token, $fileId, $savePath) {
    $r = tgApi($token, 'getFile', ['file_id' => $fileId]);
    if (!$r || !isset($r['result']['file_path'])) return null;
    $url = "https://api.telegram.org/file/bot{$token}/" . $r['result']['file_path'];
    $content = @file_get_contents($url);
    if (!$content) return null;
    $dir = dirname($savePath);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return file_put_contents($savePath, $content) ? $savePath : null;
}

webhookLog("=== START ===");

$companyId = $_GET['company'] ?? 1;
$input = file_get_contents('php://input');

if (!$input || $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    die(json_encode(['ok' => true, 'version' => 'v3-groups', 'company' => $companyId]));
}

try {
    require BASE_PATH . '/vendor/autoload.php';
    $app = \App\Core\App::getInstance();
    $db = $app->db();

    $update = json_decode($input, true);
    if (!$update) { webhookLog("Bad JSON"); exit; }

    $msg = $update['message'] ?? null;
    $cb = $update['callback_query'] ?? null;
    $myChatMember = $update['my_chat_member'] ?? null; // Bot added/removed from group

    $config = $db->selectOne("SELECT * FROM telegram_configs WHERE company_id = ? AND is_active = 1", [$companyId]);
    if (!$config) { webhookLog("No config"); exit; }
    $token = $config['bot_token'];

    // === BOT ADDED/REMOVED FROM GROUP ===
    if ($myChatMember) {
        $chat = $myChatMember['chat'];
        $chatId = $chat['id'];
        $chatType = $chat['type']; // 'group', 'supergroup', 'channel'
        $chatTitle = $chat['title'] ?? 'Unknown Group';
        $newStatus = $myChatMember['new_chat_member']['status'] ?? '';

        webhookLog("Bot status changed in {$chatType} '{$chatTitle}' ({$chatId}): {$newStatus}");

        if (in_array($chatType, ['group', 'supergroup'])) {
            if (in_array($newStatus, ['member', 'administrator'])) {
                // Bot added to group - register it
                $existing = $db->selectOne("SELECT id FROM telegram_groups WHERE chat_id = ? AND company_id = ?", [$chatId, $companyId]);
                if ($existing) {
                    $db->update('telegram_groups', ['title' => $chatTitle, 'type' => $chatType, 'is_active' => 1], 'id = ?', [$existing['id']]);
                } else {
                    $db->insert('telegram_groups', [
                        'company_id' => $companyId,
                        'chat_id' => $chatId,
                        'title' => $chatTitle,
                        'type' => $chatType,
                        'is_active' => 1
                    ]);
                }
                sendMsg($token, $chatId, "👋 <b>Hello!</b>\n\nI'm now active in this group.\n\n<b>Commands:</b>\n/newticket - Create support ticket\n/mytickets - View group tickets\n/help - Show commands");
                webhookLog("Bot registered in group: {$chatTitle}");
            } elseif (in_array($newStatus, ['left', 'kicked'])) {
                // Bot removed from group
                $db->update('telegram_groups', ['is_active' => 0], 'chat_id = ? AND company_id = ?', [$chatId, $companyId]);
                webhookLog("Bot removed from group: {$chatTitle}");
            }
        }

        header('Content-Type: application/json');
        die(json_encode(['ok' => true]));
    }

    // === CALLBACK QUERY ===
    if ($cb) {
        $chatId = $cb['message']['chat']['id'];
        $chatType = $cb['message']['chat']['type'] ?? 'private';
        $isGroup = in_array($chatType, ['group', 'supergroup']);
        $data = $cb['data'];
        $msgId = $cb['message']['message_id'] ?? null;
        webhookLog("CB: {$chatId} ({$chatType}) -> {$data}");

        @tgApi($token, 'answerCallbackQuery', ['callback_query_id' => $cb['id']]);

        list($action, $param, $param2) = array_pad(explode(':', $data), 3, null);
        webhookLog("Action: {$action}, Param: {$param}, Param2: {$param2}");

        // For groups, we look up user by from_id, not chat_id
        $fromId = $cb['from']['id'];
        $user = $db->selectOne("SELECT * FROM users WHERE telegram_chat_id = ? AND company_id = ?", [$fromId, $companyId]);

        if ($action === 'view' && $param) {
            $ticket = $db->selectOne("SELECT * FROM tickets WHERE id = ?", [$param]);
            if ($ticket) {
                sendMsg($token, $chatId,
                    "<b>Ticket #{$ticket['ticket_number']}</b>\n\n<b>Subject:</b> {$ticket['subject']}\n<b>Status:</b> {$ticket['status']}\n<b>Description:</b>\n" . substr($ticket['description'], 0, 300),
                    [[['text' => '💬 Reply', 'callback_data' => "reply:{$param}"]]]
                );
            } else {
                sendMsg($token, $chatId, "Ticket not found.");
            }
        } elseif ($action === 'reply' && $param) {
            webhookLog("Setting reply state for ticket_id: {$param}");
            $stateKey = $isGroup ? $fromId : $chatId; // Use user ID for groups
            $existing = $db->selectOne("SELECT id FROM telegram_user_states WHERE chat_id = ? AND company_id = ?", [$stateKey, $companyId]);
            $stateData = ['ticket_id' => $param, 'group_chat_id' => $isGroup ? $chatId : null];
            if ($existing) {
                $db->update('telegram_user_states', ['state' => 'reply', 'data' => json_encode($stateData)], 'id = ?', [$existing['id']]);
            } else {
                $db->insert('telegram_user_states', ['company_id' => $companyId, 'chat_id' => $stateKey, 'state' => 'reply', 'data' => json_encode($stateData)]);
            }
            $ticket = $db->selectOne("SELECT ticket_number FROM tickets WHERE id = ?", [$param]);
            $replyMsg = "💬 Type your reply for ticket #{$ticket['ticket_number']}:\n\n(Send text, photo, or file. /cancel to cancel)";
            if ($isGroup) {
                $replyMsg = "💬 @{$cb['from']['username']}, type your reply for #{$ticket['ticket_number']}:";
            }
            sendMsg($token, $chatId, $replyMsg);
        } elseif ($action === 'survey' && $param) {
            $rating = (int) $param;
            $ticketId = $param2 ?? null;
            webhookLog("Survey rating: {$rating} for ticket: {$ticketId}");

            if ($ticketId && $rating >= 1 && $rating <= 5) {
                $db->query("UPDATE ticket_surveys SET rating = ?, rated_at = NOW() WHERE ticket_id = ?", [$rating, $ticketId]);

                $stateKey = $isGroup ? $fromId : $chatId;
                $existing = $db->selectOne("SELECT id FROM telegram_user_states WHERE chat_id = ? AND company_id = ?", [$stateKey, $companyId]);
                $stateData = ['ticket_id' => $ticketId, 'rating' => $rating, 'group_chat_id' => $isGroup ? $chatId : null];
                if ($existing) {
                    $db->update('telegram_user_states', ['state' => 'survey_comment', 'data' => json_encode($stateData)], 'id = ?', [$existing['id']]);
                } else {
                    $db->insert('telegram_user_states', ['company_id' => $companyId, 'chat_id' => $stateKey, 'state' => 'survey_comment', 'data' => json_encode($stateData)]);
                }

                $stars = str_repeat('⭐', $rating);
                sendMsg($token, $chatId,
                    "Thank you for rating us {$stars}!\n\nWould you like to add a comment? (optional)\n\nType your feedback or click Skip.",
                    [[['text' => '⏭️ Skip', 'callback_data' => "survey_skip:{$ticketId}"]]]
                );
            }
        } elseif ($action === 'survey_skip') {
            $ticketId = $param;
            webhookLog("Survey skip comment for ticket: {$ticketId}");
            $stateKey = $isGroup ? $fromId : $chatId;
            $db->delete('telegram_user_states', 'chat_id = ? AND company_id = ?', [$stateKey, $companyId]);
            sendMsg($token, $chatId, "✅ Thank you for your feedback! We appreciate your time.");
        }

        webhookLog("=== END CB ===");
        header('Content-Type: application/json');
        die(json_encode(['ok' => true]));
    }

    // === MESSAGE ===
    if (!$msg) { webhookLog("No msg"); exit; }

    $chatId = $msg['chat']['id'];
    $chatType = $msg['chat']['type'] ?? 'private';
    $chatTitle = $msg['chat']['title'] ?? null;
    $isGroup = in_array($chatType, ['group', 'supergroup']);
    $fromId = $msg['from']['id'];
    $text = $msg['text'] ?? '';
    $caption = $msg['caption'] ?? '';
    $photo = $msg['photo'] ?? null;
    $doc = $msg['document'] ?? null;
    $voice = $msg['voice'] ?? null;
    $videoNote = $msg['video_note'] ?? null;
    $audio = $msg['audio'] ?? null;
    $firstName = $msg['from']['first_name'] ?? 'User';
    $username = $msg['from']['username'] ?? null;
    $replyToMsgId = $msg['message_id'];

    // For groups, handle new members (bot added via forward)
    $newMembers = $msg['new_chat_members'] ?? null;
    if ($newMembers && $isGroup) {
        foreach ($newMembers as $member) {
            if ($member['is_bot'] ?? false) {
                // Bot was added to group
                $existing = $db->selectOne("SELECT id FROM telegram_groups WHERE chat_id = ? AND company_id = ?", [$chatId, $companyId]);
                if (!$existing) {
                    $db->insert('telegram_groups', [
                        'company_id' => $companyId,
                        'chat_id' => $chatId,
                        'title' => $chatTitle ?? 'Group',
                        'type' => $chatType,
                        'is_active' => 1
                    ]);
                    sendMsg($token, $chatId, "👋 <b>Hello!</b>\n\nI'm now active in this group.\n\n<b>Commands:</b>\n/newticket - Create support ticket\n/mytickets - View group tickets\n/help - Show commands");
                    webhookLog("Bot added to group via new_chat_members: {$chatTitle}");
                }
            }
        }
        header('Content-Type: application/json');
        die(json_encode(['ok' => true]));
    }

    $msgType = $text ?: ($photo ? '[photo]' : ($doc ? '[doc]' : ($voice ? '[voice]' : ($videoNote ? '[video_note]' : ($audio ? '[audio]' : '[empty]')))));
    webhookLog("MSG: {$chatId} ({$chatType}) -> " . $msgType);

    // For groups, look up user by from_id (individual user), not chat_id (group)
    $userLookupId = $isGroup ? $fromId : $chatId;
    $user = $db->selectOne("SELECT * FROM users WHERE telegram_chat_id = ? AND company_id = ?", [$userLookupId, $companyId]);

    // For groups, state is per-user not per-chat
    $stateKey = $isGroup ? $fromId : $chatId;
    $state = $db->selectOne("SELECT * FROM telegram_user_states WHERE chat_id = ? AND company_id = ?", [$stateKey, $companyId]);
    $st = $state['state'] ?? 'none';
    $stData = $state ? json_decode($state['data'] ?? '{}', true) : [];

    webhookLog("User: " . ($user['email'] ?? 'none') . ", State: {$st}, IsGroup: " . ($isGroup ? 'yes' : 'no'));

    $setState = function($s, $d = []) use ($db, $stateKey, $companyId, $state, $isGroup, $chatId) {
        if ($isGroup) $d['group_chat_id'] = $chatId;
        if ($state) $db->update('telegram_user_states', ['state' => $s, 'data' => json_encode($d)], 'id = ?', [$state['id']]);
        else $db->insert('telegram_user_states', ['company_id' => $companyId, 'chat_id' => $stateKey, 'state' => $s, 'data' => json_encode($d)]);
    };
    $clearState = function() use ($db, $stateKey, $companyId) {
        $db->delete('telegram_user_states', 'chat_id = ? AND company_id = ?', [$stateKey, $companyId]);
    };
    $saveFile = function($fileId, $name, $mime, $ticketId, $msgId = null) use ($db, $token, $user) {
        $fn = uniqid() . '_' . preg_replace('/[^a-z0-9._-]/i', '', $name);
        $dir = BASE_PATH . "/public/uploads/tickets/{$ticketId}";
        if (downloadFile($token, $fileId, "{$dir}/{$fn}")) {
            $db->insert('attachments', [
                'ticket_id' => $ticketId, 'message_id' => $msgId, 'user_id' => $user['id'] ?? null,
                'filename' => $fn, 'original_name' => $name, 'mime_type' => $mime,
                'size' => filesize("{$dir}/{$fn}"), 'path' => "tickets/{$ticketId}/{$fn}"
            ]);
            return true;
        }
        return false;
    };

    $notifyAgents = function($ticket, $message, $type = 'telegram_reply') use ($db, $companyId, $user) {
        $agents = [];
        if ($ticket['assigned_to']) {
            $agents = $db->select("SELECT id FROM users WHERE id = ? AND is_active = 1", [$ticket['assigned_to']]);
        }
        if (empty($agents)) {
            $agents = $db->select("SELECT id FROM users WHERE company_id = ? AND role IN ('admin', 'agent') AND is_active = 1", [$companyId]);
        }

        $preview = strlen($message) > 100 ? substr($message, 0, 100) . '...' : $message;
        $requesterName = $user['name'] ?? 'Customer';
        foreach ($agents as $agent) {
            $db->insert('notifications', [
                'user_id' => $agent['id'],
                'ticket_id' => $ticket['id'],
                'type' => $type,
                'title' => "New reply on #{$ticket['ticket_number']}",
                'message' => "{$requesterName}: {$preview}",
                'data' => json_encode(['source' => 'telegram', 'requester' => $requesterName])
            ]);
        }
        webhookLog("Notified " . count($agents) . " agents about reply");
    };

    // === REPLY STATE ===
    if ($st === 'reply') {
        $ticketId = $stData['ticket_id'] ?? null;
        $targetChatId = $stData['group_chat_id'] ?? $chatId;
        webhookLog("Reply state for ticket: {$ticketId}");

        if (!$user) {
            $clearState();
            sendMsg($token, $chatId, "Please /link your email first.", null, $isGroup ? $replyToMsgId : null);
        } elseif (!$ticketId) {
            $clearState();
            sendMsg($token, $chatId, "Error. Use /mytickets to try again.");
        } else {
            $ticket = $db->selectOne("SELECT * FROM tickets WHERE id = ?", [$ticketId]);
            if (!$ticket) {
                $clearState();
                sendMsg($token, $chatId, "Ticket not found.");
            } else {
                $replyText = $text ?: $caption ?: ($photo ? '[Photo]' : ($doc ? '[File]' : ($voice ? '[Voice Message]' : ($videoNote ? '[Video Note]' : ($audio ? '[Audio]' : '')))));
                if (!$replyText && !$photo && !$doc && !$voice && !$videoNote && !$audio) {
                    sendMsg($token, $chatId, "Send a message, photo, voice, or file. /cancel to cancel.");
                } else {
                    $msgId = $db->insert('ticket_messages', [
                        'ticket_id' => $ticketId, 'user_id' => $user['id'],
                        'message' => $replyText ?: 'Attachment', 'is_internal' => 0, 'source' => 'telegram'
                    ]);
                    if ($photo) { $p = end($photo); $saveFile($p['file_id'], 'photo_' . time() . '.jpg', 'image/jpeg', $ticketId, $msgId); }
                    elseif ($doc) { $saveFile($doc['file_id'], $doc['file_name'] ?? 'file', $doc['mime_type'] ?? 'application/octet-stream', $ticketId, $msgId); }
                    elseif ($voice) { $saveFile($voice['file_id'], 'voice_' . time() . '.ogg', $voice['mime_type'] ?? 'audio/ogg', $ticketId, $msgId); }
                    elseif ($videoNote) { $saveFile($videoNote['file_id'], 'video_note_' . time() . '.mp4', 'video/mp4', $ticketId, $msgId); }
                    elseif ($audio) { $saveFile($audio['file_id'], $audio['file_name'] ?? ('audio_' . time() . '.mp3'), $audio['mime_type'] ?? 'audio/mpeg', $ticketId, $msgId); }

                    if (in_array($ticket['status'], ['resolved', 'closed'])) {
                        $db->update('tickets', ['status' => 'open'], 'id = ?', [$ticketId]);
                    }
                    $db->update('tickets', ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$ticketId]);

                    $notifyAgents($ticket, $replyText ?: 'Sent an attachment', 'telegram_reply');

                    $clearState();
                    sendMsg($token, $chatId, "✅ Reply sent to #{$ticket['ticket_number']}!", null, $isGroup ? $replyToMsgId : null);
                    webhookLog("Reply SAVED to ticket {$ticketId}");
                }
            }
        }
    }
    // === SURVEY COMMENT STATE ===
    elseif ($st === 'survey_comment') {
        $ticketId = $stData['ticket_id'] ?? null;
        $rating = $stData['rating'] ?? null;
        webhookLog("Survey comment state for ticket: {$ticketId}");

        if ($text && $ticketId) {
            $db->query("UPDATE ticket_surveys SET comment = ?, comment_at = NOW() WHERE ticket_id = ?", [$text, $ticketId]);
            $clearState();
            sendMsg($token, $chatId, "✅ Thank you for your feedback! Your comment has been recorded.");
            webhookLog("Survey comment saved for ticket {$ticketId}");
        } else {
            sendMsg($token, $chatId, "Please type your comment or click Skip.");
        }
    }
    // === COMMANDS ===
    elseif ($text && $text[0] === '/') {
        // Extract command (handle @botname suffix in groups)
        $cmdParts = explode(' ', $text);
        $cmd = strtolower(explode('@', $cmdParts[0])[0]);
        webhookLog("CMD: {$cmd}" . ($isGroup ? " (group)" : ""));

        switch ($cmd) {
            case '/start':
                $clearState();
                $welcome = $config['welcome_message'] ?? "Welcome to Support Bot!";
                if ($isGroup) {
                    sendMsg($token, $chatId, "{$welcome}\n\n<b>Group Commands:</b>\n/newticket - Create ticket\n/mytickets - View tickets\n/help - Help");
                } else {
                    sendMsg($token, $chatId, $user
                        ? "{$welcome}\n\nHi <b>{$user['name']}</b>! Use /help for commands."
                        : "{$welcome}\n\nUse /link to connect your email first.");
                }
                break;

            case '/help':
                if ($isGroup) {
                    sendMsg($token, $chatId, "<b>Group Commands:</b>\n/newticket - Create support ticket\n/mytickets - View group tickets\n/cancel - Cancel current action\n/help - Show this help");
                } else {
                    sendMsg($token, $chatId, "<b>Commands:</b>\n/link - Link email\n/newticket - Create ticket\n/mytickets - View tickets\n/cancel - Cancel\n/help - Help");
                }
                break;

            case '/link':
                if ($isGroup) {
                    // In groups, tell user to link via private message
                    sendMsg($token, $chatId, "📱 Please send /link to me in a private message to link your email.", null, $replyToMsgId);
                } else {
                    $setState('email', []);
                    sendMsg($token, $chatId, "Enter your email:");
                }
                break;

            case '/newticket':
                if (!$user) {
                    if ($isGroup) {
                        sendMsg($token, $chatId, "⚠️ @{$username}, please /link your email first (send /link to me privately).", null, $replyToMsgId);
                    } else {
                        sendMsg($token, $chatId, "Use /link first.");
                    }
                } else {
                    $setState('subject', []);
                    $msg = $isGroup
                        ? "📝 @{$username}, enter the ticket subject:"
                        : "<b>New Ticket</b>\n\nStep 1: Enter subject:";
                    sendMsg($token, $chatId, $msg, null, $isGroup ? $replyToMsgId : null);
                }
                break;

            case '/mytickets':
                if (!$user && !$isGroup) {
                    sendMsg($token, $chatId, "Use /link first.");
                    break;
                }

                // For groups, show tickets created from this group OR by linked users
                if ($isGroup) {
                    $tickets = $db->select(
                        "SELECT * FROM tickets WHERE telegram_chat_id = ? AND status != 'closed' ORDER BY created_at DESC LIMIT 10",
                        [$chatId]
                    );
                } else {
                    $tickets = $db->select(
                        "SELECT * FROM tickets WHERE requester_id = ? AND status != 'closed' ORDER BY created_at DESC LIMIT 10",
                        [$user['id']]
                    );
                }

                if (!$tickets) {
                    sendMsg($token, $chatId, $isGroup ? "No open tickets in this group. Use /newticket." : "No open tickets. Use /newticket.");
                    break;
                }

                $kb = [];
                $txt = $isGroup ? "<b>Group Tickets:</b>\n\n" : "<b>Your Tickets:</b>\n\n";
                foreach ($tickets as $t) {
                    $txt .= "• #{$t['ticket_number']} - {$t['status']}\n  " . substr($t['subject'], 0, 30) . "\n";
                    $kb[] = [['text' => "#{$t['ticket_number']}", 'callback_data' => "view:{$t['id']}"]];
                }
                sendMsg($token, $chatId, $txt, $kb);
                break;

            case '/cancel':
                $clearState();
                sendMsg($token, $chatId, "Cancelled. /help for commands.");
                break;

            default:
                if (!$isGroup) { // Don't spam groups with unknown command messages
                    sendMsg($token, $chatId, "Unknown command. /help");
                }
        }
    }
    // === EMAIL STATE (private only) ===
    elseif ($st === 'email' && !$isGroup) {
        if (!filter_var($text, FILTER_VALIDATE_EMAIL)) {
            sendMsg($token, $chatId, "Invalid email. Try again:");
        } else {
            $existing = $db->selectOne("SELECT * FROM users WHERE email = ? AND company_id = ?", [$text, $companyId]);
            if ($existing) {
                $db->update('users', ['telegram_chat_id' => $chatId], 'id = ?', [$existing['id']]);
            } else {
                $db->insert('users', [
                    'company_id' => $companyId, 'email' => $text, 'name' => $firstName,
                    'password_hash' => password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT),
                    'role' => 'customer', 'telegram_chat_id' => $chatId, 'is_active' => 1
                ]);
            }
            $clearState();
            sendMsg($token, $chatId, "✅ Linked! Use /newticket to create a ticket.");
        }
    }
    // === SUBJECT STATE ===
    elseif ($st === 'subject') {
        if (strlen($text) < 3) {
            sendMsg($token, $chatId, "Subject too short:", null, $isGroup ? $replyToMsgId : null);
        } else {
            $setState('desc', ['subject' => $text]);
            sendMsg($token, $chatId, $isGroup
                ? "📝 Now describe the issue (or send photo/file):"
                : "Step 2: Describe the issue (or send photo/file):", null, $isGroup ? $replyToMsgId : null);
        }
    }
    // === DESC STATE ===
    elseif ($st === 'desc') {
        $desc = $text ?: $caption ?: ($voice ? '[Voice Message]' : ($videoNote ? '[Video Note]' : ($audio ? '[Audio]' : '')));
        if (strlen($desc) < 3 && !$photo && !$doc && !$voice && !$videoNote && !$audio) {
            sendMsg($token, $chatId, "Please describe or send a file/voice:");
        } elseif (!$user) {
            $clearState();
            $msg = $isGroup ? "⚠️ Please /link your email first (send /link to me privately)." : "Use /link first.";
            sendMsg($token, $chatId, $msg);
        } else {
            $subj = $stData['subject'] ?? 'Support';
            $last = $db->selectOne("SELECT ticket_number FROM tickets WHERE company_id = ? ORDER BY id DESC LIMIT 1", [$companyId]);
            $num = 1;
            if ($last && preg_match('/TKT-(\d+)/', $last['ticket_number'], $m)) $num = (int)$m[1] + 1;
            $tn = 'TKT-' . str_pad($num, 6, '0', STR_PAD_LEFT);

            $tid = $db->insert('tickets', [
                'company_id' => $companyId, 'ticket_number' => $tn, 'subject' => $subj,
                'description' => $desc ?: 'See attachment', 'status' => 'open', 'priority' => 'medium',
                'source' => 'telegram', 'requester_id' => $user['id'],
                'requester_email' => $user['email'], 'requester_name' => $user['name'],
                'telegram_chat_id' => $chatId // Track source chat (private or group)
            ]);
            $mid = $db->insert('ticket_messages', [
                'ticket_id' => $tid, 'user_id' => $user['id'],
                'message' => $desc ?: 'See attachment', 'is_internal' => 0, 'source' => 'telegram'
            ]);
            if ($photo) { $p = end($photo); $saveFile($p['file_id'], 'photo.jpg', 'image/jpeg', $tid, $mid); }
            elseif ($doc) { $saveFile($doc['file_id'], $doc['file_name'] ?? 'file', $doc['mime_type'] ?? 'application/octet-stream', $tid, $mid); }
            elseif ($voice) { $saveFile($voice['file_id'], 'voice_' . time() . '.ogg', $voice['mime_type'] ?? 'audio/ogg', $tid, $mid); }
            elseif ($videoNote) { $saveFile($videoNote['file_id'], 'video_note_' . time() . '.mp4', 'video/mp4', $tid, $mid); }
            elseif ($audio) { $saveFile($audio['file_id'], $audio['file_name'] ?? ('audio_' . time() . '.mp3'), $audio['mime_type'] ?? 'audio/mpeg', $tid, $mid); }

            $newTicket = ['id' => $tid, 'ticket_number' => $tn, 'subject' => $subj, 'assigned_to' => null];
            $notifyAgents($newTicket, $subj, 'new_ticket');

            $clearState();
            $successMsg = $isGroup
                ? "✅ Ticket <b>#{$tn}</b> created!\n\nSubject: {$subj}\n\nWe'll update this group when there's a response."
                : "✅ Ticket #{$tn} created!\n\nSubject: {$subj}\n\nWe'll notify you of updates.";
            sendMsg($token, $chatId, $successMsg, null, $isGroup ? $replyToMsgId : null);
            webhookLog("Ticket created: {$tn}" . ($isGroup ? " (from group)" : ""));
        }
    }
    // === STANDALONE FILE/VOICE ===
    elseif (($photo || $doc || $voice || $videoNote || $audio) && $user) {
        $t = $db->selectOne("SELECT * FROM tickets WHERE requester_id = ? AND status NOT IN ('closed','resolved') ORDER BY updated_at DESC LIMIT 1", [$user['id']]);
        if ($t) {
            $msgText = $caption ?: ($voice ? '[Voice Message]' : ($videoNote ? '[Video Note]' : ($audio ? '[Audio]' : 'File')));
            $mid = $db->insert('ticket_messages', ['ticket_id' => $t['id'], 'user_id' => $user['id'], 'message' => $msgText, 'is_internal' => 0, 'source' => 'telegram']);
            if ($photo) { $p = end($photo); $saveFile($p['file_id'], 'photo.jpg', 'image/jpeg', $t['id'], $mid); }
            elseif ($doc) { $saveFile($doc['file_id'], $doc['file_name'] ?? 'file', $doc['mime_type'] ?? 'application/octet-stream', $t['id'], $mid); }
            elseif ($voice) { $saveFile($voice['file_id'], 'voice_' . time() . '.ogg', $voice['mime_type'] ?? 'audio/ogg', $t['id'], $mid); }
            elseif ($videoNote) { $saveFile($videoNote['file_id'], 'video_note_' . time() . '.mp4', 'video/mp4', $t['id'], $mid); }
            elseif ($audio) { $saveFile($audio['file_id'], $audio['file_name'] ?? ('audio_' . time() . '.mp3'), $audio['mime_type'] ?? 'audio/mpeg', $t['id'], $mid); }
            $db->update('tickets', ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$t['id']]);

            $notifyAgents($t, $msgText, 'telegram_reply');

            sendMsg($token, $chatId, "📎 Added to #{$t['ticket_number']}", null, $isGroup ? $replyToMsgId : null);
        } else if (!$isGroup) {
            sendMsg($token, $chatId, "No open ticket. Use /newticket first.");
        }
    }
    // === DEFAULT (only for private chats) ===
    elseif ($text && !$isGroup) {
        sendMsg($token, $chatId, "Use /help for commands.");
    }

    webhookLog("=== END ===");
} catch (\Throwable $e) {
    webhookLog("ERR: " . $e->getMessage() . " @ " . $e->getFile() . ":" . $e->getLine());
}

header('Content-Type: application/json');
echo json_encode(['ok' => true]);
