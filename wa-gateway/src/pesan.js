// Mengambil teks dari pesan Baileys. Pesan non-teks diberi label singkat
// agar agen tetap tahu pelanggan mengirim sesuatu.

const PEMBUNGKUS = [
  'ephemeralMessage',
  'viewOnceMessage',
  'viewOnceMessageV2',
  'viewOnceMessageV2Extension',
  'documentWithCaptionMessage',
  'editedMessage',
];

function buka(message) {
  let isi = message;
  for (let i = 0; i < 5 && isi; i++) {
    const kunci = PEMBUNGKUS.find(k => isi[k]?.message);
    if (!kunci) break;
    isi = isi[kunci].message;
  }
  return isi;
}

const denganKeterangan = (label, keterangan) => (keterangan ? `${label} ${keterangan}` : label);

/** Mengembalikan teks, atau null bila pesan bukan sesuatu yang perlu diteruskan. */
export function ekstrakPesan(message) {
  const m = buka(message);
  if (!m) return null;

  const teks = m.conversation ?? m.extendedTextMessage?.text;
  if (typeof teks === 'string' && teks.trim() !== '') return teks;

  if (m.imageMessage) return denganKeterangan('[Gambar]', m.imageMessage.caption);
  if (m.videoMessage) return denganKeterangan('[Video]', m.videoMessage.caption);
  if (m.audioMessage) return m.audioMessage.ptt ? '[Pesan suara]' : '[Audio]';
  if (m.documentMessage) return denganKeterangan('[Dokumen]', m.documentMessage.fileName);
  if (m.stickerMessage) return '[Stiker]';
  if (m.locationMessage) return '[Lokasi]';
  if (m.contactMessage || m.contactsArrayMessage) return '[Kontak]';

  // Reaksi, pesan protokol, dan jenis lain diabaikan.
  return null;
}
