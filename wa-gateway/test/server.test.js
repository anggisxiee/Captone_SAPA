import assert from 'node:assert/strict';
import test from 'node:test';
import { buatServer } from '../src/server.js';
import { GatewayError } from '../src/session-manager.js';

const TOKEN = 'token-uji-123456';
const logger = { info() {}, warn() {}, error() {}, debug() {} };

async function jalankan(manager) {
  const server = buatServer({ manager, token: TOKEN, logger });
  await new Promise(r => server.listen(0, '127.0.0.1', r));
  const base = `http://127.0.0.1:${server.address().port}`;
  const panggil = (path, { metode = 'GET', body, token = TOKEN, mentah } = {}) =>
    fetch(base + path, {
      method: metode,
      headers: { 'Content-Type': 'application/json', ...(token ? { 'X-Gateway-Token': token } : {}) },
      body: metode === 'GET' ? undefined : (mentah ?? (body ? JSON.stringify(body) : undefined)),
    }).then(async r => ({ status: r.status, data: await r.json() }));
  return { panggil, tutup: () => new Promise(r => server.close(r)) };
}

const managerPalsu = (menimpa = {}) => ({
  jumlahSesi: () => 2,
  status: n => (n === '6281200000001' ? 'tersambung' : 'tidak_ada'),
  start: async n => ({ status: 'qr', qr: `QR-${n}` }),
  putuskan: async () => {},
  kirim: async () => 'WAMID-1',
  ...menimpa,
});

test('semua rute menolak permintaan tanpa token atau dengan token salah (401)', async () => {
  const s = await jalankan(managerPalsu());
  try {
    for (const token of [null, 'salah', TOKEN + 'x', '']) {
      for (const [metode, path] of [['GET', '/health'], ['POST', '/sesi'], ['GET', '/sesi/6281200000001'], ['DELETE', '/sesi/6281200000001'], ['POST', '/sesi/6281200000001/pesan']]) {
        const r = await s.panggil(path, { metode, token, body: {} });
        assert.equal(r.status, 401, `${metode} ${path} token=${token}`);
      }
    }
  } finally { await s.tutup(); }
});

test('GET /health', async () => {
  const s = await jalankan(managerPalsu());
  try {
    assert.deepEqual((await s.panggil('/health')).data, { ok: true, sesi: 2 });
  } finally { await s.tutup(); }
});

test('POST /sesi mengembalikan QR, nomor diseragamkan ke 62', async () => {
  const s = await jalankan(managerPalsu());
  try {
    const r = await s.panggil('/sesi', { metode: 'POST', body: { nomor: '0812-0000-0001' } });
    assert.equal(r.status, 200);
    assert.deepEqual(r.data, { status: 'qr', qr: 'QR-6281200000001' });
  } finally { await s.tutup(); }
});

test('POST /sesi: nomor tidak valid 422; JSON rusak 400', async () => {
  const s = await jalankan(managerPalsu());
  try {
    assert.equal((await s.panggil('/sesi', { metode: 'POST', body: { nomor: '12' } })).status, 422);
    assert.equal((await s.panggil('/sesi', { metode: 'POST', body: {} })).status, 422);
    assert.equal((await s.panggil('/sesi', { metode: 'POST', mentah: '{rusak' })).status, 400);
  } finally { await s.tutup(); }
});

test('galat dari manajer diteruskan dengan status dan pesannya', async () => {
  const s = await jalankan(managerPalsu({ start: async () => { throw new GatewayError('QR tidak datang', 504); } }));
  try {
    const r = await s.panggil('/sesi', { metode: 'POST', body: { nomor: '6281200000001' } });
    assert.equal(r.status, 504);
    assert.equal(r.data.pesan, 'QR tidak datang');
  } finally { await s.tutup(); }
});

test('galat tak terduga menjadi 500 tanpa membocorkan detail', async () => {
  const s = await jalankan(managerPalsu({ start: async () => { throw new Error('rahasia internal /etc/x'); } }));
  try {
    const r = await s.panggil('/sesi', { metode: 'POST', body: { nomor: '6281200000001' } });
    assert.equal(r.status, 500);
    assert.ok(!JSON.stringify(r.data).includes('rahasia'));
  } finally { await s.tutup(); }
});

test('GET dan DELETE /sesi/:nomor', async () => {
  const dipanggil = [];
  const s = await jalankan(managerPalsu({ putuskan: async n => dipanggil.push(n) }));
  try {
    assert.deepEqual((await s.panggil('/sesi/6281200000001')).data, { status: 'tersambung' });
    assert.deepEqual((await s.panggil('/sesi/6281200000009')).data, { status: 'tidak_ada' });
    assert.deepEqual((await s.panggil('/sesi/6281200000001', { metode: 'DELETE' })).data, { ok: true });
    assert.deepEqual(dipanggil, ['6281200000001']);
  } finally { await s.tutup(); }
});

test('POST /sesi/:nomor/pesan mengirim pesan', async () => {
  const dikirim = [];
  const s = await jalankan(managerPalsu({ kirim: async (n, t, i) => { dikirim.push([n, t, i]); return 'WAMID-9'; } }));
  try {
    const r = await s.panggil('/sesi/6281200000001/pesan', { metode: 'POST', body: { telepon: '0857-1111-0001', isi: 'Halo!' } });
    assert.equal(r.status, 200);
    assert.deepEqual(r.data, { id_pesan: 'WAMID-9' });
    assert.deepEqual(dikirim, [['6281200000001', '6285711110001', 'Halo!']]);
  } finally { await s.tutup(); }
});

test('POST pesan: validasi telepon dan isi', async () => {
  const s = await jalankan(managerPalsu());
  try {
    const kirim = body => s.panggil('/sesi/6281200000001/pesan', { metode: 'POST', body });
    assert.equal((await kirim({ telepon: '12', isi: 'Halo' })).status, 422);
    assert.equal((await kirim({ telepon: '6285711110001', isi: '' })).status, 422);
    assert.equal((await kirim({ telepon: '6285711110001', isi: '   ' })).status, 422);
    assert.equal((await kirim({ telepon: '6285711110001', isi: 'x'.repeat(5001) })).status, 422);
    assert.equal((await kirim({ telepon: '6285711110001' })).status, 422);
  } finally { await s.tutup(); }
});

test('rute tidak dikenal 404; badan terlalu besar 413', async () => {
  const s = await jalankan(managerPalsu());
  try {
    assert.equal((await s.panggil('/tidak-ada')).status, 404);
    assert.equal((await s.panggil('/sesi', { metode: 'POST', mentah: JSON.stringify({ nomor: 'x'.repeat(70000) }) })).status, 413);
  } finally { await s.tutup(); }
});
