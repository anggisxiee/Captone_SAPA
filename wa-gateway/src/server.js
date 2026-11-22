import crypto from 'node:crypto';
import http from 'node:http';
import { GatewayError } from './session-manager.js';
import { normalisasiTelepon, teleponValid } from './jid.js';

const BATAS_BODY = 64 * 1024;

function tokenCocok(dikirim, token) {
  const a = Buffer.from(String(dikirim ?? ''));
  const b = Buffer.from(token);
  return a.length === b.length && crypto.timingSafeEqual(a, b);
}

function balas(res, status, data) {
  const isi = JSON.stringify(data);
  res.writeHead(status, { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(isi) });
  res.end(isi);
}

async function bacaJson(req) {
  const potongan = [];
  let total = 0;
  for await (const p of req) {
    total += p.length;
    if (total > BATAS_BODY) throw new GatewayError('Isi permintaan terlalu besar.', 413);
    potongan.push(p);
  }
  if (total === 0) return {};
  try {
    return JSON.parse(Buffer.concat(potongan).toString('utf8'));
  } catch {
    throw new GatewayError('Isi permintaan bukan JSON yang valid.', 400);
  }
}

function nomorDariParam(param) {
  const nomor = normalisasiTelepon(param);
  if (!teleponValid(nomor)) throw new GatewayError('Nomor tidak valid.', 422);
  return nomor;
}

/**
 * API HTTP gateway. Dipanggil Laravel; wajib memakai header X-Gateway-Token.
 *
 *   GET    /health
 *   POST   /sesi                  { nomor }            -> { status: 'qr', qr } | { status: 'tersambung' }
 *   GET    /sesi/:nomor                                -> { status }
 *   DELETE /sesi/:nomor                                -> { ok: true }
 *   POST   /sesi/:nomor/pesan     { telepon, isi }     -> { id_pesan }
 */
export function buatServer({ manager, token, logger }) {
  return http.createServer(async (req, res) => {
    try {
      if (!tokenCocok(req.headers['x-gateway-token'], token)) {
        return balas(res, 401, { pesan: 'Token gateway tidak valid.' });
      }

      const { pathname } = new URL(req.url, 'http://gateway.local');
      const metode = req.method;
      let cocok;

      if (metode === 'GET' && pathname === '/health') {
        return balas(res, 200, { ok: true, sesi: manager.jumlahSesi() });
      }

      if (metode === 'POST' && pathname === '/sesi') {
        const { nomor } = await bacaJson(req);
        return balas(res, 200, await manager.start(nomorDariParam(nomor)));
      }

      if ((cocok = pathname.match(/^\/sesi\/(\d+)$/))) {
        const nomor = nomorDariParam(cocok[1]);
        if (metode === 'GET') return balas(res, 200, { status: manager.status(nomor) });
        if (metode === 'DELETE') {
          await manager.putuskan(nomor);
          return balas(res, 200, { ok: true });
        }
      }

      if (metode === 'POST' && (cocok = pathname.match(/^\/sesi\/(\d+)\/pesan$/))) {
        const nomor = nomorDariParam(cocok[1]);
        const { telepon, isi } = await bacaJson(req);
        const tujuan = normalisasiTelepon(telepon);

        if (!teleponValid(tujuan)) throw new GatewayError('Nomor tujuan tidak valid.', 422);
        if (typeof isi !== 'string' || isi.trim() === '' || isi.length > 5000) {
          throw new GatewayError('Isi pesan wajib diisi (maksimal 5000 karakter).', 422);
        }

        return balas(res, 200, { id_pesan: await manager.kirim(nomor, tujuan, isi) });
      }

      return balas(res, 404, { pesan: 'Rute tidak ditemukan.' });
    } catch (e) {
      if (e instanceof GatewayError) return balas(res, e.status, { pesan: e.message });
      logger.error({ err: e.message }, 'kesalahan tak terduga di server');
      return balas(res, 500, { pesan: 'Kesalahan internal gateway.' });
    }
  });
}
