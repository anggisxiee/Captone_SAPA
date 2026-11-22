import assert from 'node:assert/strict';
import test from 'node:test';
import { bacaKonfigurasi } from '../src/config.js';

test('token wajib dan minimal 12 karakter', () => {
  assert.throws(() => bacaKonfigurasi({}), /GATEWAY_TOKEN/);
  assert.throws(() => bacaKonfigurasi({ GATEWAY_TOKEN: 'pendek' }), /GATEWAY_TOKEN/);
  assert.throws(() => bacaKonfigurasi({ GATEWAY_TOKEN: '            ' }), /GATEWAY_TOKEN/);
});

test('nilai bawaan aman: hanya localhost, port 3001', () => {
  const c = bacaKonfigurasi({ GATEWAY_TOKEN: 'token-cukup-panjang' });
  assert.equal(c.host, '127.0.0.1');
  assert.equal(c.port, 3001);
  assert.equal(c.laravelUrl, 'http://127.0.0.1:8000/api/webhook/whatsapp');
});

test('URL webhook dibersihkan dari garis miring akhir', () => {
  const c = bacaKonfigurasi({ GATEWAY_TOKEN: 'token-cukup-panjang', LARAVEL_WEBHOOK_URL: 'https://x.test/api/webhook/whatsapp//' });
  assert.equal(c.laravelUrl, 'https://x.test/api/webhook/whatsapp');
});
