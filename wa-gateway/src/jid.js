// Fungsi murni untuk nomor telepon dan JID WhatsApp (tanpa dependensi Baileys).

/** "0812-3456-7890" -> "6281234567890" (sama dengan App\Support\Telepon di Laravel). */
export function normalisasiTelepon(nomor) {
  const angka = String(nomor ?? '').replace(/\D+/g, '');
  return angka.startsWith('0') ? '62' + angka.slice(1) : angka;
}

/** Aturan yang sama dengan validasi nomor tujuan di Laravel. */
export function teleponValid(telepon) {
  return /^62\d{8,13}$/.test(telepon);
}

export const adalahJidPribadi = jid => typeof jid === 'string' && jid.endsWith('@s.whatsapp.net');
export const adalahLid = jid => typeof jid === 'string' && jid.endsWith('@lid');

/** Grup, status, siaran, dan kanal tidak diteruskan ke Laravel. */
export function abaikanJid(jid) {
  return (
    typeof jid !== 'string' ||
    jid.endsWith('@g.us') ||
    jid.endsWith('@broadcast') ||
    jid.endsWith('@newsletter')
  );
}

/** "6281234567890:12@s.whatsapp.net" -> "6281234567890" (buang akhiran perangkat). */
export function jidKeTelepon(jid) {
  if (!adalahJidPribadi(jid)) return null;
  const pengguna = jid.split('@')[0].split(':')[0];
  return /^\d+$/.test(pengguna) ? pengguna : null;
}

/**
 * Menentukan nomor telepon pengirim dari kunci pesan Baileys.
 * Baileys v7 dapat memakai JID "@lid" (identitas anonim) sebagai pengganti
 * nomor telepon; nomornya diambil dari remoteJidAlt atau pemetaan LID.
 * Mengembalikan null bila nomor tidak bisa dipastikan.
 */
export async function resolveTelepon(key, { cariPnDariLid } = {}) {
  const utama = key?.remoteJid;
  if (abaikanJid(utama)) return null;

  if (adalahJidPribadi(utama)) return jidKeTelepon(utama);

  if (adalahLid(utama)) {
    if (adalahJidPribadi(key.remoteJidAlt)) return jidKeTelepon(key.remoteJidAlt);

    if (cariPnDariLid) {
      const pn = await Promise.resolve(cariPnDariLid(utama)).catch(() => null);
      if (adalahJidPribadi(pn)) return jidKeTelepon(pn);
    }
  }

  return null;
}
