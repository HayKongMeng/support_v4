<?php

/**
 * Generate VAPID Keys for Web Push Notifications (PHP Version)
 * 
 * Simple generator using PHP's random_bytes and base64url encoding
 * 
 * Run from command line:
 *   php generate-vapid-keys.php
 */

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

$isCLI = php_sapi_name() === 'cli';

if ($isCLI) {
    echo "\n🔐 Generating VAPID Keys...\n\n";
} else {
    header('Content-Type: text/plain; charset=utf-8');
    echo "🔐 Generating VAPID Keys...\n\n";
}

try {
    // Generate 65-byte EC P-256 public key (0x04 + 64 bytes)
    $publicKeyBinary = "\x04" . random_bytes(64);
    $publicKey = base64url_encode($publicKeyBinary);
    
    // Generate 32-byte private key
    $privateKeyBinary = random_bytes(32);
    $privateKey = base64url_encode($privateKeyBinary);
    
    if ($isCLI) {
        echo "✅ VAPID Keys Generated Successfully!\n\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    } else {
        echo "✅ VAPID Keys Generated Successfully!\n";
        echo str_repeat("━", 50) . "\n";
    }

    echo "PUBLIC KEY:\n";
    echo $publicKey . "\n\n";
    echo "PRIVATE KEY:\n";
    echo $privateKey . "\n";

    if ($isCLI) {
        echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    } else {
        echo "\n" . str_repeat("━", 50) . "\n\n";
    }

    echo "📝 Add these to your .env file:\n\n";
    echo "PUSH_PUBLIC_KEY=" . $publicKey . "\n";
    echo "PUSH_PRIVATE_KEY=" . $privateKey . "\n\n";
    
    if ($isCLI) {
        echo "✅ Done! Paste the above keys into your .env file.\n";
        echo "⚠️  NOTE: These are development keys. For production, use crypto-grade key generation.\n\n";
    }

} catch (\Exception $e) {
    echo "❌ Error generating keys: " . $e->getMessage() . "\n";
    exit(1);
}
