import fsp from 'node:fs/promises';
import path from 'node:path';
import { normalisasiTelepon } from './jid.js';

export class GatewayError extends Error {
  constructor(pesan, status = 500) {
    super(pesan);
    this.name = 'GatewayError';
    this.status = status;
  }
}

/**
 * Mengelola satu sesi WhatsApp per nomor: QR, verifikasi nomor yang dipindai,
 * sambung ulang, dan penerusan pesan masuk ke Laravel.
 *
 * Seluruh kode Baileys ada di pabrikKoneksi (lihat baileys-socket.js).
 * Kontrak pabrikKoneksi(nomor, handlers) -> Promise<Koneksi>:
 *   handlers: { qr(qr), terbuka({ telepon }), tertutup({ kode, loggedOut,
 *               restartRequired, replaced, alasan }), pesanMasuk({ dari, nama, isi, idPesan }) }
 *   Koneksi:  { kirim(telepon, isi) -> idPesan, logout(), tutup() }
 *   Galat kirim dengan .kode === 'TIDAK_TERDAFTAR' berarti nomor tujuan bukan pengguna WhatsApp.
 */
export function buatManagerSesi({
  pabrikKoneksi,
  webhook,
  authDir,
  logger,
  qrTimeoutMs = 20000,
  backoff = { dasar: 1000, maks: 30000, gagalLaporan: 5 },
  jadwal = setTimeout,
  batal = clearTimeout,
  sekarang = Date.now,
  // QR yang lebih tua dari ini tidak dipakai ulang: WhatsApp memutar QR tiap ~20
  // detik setelah QR pertama, jadi QR tersimpan bisa tinggal beberapa detik lagi.
  masaQrSegarMs = 5000,
  fs = fsp,
}) {
  /** @type {Map<string, any>} */
  const sesi = new Map();

  // start() menunggu operasi berkas sebelum sesi terdaftar di peta, sehingga dua
  // panggilan hampir bersamaan bisa sama-sama lolos. Proses yang sedang berjalan
  // dicatat di sini secara sinkron agar panggilan kedua ikut menunggu yang pertama.
  const sedangMulai = new Map();

  const dirSesi = nomor => path.join(authDir, nomor);

  // Satu handler yang melempar galat tidak boleh menjatuhkan seluruh proses.
  const aman = fn => (...args) =>
    Promise.resolve()
      .then(() => fn(...args))
      .catch(e => logger.error({ err: e.message }, 'handler sesi gagal'));

  async function sudahTertaut(dir) {
    try {
      const creds = JSON.parse(await fs.readFile(path.join(dir, 'creds.json'), 'utf8'));
      return Boolean(creds?.me);
    } catch {
      return false;
    }
  }

  function buatSesi(nomor, terdaftar) {
    const s = {
      nomor,
      gen: 0,
      status: 'memulai',
      qr: null,
      koneksi: null,
      terdaftar,
      berhenti: false,
      gagalBeruntun: 0,
      dilaporkanTerputus: false,
      timerUlang: null,
      mulai: null,
      selesaiMulai: null,
    };
    sesi.set(nomor, s);
    return s;
  }

  function selesaikanMulai(s, hasil, galat) {
    if (!s.selesaiMulai) return false;
    const { resolve, reject, timer } = s.selesaiMulai;
    batal(timer);
    s.selesaiMulai = null;
    s.mulai = null;
    galat ? reject(galat) : resolve(hasil);
    return true;
  }

  async function hapusSesi(s, { hapusAuth, logout = false }) {
    s.berhenti = true;
    batal(s.timerUlang);
    const koneksi = s.koneksi;
    s.koneksi = null;

    if (koneksi) {
      if (logout) await Promise.resolve(koneksi.logout()).catch(() => {});
      try {
        koneksi.tutup();
      } catch {
        /* sudah tertutup */
      }
    }

    if (sesi.get(s.nomor) === s) sesi.delete(s.nomor);
    if (hapusAuth) await fs.rm(dirSesi(s.nomor), { recursive: true, force: true });
  }

  async function sambungkan(s) {
    const gen = ++s.gen;
    const hanyaIni = fn => aman((...args) => (s.gen === gen && !s.berhenti ? fn(...args) : undefined));

    const handlers = {
      qr: hanyaIni(async qr => {
        s.status = 'menunggu_qr';
        s.qr = qr;
        s.qrDiterimaPada = sekarang();
        // QR pertama dikembalikan lewat respons start(); QR berikutnya (WhatsApp
        // memutarnya tiap ~20 detik) diteruskan ke Laravel agar tampilan ikut berganti.
        if (!selesaikanMulai(s, { status: 'qr', qr })) {
          await webhook.sesi({ nomor: s.nomor, status: 'qr', qr });
        }
      }),

      terbuka: hanyaIni(async ({ telepon }) => {
        // Nomor yang dipindai harus sama dengan nomor yang didaftarkan; kalau
        // tidak, kartu "Admisi" bisa terhubung ke ponsel orang lain.
        if (normalisasiTelepon(telepon) !== s.nomor) {
          const alasan = `Nomor yang dipindai (${normalisasiTelepon(telepon)}) berbeda dengan nomor terdaftar (${s.nomor}).`;
          logger.warn({ nomor: s.nomor }, 'nomor yang dipindai tidak cocok, sesi ditolak');
          await hapusSesi(s, { hapusAuth: true, logout: true });
          if (!selesaikanMulai(s, null, new GatewayError(alasan, 409))) {
            await webhook.sesi({ nomor: s.nomor, status: 'gagal', alasan });
          }
          return;
        }

        s.status = 'tersambung';
        s.qr = null;
        s.terdaftar = true;
        s.gagalBeruntun = 0;
        s.dilaporkanTerputus = false;
        selesaikanMulai(s, { status: 'tersambung' });
        logger.info({ nomor: s.nomor }, 'sesi tersambung');
        await webhook.sesi({ nomor: s.nomor, status: 'tersambung' });
      }),

      tertutup: hanyaIni(async info => {
        s.koneksi = null;

        // WhatsApp meminta koneksi dimulai ulang setelah QR dipindai; ini normal.
        if (info.restartRequired) return sambungkan(s);

        if (info.loggedOut) {
          logger.info({ nomor: s.nomor }, 'sesi keluar dari ponsel');
          await hapusSesi(s, { hapusAuth: true });
          await webhook.sesi({ nomor: s.nomor, status: 'terputus', alasan: 'Perangkat dikeluarkan dari ponsel.' });
          return;
        }

        if (info.replaced) {
          // Sesi lain memakai kredensial yang sama; menyambung lagi hanya akan saling mengusir.
          await hapusSesi(s, { hapusAuth: false });
          await webhook.sesi({ nomor: s.nomor, status: 'terputus', alasan: 'Sesi digantikan perangkat lain.' });
          return;
        }

        // Fase QR berakhir tanpa dipindai: bersihkan, Laravel menampilkan "QR kedaluwarsa".
        if (!s.terdaftar) {
          selesaikanMulai(s, null, new GatewayError(info.alasan ?? 'Koneksi ditutup sebelum QR dipindai.', 502));
          await hapusSesi(s, { hapusAuth: true });
          return;
        }

        // Putus sementara: sambung ulang dengan jeda yang makin panjang.
        s.status = 'menyambung';
        s.gagalBeruntun += 1;

        if (s.gagalBeruntun >= backoff.gagalLaporan && !s.dilaporkanTerputus) {
          s.dilaporkanTerputus = true;
          await webhook.sesi({ nomor: s.nomor, status: 'terputus', alasan: info.alasan ?? 'Koneksi terputus.' });
        }

        const jeda = Math.min(backoff.maks, backoff.dasar * 2 ** (s.gagalBeruntun - 1));
        logger.warn({ nomor: s.nomor, jeda, kode: info.kode }, 'koneksi terputus, menyambung ulang');
        s.timerUlang = jadwal(() => {
          sambungkan(s).catch(e => logger.error({ err: e.message }, 'sambung ulang gagal'));
        }, jeda);
      }),

      pesanMasuk: hanyaIni(async ({ dari, nama, isi, idPesan }) => {
        const badan = { nomor: s.nomor, dari, nama, isi, id_pesan: idPesan };
        let res = await webhook.pesan(badan);

        // 409 berarti Laravel mengira nomor ini belum tersambung (misalnya basis
        // datanya direset). Gateway adalah sumber kebenaran status sesi, jadi
        // selaraskan sekali lalu coba lagi.
        if (res.status === 409) {
          await webhook.sesi({ nomor: s.nomor, status: 'tersambung' });
          res = await webhook.pesan(badan);
        }

        if (!res.ok) logger.warn({ nomor: s.nomor, status: res.status, idPesan }, 'pesan masuk ditolak Laravel');
      }),
    };

    s.koneksi = await pabrikKoneksi(s.nomor, handlers);
  }

  async function mulaiSesi(nomor) {
    let s = sesi.get(nomor);

    if (s?.status === 'tersambung') return { status: 'tersambung' };
    if (s?.mulai) return s.mulai;
    // Hanya QR yang masih segar dipakai ulang (mis. dua klik beruntun). QR usang
    // berarti pengguna menekan "Buat ulang kode": mulai sesi baru agar QR-nya
    // kembali berumur penuh, sesuai hitung mundur yang tampil di layar.
    if (s?.status === 'menunggu_qr' && s.qr && sekarang() - s.qrDiterimaPada <= masaQrSegarMs) {
      return { status: 'qr', qr: s.qr };
    }
    if (s) await hapusSesi(s, { hapusAuth: false });

    // Folder lama yang belum pernah tertaut (QR tak dipindai) dibuang agar mulai bersih.
    if (!(await sudahTertaut(dirSesi(nomor)))) {
      await fs.rm(dirSesi(nomor), { recursive: true, force: true });
    }

    s = buatSesi(nomor, await sudahTertaut(dirSesi(nomor)));
    s.mulai = new Promise((resolve, reject) => {
      const timer = jadwal(() => {
        selesaikanMulai(s, null, new GatewayError('Gateway tidak menerima QR dari WhatsApp tepat waktu.', 504));
        hapusSesi(s, { hapusAuth: !s.terdaftar }).catch(() => {});
      }, qrTimeoutMs);
      s.selesaiMulai = { resolve, reject, timer };
    });
    const menunggu = s.mulai;

    try {
      await sambungkan(s);
    } catch (e) {
      selesaikanMulai(s, null, new GatewayError(`Gagal memulai sesi: ${e.message}`, 502));
      await hapusSesi(s, { hapusAuth: false });
    }

    return menunggu;
  }

  return {
    /** Memulai sesi baru. Mengembalikan { status: 'qr', qr } atau { status: 'tersambung' }. */
    start(nomor) {
      if (sedangMulai.has(nomor)) return sedangMulai.get(nomor);

      const proses = mulaiSesi(nomor).finally(() => sedangMulai.delete(nomor));
      sedangMulai.set(nomor, proses);
      return proses;
    },

    async kirim(nomor, telepon, isi) {
      const s = sesi.get(nomor);
      if (!s || s.status !== 'tersambung' || !s.koneksi) {
        throw new GatewayError('Nomor WhatsApp tidak tersambung di gateway.', 409);
      }

      try {
        return await s.koneksi.kirim(telepon, isi);
      } catch (e) {
        if (e.kode === 'TIDAK_TERDAFTAR') {
          throw new GatewayError('Nomor tujuan tidak terdaftar di WhatsApp.', 422);
        }
        throw new GatewayError(`Gagal mengirim pesan: ${e.message}`, 502);
      }
    },

    /** Memutus sesi atas permintaan Laravel; tidak memanggil balik webhook. */
    async putuskan(nomor) {
      const s = sesi.get(nomor);
      if (s) await hapusSesi(s, { hapusAuth: true, logout: true });
      else await fs.rm(dirSesi(nomor), { recursive: true, force: true });
    },

    status(nomor) {
      return sesi.get(nomor)?.status ?? 'tidak_ada';
    },

    jumlahSesi: () => sesi.size,

    /** Menyambungkan kembali semua sesi yang sudah tertaut (dipanggil saat gateway menyala). */
    async pulihkan() {
      let daftar;
      try {
        daftar = await fs.readdir(authDir, { withFileTypes: true });
      } catch {
        return [];
      }

      const dipulihkan = [];
      for (const d of daftar) {
        if (!d.isDirectory() || !/^62\d{8,13}$/.test(d.name)) continue;
        if (!(await sudahTertaut(dirSesi(d.name)))) continue;

        const s = buatSesi(d.name, true);
        s.status = 'menyambung';
        await sambungkan(s).catch(e => logger.error({ nomor: d.name, err: e.message }, 'gagal memulihkan sesi'));
        dipulihkan.push(d.name);
      }
      return dipulihkan;
    },

    /** Mematikan semua koneksi tanpa menghapus kredensial (saat gateway berhenti). */
    tutupSemua() {
      for (const s of sesi.values()) {
        s.berhenti = true;
        batal(s.timerUlang);
        try {
          s.koneksi?.tutup();
        } catch {
          /* abaikan */
        }
      }
      sesi.clear();
    },
  };
}
