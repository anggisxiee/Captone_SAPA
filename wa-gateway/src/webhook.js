const tidurBawaan = ms => new Promise(resolve => setTimeout(resolve, ms));

/**
 * Klien webhook ke Laravel. Gagal jaringan dan respons 5xx/429 dicoba ulang;
 * respons 4xx lain dikembalikan apa adanya (tidak ada gunanya diulang).
 */
export function buatKlienWebhook({
  baseUrl,
  token,
  logger,
  fetchFn = fetch,
  tidur = tidurBawaan,
  jeda = [500, 2000, 5000],
  batasWaktuMs = 10000,
}) {
  async function kirim(path, body) {
    for (let percobaan = 0; ; percobaan++) {
      try {
        const res = await fetchFn(baseUrl + path, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Gateway-Token': token,
          },
          body: JSON.stringify(body),
          signal: AbortSignal.timeout(batasWaktuMs),
        });
        const data = await res.json().catch(() => ({}));

        if (res.ok) return { ok: true, status: res.status, data };
        if (res.status === 429 || res.status >= 500) throw new Error(`HTTP ${res.status}`);

        return { ok: false, status: res.status, data };
      } catch (e) {
        if (percobaan >= jeda.length) {
          logger.error({ path, err: e.message }, 'webhook Laravel gagal setelah percobaan ulang');
          return { ok: false, status: 0, data: { pesan: e.message } };
        }
        logger.warn({ path, err: e.message, percobaan: percobaan + 1 }, 'webhook Laravel gagal, mencoba ulang');
        await tidur(jeda[percobaan]);
      }
    }
  }

  return {
    pesan: body => kirim('/pesan', body),
    sesi: body => kirim('/sesi', body),
  };
}
