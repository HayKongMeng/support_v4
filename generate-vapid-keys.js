#!/usr/bin/env node

/**
 * Generate VAPID Keys for Web Push Notifications
 * 
 * Install web-push globally first:
 *   npm install -g web-push
 * 
 * Then run:
 *   node generate-vapid-keys.js
 */

const fs = require('fs');
const path = require('path');

try {
    const webpush = require('web-push');
    
    console.log('🔐 Generating VAPID Keys...\n');
    
    const vapidKeys = webpush.generateVAPIDKeys();
    
    console.log('✅ VAPID Keys Generated Successfully!\n');
    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    console.log('PUBLIC KEY:');
    console.log(vapidKeys.publicKey);
    console.log('\nPRIVATE KEY:');
    console.log(vapidKeys.privateKey);
    console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');
    
    console.log('📝 Add these to your .env file:\n');
    console.log(`PUSH_PUBLIC_KEY=${vapidKeys.publicKey}`);
    console.log(`PUSH_PRIVATE_KEY=${vapidKeys.privateKey}\n`);
    
    // Optionally save to file
    const envPath = path.join(__dirname, '.env.push');
    const envContent = `# Web Push VAPID Keys - Generated at ${new Date().toISOString()}\nPUSH_PUBLIC_KEY=${vapidKeys.publicKey}\nPUSH_PRIVATE_KEY=${vapidKeys.privateKey}\n`;
    
    fs.writeFileSync(envPath, envContent);
    console.log(`💾 Keys also saved to: .env.push\n`);
    
} catch (error) {
    if (error.code === 'MODULE_NOT_FOUND') {
        console.error('❌ Error: web-push module not found\n');
        console.log('Install it with:\n  npm install -g web-push\n');
        console.log('Then run this script again.\n');
    } else {
        console.error('❌ Error:', error.message);
    }
    process.exit(1);
}
