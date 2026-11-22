import path from 'node:path';
import { resolveTelepon, jidKeTelepon } from './jid.js';
import { ekstrakPesan } from './pesan.js';

/**
 * Satu-satunya berkas yang menyentuh Baileys. Versi yang dipakai di-pin pada
 * package.json (7.0.0-rc14). Bila API Baileys berubah, cukup ubah berkas ini.
 */
export function buatPabrikBaileys({ authDir, logger }) {
  return async function buatKoneksi(nomor, handlers) {
    // Diimpor saat dibutuhkan agar sisa gateway bisa diuji tanpa Baileys terpasang.
    const baileys = await import('@whiskeysockets/baileys');
    const makeWASocket = baileys.default ?? baileys.makeWASocket;
    const {
      useMultiFileAuthState,
      makeCacheableSignalKeyStore,
      fetchLatestBaileysVersion,
      DisconnectReason,
      Browsers,
    } = baileys;

    const { state, saveCreds } = await useMultiFileAuthState(path.join(authDir, nomor));

    let version;
    try {
      ({ version } = await fetchLatestBaileysVersion());
    } catch (e) {
      logger.warn({ err: e.message }, 'gagal mengambil versi WhatsApp Web terbaru, memakai bawaan Baileys');
    }

    const sock = makeWASocket({
      version,
      logger: logger.child({ nomor, modul: 'baileys' }, { level: 'warn' }),
      auth: {
        creds: state.creds,
        keys: makeCacheableSignalKeyStore(state.keys, logger),
      },
      browser: Browsers.ubuntu('Chrome'),
      // Ponsel tetap menerima notifikasi, dan riwayat penuh tidak diunduh.
      // JANGAN menonaktifkan sinkronisasi riwayat (shouldSyncHistoryMessage):
      // Baileys v7 membutuhkannya untuk pemetaan LID awal dan memperingatkan
      // bahwa mematikannya menyebabkan galat sesi. Riwayat lama diabaikan di
      // handler messages.upsert (hanya type "notify" yang diteruskan).
      markOnlineOnConnect: false,
      syncFullHistory: false,
      generateHighQualityLinkPreview: false,
      // Tanpa penyimpanan pesan; dibutuhkan hanya untuk pengiriman ulang.
      getMessage: async () => undefined,
    });

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', update => {
      const { connection, lastDisconnect, qr } = update;

      if (qr) handlers.qr(qr);

      if (connection === 'open') {
        handlers.terbuka({ telepon: jidKeTelepon(sock.user?.id) ?? '' });
      }

      if (connection === 'close') {
        const kode = lastDisconnect?.error?.output?.statusCode;
        handlers.tertutup({
          kode,
          loggedOut: kode === DisconnectReason.loggedOut,
          restartRequired: kode === DisconnectReason.restartRequired,
          replaced: kode === DisconnectReason.connectionReplaced,
          alasan: lastDisconnect?.error?.message,
        });
      }
    });

    sock.ev.on('messages.upsert', async ({ messages, type }) => {
      if (type !== 'notify') return;

      for (const m of messages) {
        if (m.key.fromMe) continue;

        const dari = await resolveTelepon(m.key, {
          cariPnDariLid: lid => sock.signalRepository?.lidMapping?.getPNForLID(lid),
        });
        if (!dari) {
          // Isi pesan sengaja tidak dicatat (data pribadi).
          logger.warn({ nomor, idPesan: m.key.id }, 'pengirim tidak dapat dipetakan ke nomor telepon, pesan diabaikan');
          continue;
        }

        const isi = ekstrakPesan(m.message);
        if (!isi) continue;

        handlers.pesanMasuk({ dari, nama: m.pushName ?? null, isi, idPesan: m.key.id });
      }
    });

    // Hasil pengecekan nomor disimpan sebentar agar tidak menanyai WhatsApp
    // untuk setiap pesan ke pelanggan yang sama.
    const cacheNomor = new Map();
    const MASA_CACHE_MS = 10 * 60 * 1000;

    async function cariJid(telepon) {
      const tersimpan = cacheNomor.get(telepon);
      if (tersimpan && tersimpan.hingga > Date.now()) return tersimpan.jid;

      const [hasil] = (await sock.onWhatsApp(telepon)) ?? [];
      if (!hasil?.exists) {
        const galat = new Error('Nomor tujuan tidak terdaftar di WhatsApp.');
        galat.kode = 'TIDAK_TERDAFTAR';
        throw galat;
      }

      cacheNomor.set(telepon, { jid: hasil.jid, hingga: Date.now() + MASA_CACHE_MS });
      return hasil.jid;
    }

    return {
      async kirim(telepon, isi) {
        const jid = await cariJid(telepon);
        const hasil = await sock.sendMessage(jid, { text: isi });
        return hasil?.key?.id ?? null;
      },
      logout: () => sock.logout(),
      tutup: () => sock.end(undefined),
    };
  };
}
