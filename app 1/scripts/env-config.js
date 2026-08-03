/**
 * env-config.js — Resuelve credenciales según TEST_MODE
 * 
 * En .env: TEST_MODE=true  → usa credenciales _TEST
 *          TEST_MODE=false → usa credenciales de producción (P)
 */
const path = require('path');
require('dotenv').config({ path: path.resolve(__dirname, '../.env') });

function isTestMode() {
  const v = (process.env.TEST_MODE || '').trim().toLowerCase();
  return ['1', 'true', 'yes', 'on'].includes(v);
}

function getConfig() {
  const testMode = isTestMode();

  if (testMode) {
    return {
      testMode: true,
      label: '🧪 TEST MODE',
      WP_API_URL: (process.env.URL_TEST || '').trim().replace(/^['"]|['"]$/g, ''),
      CONSUMER_KEY: (process.env.WOOCOMMERCE_CONSUMER_KEY_TEST || '').trim().replace(/^['"]|['"]$/g, ''),
      CONSUMER_SECRET: (process.env.WOOCOMMERCE_CONSUMER_SECRET_TEST || '').trim().replace(/^['"]|['"]$/g, ''),
      WP_MEDIA_USER: (process.env.WP_MEDIA_USER_TEST || '').trim().replace(/^['"]|['"]$/g, ''),
      WP_MEDIA_PASSWORD: (process.env.WP_MEDIA_PASSWORD_TEST || '').trim().replace(/^['"]|['"]$/g, '')
    };
  }

  return {
    testMode: false,
    label: '🏪 PRODUCCIÓN',
    WP_API_URL: (process.env.URLP || '').trim().replace(/^['"]|['"]$/g, ''),
    CONSUMER_KEY: (process.env.WOOCOMMERCE_CONSUMER_KEYP || '').trim().replace(/^['"]|['"]$/g, ''),
    CONSUMER_SECRET: (process.env.WOOCOMMERCE_CONSUMER_SECRETP || '').trim().replace(/^['"]|['"]$/g, ''),
    WP_MEDIA_USER: (process.env.WP_MEDIA_USER || '').trim().replace(/^['"]|['"]$/g, ''),
    WP_MEDIA_PASSWORD: (process.env.WP_MEDIA_PASSWORD || '').trim().replace(/^['"]|['"]$/g, '')
  };
}

module.exports = { getConfig, isTestMode };
