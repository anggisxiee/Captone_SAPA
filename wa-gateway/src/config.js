import path from 'node:path';

export function bacaKonfigurasi(env = process.env) {
  const token = (env.GATEWAY_TOKEN ?? '').trim();

  if (token.length < 12) {
    throw new Error(
      'GATEWAY_TOKEN wajib diisi (minimal 12 karakter) dan harus sama dengan WHATSAPP_WEBHOOK_TOKEN di Laravel.',
    );
  }

  return {
    host: env.HOST ?? '127.0.0.1',
    port: Number(env.PORT ?? 3001),
    token,
    laravelUrl: (env.LARAVEL_WEBHOOK_URL ?? 'http://127.0.0.1:8000/api/webhook/whatsapp').replace(/\/+$/, ''),
    authDir: path.resolve(env.AUTH_DIR ?? './auth'),
    qrTimeoutMs: Number(env.QR_TIMEOUT_MS ?? 20000),
    logLevel: env.LOG_LEVEL ?? 'info',
  };
}
