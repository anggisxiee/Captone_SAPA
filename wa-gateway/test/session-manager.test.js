import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import test from 'node:test';
import { buatManagerSesi, GatewayError } from '../src/session-manager.js';

const NOMOR = '6281200000001';
const logger = { info() {}, warn() {}, error() {}, debug() {} };
const tick = () => new Promise(r => setImmediate(r));

// start() menunggu operasi berkas sebelum membuat koneksi, jadi tunggu sampai koneksinya ada.
async function tungguKoneksi(l, jumlah = 1) {
  for (let i = 0; i < 500 && l.koneksiDibuat.length < jumlah; i++) await new Promise(r => setTimeout(r, 2));
  assert.ok(l.koneksiDibuat.length >= jumlah, 'koneksi tidak dibuat');
}

async function buatLingkungan(opsi = {}) {
  const authDir = await fs.mkdtemp(path.join(os.tmpdir(), 'sapa-gw-'));

  const koneksiDibuat = [];
  const pabrik = async (nomor, handlers) => {
    const k = {
      nomor,
      handlers,
      dikirim: [],
      gagalKirim: null,
      loggedOut: false,
      ditutup: false,
      async kirim(telepon, isi) {
        if (k.gagalKirim) throw k.gagalKirim;
        k.dikirim.push([telepon, isi]);
        return `id-${k.dikirim.length}`;
      },
      async logout() { k.loggedOut = true; },
      tutup() { k.ditutup = true; },
    };
    koneksiDibuat.push(k);
    return k;
  };

  const panggilan = { pesan: [], sesi: [] };
  const balasan = { pesan: [] };
  const webhook = {
    async pesan(b) { panggilan.pesan.push(b); return balasan.pesan.shift() ?? { ok: true, status: 201, data: {} }; },
    async sesi(b) { panggilan.sesi.push(b); return { ok: true, status: 200, data: {} }; },
  };

  // Penjadwal manual agar tes tidak menunggu waktu nyata.
  const tugas = [];
  const jadwal = (fn, ms) => { const t = { fn, ms, batal: false }; tugas.push(t); return t; };
  const batal = t => { if (t) t.batal = true; };
  const jalankanTugas = async () => {
    for (const t of tugas.splice(0)) if (!t.batal) await t.fn();
    await tick();
  };

  const jam = { t: 1_000_000 };
  const manager = buatManagerSesi({
    pabrikKoneksi: pabrik, webhook, authDir, logger, jadwal, batal,
    sekarang: () => jam.t,
    qrTimeoutMs: 20000,
    backoff: { dasar: 1000, maks: 4000, gagalLaporan: 3 },
    ...opsi,
  });

  return { manager, authDir, koneksiDibuat, panggilan, balasan, tugas, jalankanTugas, jam };
}

async function mulaiDanScan(l, nomor = NOMOR) {
  const p = l.manager.start(nomor);
  await tungguKoneksi(l);
  const k = l.koneksiDibuat.at(-1);
  await k.handlers.qr('QR-1');
  await p;
  return k;
}

async function buatCreds(authDir, nomor, tertaut = true) {
  await fs.mkdir(path.join(authDir, nomor), { recursive: true });
  await fs.writeFile(path.join(authDir, nomor, 'creds.json'), JSON.stringify(tertaut ? { me: { id: `${nomor}:1@s.whatsapp.net` } } : {}));
}

test('start mengembalikan QR pertama dari WhatsApp', async () => {
  const l = await buatLingkungan();
  const p = l.manager.start(NOMOR);
  await tungguKoneksi(l);
  await l.koneksiDibuat[0].handlers.qr('QR-PERTAMA');

  assert.deepEqual(await p, { status: 'qr', qr: 'QR-PERTAMA' });
  assert.equal(l.manager.status(NOMOR), 'menunggu_qr');
  assert.equal(l.panggilan.sesi.length, 0, 'QR pertama tidak dikirim ganda lewat webhook');
});

test('QR berikutnya diteruskan ke Laravel lewat webhook', async () => {
  const l = await buatLingkungan();
  const k = await mulaiDanScan(l);

  await k.handlers.qr('QR-2');

  assert.deepEqual(l.panggilan.sesi, [{ nomor: NOMOR, status: 'qr', qr: 'QR-2' }]);
});

test('start berulang saat menunggu QR memakai QR yang sama dan tidak membuat koneksi baru', async () => {
  const l = await buatLingkungan();
  await mulaiDanScan(l);

  assert.deepEqual(await l.manager.start(NOMOR), { status: 'qr', qr: 'QR-1' });
  assert.equal(l.koneksiDibuat.length, 1);
});

test('start saat QR sudah usang (Buat ulang kode) memulai sesi baru, bukan memakai QR lama', async () => {
  const l = await buatLingkungan();
  const lama = await mulaiDanScan(l);                 // QR-1 diterima pada t=1_000_000
  await lama.handlers.qr('QR-ROTASI');                // WhatsApp memutar QR; kini tinggal ~20 detik
  l.jam.t += 30_000;                                  // pengguna menekan "Buat ulang kode" 30 detik kemudian

  const p = l.manager.start(NOMOR);
  await tungguKoneksi(l, 2);
  assert.equal(lama.ditutup, true, 'koneksi lama ditutup');
  await l.koneksiDibuat[1].handlers.qr('QR-SEGAR');

  assert.deepEqual(await p, { status: 'qr', qr: 'QR-SEGAR' });
  assert.equal(l.koneksiDibuat.length, 2);
});

test('start dua kali berurutan saat QR masih segar tetap memakai QR yang sama', async () => {
  const l = await buatLingkungan();
  await mulaiDanScan(l);
  l.jam.t += 3_000;

  assert.deepEqual(await l.manager.start(NOMOR), { status: 'qr', qr: 'QR-1' });
  assert.equal(l.koneksiDibuat.length, 1);
});

test('start bersamaan berbagi satu proses (tidak membuat dua koneksi)', async () => {
  const l = await buatLingkungan();
  const p1 = l.manager.start(NOMOR);
  const p2 = l.manager.start(NOMOR);
  await tungguKoneksi(l);
  await l.koneksiDibuat[0].handlers.qr('QR-A');

  assert.deepEqual(await p1, await p2);
  assert.equal(l.koneksiDibuat.length, 1);
});

test('nomor yang dipindai sama -> tersambung dan Laravel diberi tahu', async () => {
  const l = await buatLingkungan();
  const k = await mulaiDanScan(l);

  await k.handlers.terbuka({ telepon: NOMOR });

  assert.equal(l.manager.status(NOMOR), 'tersambung');
  assert.deepEqual(l.panggilan.sesi, [{ nomor: NOMOR, status: 'tersambung' }]);
  assert.deepEqual(await l.manager.start(NOMOR), { status: 'tersambung' });
});

test('nomor yang dipindai berbeda -> ditolak, logout, dan dilaporkan gagal', async () => {
  const l = await buatLingkungan();
  const k = await mulaiDanScan(l);

  await k.handlers.terbuka({ telepon: '6289999999999' });

  assert.equal(k.loggedOut, true);
  assert.equal(l.manager.status(NOMOR), 'tidak_ada');
  assert.equal(l.panggilan.sesi.length, 1);
  assert.equal(l.panggilan.sesi[0].status, 'gagal');
  assert.match(l.panggilan.sesi[0].alasan, /berbeda/);
  await assert.rejects(fs.access(path.join(l.authDir, NOMOR)), 'kredensial nomor salah dihapus');
});

test('restartRequired setelah pemindaian menyambung ulang tanpa melapor ke Laravel', async () => {
  const l = await buatLingkungan();
  const k = await mulaiDanScan(l);

  await k.handlers.tertutup({ kode: 515, restartRequired: true });

  assert.equal(l.koneksiDibuat.length, 2);
  assert.equal(l.panggilan.sesi.length, 0);
});

test('koneksi lama yang sudah digantikan tidak lagi memengaruhi sesi', async () => {
  const l = await buatLingkungan();
  const lama = await mulaiDanScan(l);
  await lama.handlers.tertutup({ kode: 515, restartRequired: true });
  const baru = l.koneksiDibuat[1];
  await baru.handlers.terbuka({ telepon: NOMOR });

  await lama.handlers.tertutup({ kode: 500 }); // sinyal basi dari koneksi lama

  assert.equal(l.manager.status(NOMOR), 'tersambung');
});

test('logout dari ponsel -> sesi dihapus, kredensial dihapus, Laravel diberi tahu terputus', async () => {
  const l = await buatLingkungan();
  const k = await mulaiDanScan(l);
  await k.handlers.terbuka({ telepon: NOMOR });
  l.panggilan.sesi.length = 0;
  await buatCreds(l.authDir, NOMOR);

  await k.handlers.tertutup({ kode: 401, loggedOut: true });

  assert.equal(l.manager.status(NOMOR), 'tidak_ada');
  assert.equal(l.panggilan.sesi[0].status, 'terputus');
  await assert.rejects(fs.access(path.join(l.authDir, NOMOR)));
});

test('sesi digantikan perangkat lain -> berhenti tanpa sambung ulang dan kredensial dipertahankan', async () => {
  const l = await buatLingkungan();
  const k = await mulaiDanScan(l);
  await k.handlers.terbuka({ telepon: NOMOR });
  l.panggilan.sesi.length = 0;
  await buatCreds(l.authDir, NOMOR);

  await k.handlers.tertutup({ kode: 440, replaced: true });
  await l.jalankanTugas();

  assert.equal(l.koneksiDibuat.length, 1);
  assert.equal(l.panggilan.sesi[0].status, 'terputus');
  await fs.access(path.join(l.authDir, NOMOR));
});

test('putus sementara -> sambung ulang dengan backoff; baru dilaporkan setelah gagal berulang', async () => {
  const l = await buatLingkungan();
  const k = await mulaiDanScan(l);
  await k.handlers.terbuka({ telepon: NOMOR });
  l.panggilan.sesi.length = 0;

  // gagal ke-1 dan ke-2: menyambung ulang, belum dilaporkan
  let aktif = k;
  for (const [i, jedaDiharapkan] of [[1, 1000], [2, 2000]]) {
    await aktif.handlers.tertutup({ kode: 408, alasan: 'putus' });
    assert.equal(l.manager.status(NOMOR), 'menyambung');
    assert.equal(l.tugas.at(-1).ms, jedaDiharapkan, `jeda gagal ke-${i}`);
    await l.jalankanTugas();
    aktif = l.koneksiDibuat.at(-1);
  }
  assert.equal(l.panggilan.sesi.length, 0);

  // gagal ke-3: dilaporkan terputus (sekali saja)
  await aktif.handlers.tertutup({ kode: 408, alasan: 'putus' });
  assert.deepEqual(l.panggilan.sesi.map(s => s.status), ['terputus']);
  assert.equal(l.tugas.at(-1).ms, 4000, 'jeda dibatasi nilai maks');
  await l.jalankanTugas();
  aktif = l.koneksiDibuat.at(-1);
  await aktif.handlers.tertutup({ kode: 408 });
  assert.equal(l.panggilan.sesi.length, 1, 'tidak melapor terputus berulang kali');

  // pulih: tersambung lagi dan Laravel diberi tahu
  await l.jalankanTugas();
  await l.koneksiDibuat.at(-1).handlers.terbuka({ telepon: NOMOR });
  assert.equal(l.panggilan.sesi.at(-1).status, 'tersambung');
  assert.equal(l.manager.status(NOMOR), 'tersambung');
});

test('QR tak dipindai (koneksi menutup sebelum terdaftar) -> dibersihkan tanpa lapor ke Laravel', async () => {
  const l = await buatLingkungan();
  const k = await mulaiDanScan(l);
  await buatCreds(l.authDir, NOMOR, false); // folder setengah jadi buatan Baileys

  await k.handlers.tertutup({ kode: 408, alasan: 'QR refs attempts ended' });

  assert.equal(l.manager.status(NOMOR), 'tidak_ada');
  assert.equal(l.panggilan.sesi.length, 0);
  await assert.rejects(fs.access(path.join(l.authDir, NOMOR)));
});

test('start gagal bila WhatsApp tidak mengirim QR tepat waktu', async () => {
  const l = await buatLingkungan();
  const p = l.manager.start(NOMOR);
  p.catch(() => {});
  await tungguKoneksi(l);

  await l.jalankanTugas(); // pewaktu QR habis

  await assert.rejects(p, e => e instanceof GatewayError && e.status === 504);
  assert.equal(l.manager.status(NOMOR), 'tidak_ada');
});

test('start gagal jika pabrik koneksi melempar galat', async () => {
  const l = await buatLingkungan({ pabrikKoneksi: async () => { throw new Error('boom'); } });

  await assert.rejects(l.manager.start(NOMOR), e => e.status === 502 && /boom/.test(e.message));
  assert.equal(l.manager.status(NOMOR), 'tidak_ada');
});

test('kirim: berhasil saat tersambung', async () => {
  const l = await buatLingkungan();
  const k = await mulaiDanScan(l);
  await k.handlers.terbuka({ telepon: NOMOR });

  assert.equal(await l.manager.kirim(NOMOR, '6285711110001', 'Halo'), 'id-1');
  assert.deepEqual(k.dikirim, [['6285711110001', 'Halo']]);
});

test('kirim: 409 bila nomor tidak tersambung atau tidak dikenal', async () => {
  const l = await buatLingkungan();
  await assert.rejects(l.manager.kirim(NOMOR, '6285711110001', 'Halo'), e => e.status === 409);

  await mulaiDanScan(l); // masih menunggu QR
  await assert.rejects(l.manager.kirim(NOMOR, '6285711110001', 'Halo'), e => e.status === 409);
});

test('kirim: nomor tujuan tidak terdaftar -> 422; galat lain -> 502', async () => {
  const l = await buatLingkungan();
  const k = await mulaiDanScan(l);
  await k.handlers.terbuka({ telepon: NOMOR });

  k.gagalKirim = Object.assign(new Error('x'), { kode: 'TIDAK_TERDAFTAR' });
  await assert.rejects(l.manager.kirim(NOMOR, '6285711110001', 'Halo'), e => e.status === 422);

  k.gagalKirim = new Error('socket putus');
  await assert.rejects(l.manager.kirim(NOMOR, '6285711110001', 'Halo'), e => e.status === 502);
});

test('pesan masuk diteruskan ke webhook dengan format Laravel', async () => {
  const l = await buatLingkungan();
  const k = await mulaiDanScan(l);
  await k.handlers.terbuka({ telepon: NOMOR });

  await k.handlers.pesanMasuk({ dari: '6285711110001', nama: 'Bu Ratna', isi: 'Halo', idPesan: 'WAMID1' });

  assert.deepEqual(l.panggilan.pesan, [
    { nomor: NOMOR, dari: '6285711110001', nama: 'Bu Ratna', isi: 'Halo', id_pesan: 'WAMID1' },
  ]);
});

test('pesan masuk ditolak 409 -> status disinkronkan lalu dicoba sekali lagi', async () => {
  const l = await buatLingkungan();
  const k = await mulaiDanScan(l);
  await k.handlers.terbuka({ telepon: NOMOR });
  l.panggilan.sesi.length = 0;
  l.balasan.pesan.push({ ok: false, status: 409, data: {} }, { ok: true, status: 201, data: {} });

  await k.handlers.pesanMasuk({ dari: '6285711110001', nama: null, isi: 'Halo', idPesan: 'W2' });

  assert.equal(l.panggilan.pesan.length, 2);
  assert.deepEqual(l.panggilan.sesi, [{ nomor: NOMOR, status: 'tersambung' }]);
});

test('pesan masuk yang tetap ditolak tidak diulang tanpa batas', async () => {
  const l = await buatLingkungan();
  const k = await mulaiDanScan(l);
  await k.handlers.terbuka({ telepon: NOMOR });
  l.balasan.pesan.push({ ok: false, status: 409, data: {} }, { ok: false, status: 409, data: {} });

  await k.handlers.pesanMasuk({ dari: '6285711110001', nama: null, isi: 'Halo', idPesan: 'W3' });

  assert.equal(l.panggilan.pesan.length, 2);
});

test('putuskan: logout, hapus kredensial, dan TIDAK memanggil balik webhook', async () => {
  const l = await buatLingkungan();
  const k = await mulaiDanScan(l);
  await k.handlers.terbuka({ telepon: NOMOR });
  l.panggilan.sesi.length = 0;
  await buatCreds(l.authDir, NOMOR);

  await l.manager.putuskan(NOMOR);

  assert.equal(k.loggedOut, true);
  assert.equal(k.ditutup, true);
  assert.equal(l.manager.status(NOMOR), 'tidak_ada');
  assert.equal(l.panggilan.sesi.length, 0);
  await assert.rejects(fs.access(path.join(l.authDir, NOMOR)));
});

test('putuskan nomor tanpa sesi tetap membersihkan folder sisa', async () => {
  const l = await buatLingkungan();
  await buatCreds(l.authDir, NOMOR);

  await l.manager.putuskan(NOMOR);

  await assert.rejects(fs.access(path.join(l.authDir, NOMOR)));
});

test('pulihkan hanya menyambung sesi yang sudah tertaut', async () => {
  const l = await buatLingkungan();
  await buatCreds(l.authDir, '6281200000001', true);
  await buatCreds(l.authDir, '6281200000002', false); // QR tak pernah dipindai
  await fs.mkdir(path.join(l.authDir, 'bukan-nomor'));

  const dipulihkan = await l.manager.pulihkan();

  assert.deepEqual(dipulihkan, ['6281200000001']);
  assert.equal(l.koneksiDibuat.length, 1);
  assert.equal(l.manager.status('6281200000001'), 'menyambung');
});

test('sesi yang dipulihkan tidak menghapus kredensial saat koneksi gagal (mis. jaringan mati)', async () => {
  const l = await buatLingkungan();
  await buatCreds(l.authDir, NOMOR, true);
  await l.manager.pulihkan();

  await l.koneksiDibuat[0].handlers.tertutup({ kode: 408, alasan: 'jaringan mati' });

  assert.equal(l.manager.status(NOMOR), 'menyambung');
  await fs.access(path.join(l.authDir, NOMOR, 'creds.json'));
});

test('tutupSemua menutup koneksi tanpa menghapus kredensial', async () => {
  const l = await buatLingkungan();
  const k = await mulaiDanScan(l);
  await k.handlers.terbuka({ telepon: NOMOR });
  await buatCreds(l.authDir, NOMOR);

  l.manager.tutupSemua();

  assert.equal(k.ditutup, true);
  assert.equal(l.manager.jumlahSesi(), 0);
  await fs.access(path.join(l.authDir, NOMOR, 'creds.json'));
});
