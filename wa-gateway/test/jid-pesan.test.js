import assert from 'node:assert/strict';
import test from 'node:test';
import { normalisasiTelepon, teleponValid, jidKeTelepon, resolveTelepon, abaikanJid } from '../src/jid.js';
import { ekstrakPesan } from '../src/pesan.js';

test('normalisasiTelepon menyeragamkan ke 62', () => {
  assert.equal(normalisasiTelepon('0812-3456-7890'), '6281234567890');
  assert.equal(normalisasiTelepon('+62 812 3456 7890'), '6281234567890');
  assert.equal(normalisasiTelepon('6281234567890'), '6281234567890');
});

test('teleponValid mengikuti aturan Laravel', () => {
  assert.ok(teleponValid('6281234567890'));
  assert.ok(!teleponValid('123'));
  assert.ok(!teleponValid('081234567890'));
  assert.ok(!teleponValid('62abc'));
});

test('jidKeTelepon membuang akhiran perangkat dan menolak jid non-pribadi', () => {
  assert.equal(jidKeTelepon('6281234567890@s.whatsapp.net'), '6281234567890');
  assert.equal(jidKeTelepon('6281234567890:12@s.whatsapp.net'), '6281234567890');
  assert.equal(jidKeTelepon('1234567890@lid'), null);
  assert.equal(jidKeTelepon('abc@s.whatsapp.net'), null);
  assert.equal(jidKeTelepon(undefined), null);
});

test('abaikanJid: grup, status, siaran, kanal', () => {
  for (const j of ['1203@g.us', 'status@broadcast', '1@newsletter', undefined]) assert.ok(abaikanJid(j), j);
  assert.ok(!abaikanJid('6281@s.whatsapp.net'));
  assert.ok(!abaikanJid('99@lid'));
});

test('resolveTelepon: jid nomor telepon langsung', async () => {
  assert.equal(await resolveTelepon({ remoteJid: '6281234567890@s.whatsapp.net' }), '6281234567890');
});

test('resolveTelepon: LID dipetakan lewat remoteJidAlt', async () => {
  const key = { remoteJid: '99887766@lid', remoteJidAlt: '6281234567890@s.whatsapp.net' };
  assert.equal(await resolveTelepon(key), '6281234567890');
});

test('resolveTelepon: LID tanpa Alt memakai pemetaan LID', async () => {
  const key = { remoteJid: '99887766@lid' };
  const cari = async lid => (lid === '99887766@lid' ? '6281234567890:5@s.whatsapp.net' : null);
  assert.equal(await resolveTelepon(key, { cariPnDariLid: cari }), '6281234567890');
});

test('resolveTelepon: LID yang tak bisa dipetakan -> null (bukan menebak)', async () => {
  assert.equal(await resolveTelepon({ remoteJid: '99887766@lid' }), null);
  assert.equal(await resolveTelepon({ remoteJid: '99887766@lid' }, { cariPnDariLid: async () => null }), null);
  assert.equal(await resolveTelepon({ remoteJid: '99887766@lid' }, { cariPnDariLid: async () => { throw new Error('x'); } }), null);
});

test('resolveTelepon: grup dan status diabaikan', async () => {
  assert.equal(await resolveTelepon({ remoteJid: '1203@g.us' }), null);
  assert.equal(await resolveTelepon({ remoteJid: 'status@broadcast' }), null);
});

test('ekstrakPesan: teks biasa dan teks panjang', () => {
  assert.equal(ekstrakPesan({ conversation: 'Halo' }), 'Halo');
  assert.equal(ekstrakPesan({ extendedTextMessage: { text: 'Balasan' } }), 'Balasan');
});

test('ekstrakPesan: membuka pembungkus (ephemeral, view once)', () => {
  assert.equal(ekstrakPesan({ ephemeralMessage: { message: { conversation: 'rahasia' } } }), 'rahasia');
  assert.equal(ekstrakPesan({ viewOnceMessage: { message: { imageMessage: { caption: 'foto' } } } }), '[Gambar] foto');
});

test('ekstrakPesan: pesan non-teks diberi label', () => {
  assert.equal(ekstrakPesan({ imageMessage: {} }), '[Gambar]');
  assert.equal(ekstrakPesan({ imageMessage: { caption: 'brosur' } }), '[Gambar] brosur');
  assert.equal(ekstrakPesan({ audioMessage: { ptt: true } }), '[Pesan suara]');
  assert.equal(ekstrakPesan({ documentMessage: { fileName: 'rapor.pdf' } }), '[Dokumen] rapor.pdf');
  assert.equal(ekstrakPesan({ locationMessage: {} }), '[Lokasi]');
});

test('ekstrakPesan: reaksi, protokol, dan pesan kosong diabaikan', () => {
  assert.equal(ekstrakPesan({ reactionMessage: { text: '👍' } }), null);
  assert.equal(ekstrakPesan({ protocolMessage: {} }), null);
  assert.equal(ekstrakPesan({ conversation: '   ' }), null);
  assert.equal(ekstrakPesan(undefined), null);
});
