I scanned the project (`src`, `public`, `config`, `views`) and ran PHP lint on all PHP files in those folders.  
Result: no syntax errors, but several high-risk issues.

1. Critical: Public debug/admin scripts are directly reachable and can mutate Telegram/webhook state.
`public/.htaccess:14`  
`public/.htaccess:15`  
`public/.htaccess:16`  
`public/setup_webhook.php:33`  
`public/delete_webhook_direct.php:29`  
`public/check_webhook.php:48`  
`public/test-api.php:7`  
`public/test-api.php:63`  
`public/telegram_full_debug.php:85`

2. Critical: Telegram webhook secret mismatch is logged but not blocked.
`src/Controllers/Api/TelegramController.php:76`  
`src/Controllers/Api/TelegramController.php:78`

3. High: Sensitive data is committed to git (`.env`, logs, uploads/PII).
`.env`  
`storage/logs/miniapp_debug.log:15`  
`storage/logs/telegram_webhook_handler.log:5`  
`public/uploads/tickets/4/2026/02/photo_1770359426_69858a8460650.jpg`

4. High: Mini App auth is replay-prone.
`src/Controllers/Api/TelegramMiniAppController.php:73`  
`src/Controllers/Api/TelegramMiniAppController.php:281`  
`src/Controllers/Api/TelegramMiniAppController.php:320`  
`public/telegram-app.html:618`  
`public/telegram-app.html:707`

5. High: CSRF protection is effectively missing on session-based forms.
`src/Helpers/functions.php:57`  
`views/auth/login.php:8`  
`views/settings/telegram.php:13`

6. Medium: JWT/API auth flow is inconsistent and has weak fallback secret defaults.
`src/Core/Auth.php:18`  
`config/app.php:10`  
`src/Core/Auth.php:177`  
`public/index.php:26`  
`src/Controllers/AuthController.php:150`  
`src/Models/User.php:24`

7. Medium: Production error display is forced on.
`public/index.php:10`

8. Note: `src/Core/RateLimiter.php` is not present in the repo (despite IDE tab).

If you want, I can start patching this in priority order:
1. Lock down/remove public debug files and tighten `.htaccess`.
2. Enforce webhook secret check and rotate secrets.
3. Remove tracked secrets/logs/uploads from git and add `.gitignore`.
4. Add CSRF middleware + token validation.
5. Harden Mini App validation (`auth_date` TTL, POST-only initData, `hash_equals`).