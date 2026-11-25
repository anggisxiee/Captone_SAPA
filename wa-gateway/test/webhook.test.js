import assert from 'node:assert/strict';
import test from 'node:test';
import { buatKlienWebhook } from '../src/webhook.js';

const logger = { info() {}, warn() {}, error() {}, debug() {} };
const res = (status, data = {}) => ({ ok: status >= 200 && status < 300, status, json: async () => data });

function klien(urutan) {
  const panggilan = [];
  const fetchFn = async (url, opsi) => {
    panggilan.push({ url, opsi });
    const langkah = urutan.shift();
    if (langkah instanceof Error) throw langkah;
    return langkah;
  };
  const tidurDipanggil = [];
  const k = buatKlienWebhook({
    baseUrl: 'http://laravel/api/webhook/whatsapp',
    token: 'rahasia-rahasia',
    logger,
    fetchFn,
    tidur: async ms => tidurDipanggil.push(ms),
    jeda: [10, 20, 30],
  });
  return { k, panggilan, tidurDipanggil };
}

test('mengirim JSON ke path yang benar dengan header token', async () => {
  const { k, panggilan } = klien([res(201, { id: 5 })]);
  const hasil = await k.pesan({ nomor: '62', isi: 'Halo' });

  assert.deepEqual(hasil, { ok: true, status: 201, data: { id: 5 } });
  assert.equal(panggilan[0].url, 'http://laravel/api/webhook/whatsapp/pesan');
  assert.equal(panggilan[0].opsi.headers['X-Gateway-Token'], 'rahasia-rahasia');
  assert.deepEqual(JSON.parse(panggilan[0].opsi.body), { nomor: '62', isi: 'Halo' });
});

test('sesi dikirim ke /sesi', async () => {
  const { k, panggilan } = klien([res(200)]);
  await k.sesi({ nomor: '62', status: 'tersambung' });
  assert.ok(panggilan[0].url.endsWith('/sesi'));
});

test('galat jaringan dan 5xx dicoba ulang sampai berhasil', async () => {
  const { k, panggilan, tidurDipanggil } = klien([new Error('ECONNREFUSED'), res(503), res(201)]);
  const hasil = await k.pesan({});

  assert.ok(hasil.ok);
  assert.equal(panggilan.length, 3);
  assert.deepEqual(tidurDipanggil, [10, 20]);
});

test('4xx (selain 429) tidak diulang', async () => {
  const { k, panggilan } = klien([res(404, { pesan: 'Nomor tidak dikenal.' }), res(201)]);
  const hasil = await k.pesan({});

  assert.equal(hasil.ok, false);
  assert.equal(hasil.status, 404);
  assert.equal(panggilan.length, 1);
});

test('429 dicoba ulang', async () => {
  const { k, panggilan } = klien([res(429), res(200)]);
  assert.ok((await k.sesi({})).ok);
  assert.equal(panggilan.length, 2);
});

test('menyerah setelah percobaan habis dan tidak melempar galat', async () => {
  const { k, panggilan } = klien([res(500), res(500), res(500), res(500)]);
  const hasil = await k.pesan({});

  assert.deepEqual([hasil.ok, hasil.status], [false, 0]);
  assert.equal(panggilan.length, 4); // 1 awal + 3 ulang
});
