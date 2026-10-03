/**
 * Tampilan "live" untuk daftar tugas: hitungan mundur "sudah lewat …"
 * berdasarkan waktu server, plus indikator scheduler yang mati.
 *
 * Prinsip:
 * 1. Waktu server adalah acuan. Client hanya menghitung selisih
 *    (`offsetMs = serverTime - clientTime`) lalu men-tick dengan jam
 *    perangkat + offset. Akibatnya counter tetap benar walau jam perangkat
 *    salah, dan TIDAK melompat atau ke-reset saat halaman di-refresh -
 *    setiap render halaman mengkalibrasi ulang offset dari `server_time`.
 * 2. Polling hanya mengambil acuan waktu + status task (default 10 detik),
 *    bukan satu request per detik. Di antara dua polling counter tetap
 *    bergerak tiap detik di sisi klien.
 * 3. Bila Ajax gagal, counter yang sudah dirender server tetap tampil.
 */

/** Hitung mundur "sudah lewat" — detik dulu, lalu menit, jam, hari. */
function formatShort(ms) {
    const total = Math.max(0, Math.floor(ms / 1000));
    const s = total % 60;
    const m = Math.floor(total / 60) % 60;
    const h = Math.floor(total / 3600) % 24;
    const d = Math.floor(total / 86400);

    if (d > 0) return `${d} hari ${h} jam`;
    if (h > 0) return `${h} jam ${m} menit`;
    if (m > 0) return `${m} menit ${s} detik`;

    return `${s} detik`;
}

/** Hitung mundur "mm:ss" untuk waktu menuju penandaan `late`. */
function formatCountdown(ms) {
    // `ceil` supaya "1 detik" tidak langsung tampil "0:00" sebelum habis.
    const total = Math.max(0, Math.ceil(ms / 1000));
    const s = total % 60;
    const m = Math.floor(total / 60);

    return `${m}:${String(s).padStart(2, '0')}`;
}

/**
 * Sisa waktu menuju deadline, untuk task yang belum lewat.
 *
 * nearing deadline (mendekati) format presisi `mm:ss` supaya terasa hidup;
 * jarak jauh cukup hari/jam - detik tidak ada artinya di sana.
 */
function formatUntil(ms) {
    const total = Math.max(0, Math.floor(ms / 1000));

    if (total <= 3600) {
        return formatCountdown(ms);
    }

    const m = Math.floor(total / 60) % 60;
    const h = Math.floor(total / 3600) % 24;
    const d = Math.floor(total / 86400);

    if (d > 0) return `${d} hari ${h} jam`;

    return `${h} jam ${m} menit`;
}

/** Waktu berlalu untuk halaman Sampah — detik terlalu bising, jadi dibulatkan. */
function formatLong(ms) {
    const total = Math.max(0, Math.floor(ms / 1000));
    const m = Math.floor(total / 60) % 60;
    const h = Math.floor(total / 3600) % 24;
    const d = Math.floor(total / 86400);

    if (d > 0) return `${d} hari ${h} jam`;
    if (h > 0) return `${h} jam ${m} menit`;
    if (m > 0) return `${m} menit`;

    return `${Math.floor(total / 60)} menit`;
}

export default function registerLive(Alpine) {
    Alpine.store('live', {
        offsetMs: 0,
        overdue: {},
        // Id yang PERNAH terlihat overdue. Wajib ada: `overdue` hanya berisi
        // task progress yang deadline-nya lewat, jadi task berdeadline depan
        // juga tidak ada di sana - tanpa penanda ini, ticker-nya akan
        // mengira dirinya sudah `late` begitu polling pertama selesai.
        seen: {},
        down: 0,
        markAfter: 120,
        polled: false,
        timer: null,

        sync(serverNowIso, payload, initialOverdueIds) {
            this.offsetMs = new Date(serverNowIso).getTime() - Date.now();

            if (Array.isArray(initialOverdueIds)) {
                initialOverdueIds.forEach((id) => {
                    this.seen[id] = true;
                });
            }

            if (!payload) return;

            this.markAfter = payload.mark_after_seconds ?? this.markAfter;
            this.down = payload.scheduler_down_count ?? 0;

            const map = {};
            (payload.overdue ?? []).forEach((row) => {
                map[row.id] = row.deadline;
                this.seen[row.id] = true;
            });
            this.overdue = map;
            this.polled = true;
        },

        isOverdue(id) {
            return Object.prototype.hasOwnProperty.call(this.overdue, id);
        },

        wasOverdue(id) {
            return Object.prototype.hasOwnProperty.call(this.seen, id);
        },

        now() {
            return Date.now() + this.offsetMs;
        },
    });

    /**
     * Counter pada satu baris task `progress`.
     *
     * Tiga tampilan, semuanya dihitung dari waktu server:
     *
     * 1. `sisa 14:32`  — deadline belum lewat. **Hitung mundur menuju
     *    deadline.** Inilah yang paling sering dilihat user, dan membuat
     *    hitung mundur benar-benar berguna: tanpa ini, jendela 120 detik
     *    hampir tidak pernah terlihat karena user membuka daftar jauh
     *    setelah deadline lewat.
     * 2. `dalam 0:45`  — deadline sudah lewat tapi masih di dalam jendela
     *    penandaan. Hitung mundur menuju scheduler menandai `late`.
     * 3. `terlambat 4 menit` — jendela habis tapi masih `progress`, artinya
     *    scheduler tidak jalan. Angka `0:00` yang membeku tidak pernah
     *    ditampilkan karena menyesatkan.
     *
     * @param deadlineIso deadline task (waktu server)
     * @param serverNowIso waktu server saat halaman dirender
     * @param shortFormat true = task aktif, false = durasi panjang (Sampah)
     */
    Alpine.data('deadlineTicker', (deadlineIso, serverNowIso, shortFormat) => ({
        deadline: new Date(deadlineIso).getTime(),
        text: '…',
        done: false,
        timer: null,
        format: shortFormat ? formatShort : formatLong,

        init() {
            const store = Alpine.store('live');

            // Kalibrasi awal memakai waktu server hasil render. Kalau polling
            // sudah sempat berjalan, JANGAN menimpa offset dengan nilai render
            // yang lebih lama - itu bisa membuat counter melompat mundur.
            if (!store.polled) {
                store.sync(serverNowIso, null);
            }

            this.tick();
            this.timer = setInterval(() => this.tick(), 1000);
        },

        tick() {
            const store = Alpine.store('live');
            const id = this.$el.dataset.taskId;

            // Task ini PERNAH terlihat overdue, lalu sekarang tidak lagi =>
            // scheduler sudah menandainya `late`. Tampilkan badge, hentikan
            // counter.
            //
            // Syarat `wasOverdue(id)` itu wajib: task berdeadline depan juga
            // tidak ada di daftar `overdue`, dan tanpa syarat ini semuanya
            // akan berubah jadi badge `Terlambat` begitu polling pertama selesai.
            if (store.polled && store.wasOverdue(id) && !store.isOverdue(id)) {
                this.finish();
                return;
            }

            const now = store.now();

            if (!shortFormat) {
                this.text = this.format(now - this.deadline);

                return;
            }

            const markAt = this.deadline + store.markAfter * 1000;

            if (now < this.deadline) {
                this.text = `sisa ${formatUntil(this.deadline - now)}`;
            } else if (now < markAt) {
                this.text = `dalam ${formatCountdown(markAt - now)}`;
            } else {
                this.text = `terlambat ${this.format(now - this.deadline)}`;
            }
        },

        finish() {
            if (this.done) return;

            this.done = true;
            clearInterval(this.timer);
            window.dispatchEvent(
                new CustomEvent('live:task-late', { detail: { id: this.$el.dataset.taskId } }),
            );
        },

        destroy() {
            if (this.timer) clearInterval(this.timer);
        },
    }));

    /**
     * Polling. Dipanggil sekali per halaman lewat `x-init`.
     * Sengaja memakai `setInterval` biasa agar mudah dihentikan saat
     * Alpine melepas komponen.
     */
    window.startLive = function startLive(url, serverNowIso, initialOverdueIds) {
        const store = Alpine.store('live');

        // Seed daftar overdue dari render server, supaya task yang sedang
        // menunggu penandaan langsung dikenali sebagai `wasOverdue`.
        store.sync(serverNowIso, null, initialOverdueIds);

        const apply = (payload) => store.sync(payload.server_time, payload);

        const refresh = async () => {
            try {
                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                });

                if (!response.ok) return;

                apply(await response.json());
            } catch {
                // Diamkan: nilai yang sudah dirender server tetap tampil.
            }
        };

        // Poll pertama dijalankan segera supaya acuan server segera segar,
        // lalu mengikuti interval dari server.
        const kick = async () => {
            await refresh();

            const wait = Math.max(5, store.poll ?? 10) * 1000;
            store.timer = setInterval(refresh, wait);
        };

        kick();
    };
}