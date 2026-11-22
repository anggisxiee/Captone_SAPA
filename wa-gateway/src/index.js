import pino from 'pino';
import { buatPabrikBaileys } from './baileys-socket.js';
import { bacaKonfigurasi } from './config.js';
import { buatServer } from './server.js';
import { buatManagerSesi } from './session-manager.js';
import { buatKlienWebhook } from './webhook.js';

let config;
try {
  config = bacaKonfigurasi();
} catch (e) {
  console.error(`Konfigurasi tidak valid: ${e.message}`);
  process.exit(1);
}

const logger = pino({ level: config.logLevel });

const webhook = buatKlienWebhook({ baseUrl: config.laravelUrl, token: config.token, logger });
const manager = buatManagerSesi({
  pabrikKoneksi: buatPabrikBaileys({ authDir: config.authDir, logger }),
  webhook,
  authDir: config.authDir,
  logger,
  qrTimeoutMs: config.qrTimeoutMs,
});

const server = buatServer({ manager, token: config.token, logger });

server.listen(config.port, config.host, async () => {
  logger.info({ host: config.host, port: config.port }, 'gateway WhatsApp berjalan');
  const dipulihkan = await manager.pulihkan();
  if (dipulihkan.length) logger.info({ nomor: dipulihkan }, 'sesi dipulihkan');
});

function berhenti(sinyal) {
  logger.info({ sinyal }, 'gateway berhenti');
  manager.tutupSemua();
  server.close(() => process.exit(0));
  setTimeout(() => process.exit(0), 3000).unref();
}
process.on('SIGINT', () => berhenti('SIGINT'));
process.on('SIGTERM', () => berhenti('SIGTERM'));
