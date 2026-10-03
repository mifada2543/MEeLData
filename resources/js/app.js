import './bootstrap';

import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import registerLive from './live';

// Focus trap untuk modal: keyboard user tidak dapat keluar dari dialog
// yang terbuka (design.md §23 accessibility).
Alpine.plugin(focus);

// Store 'live', counter "sudah lewat …", dan polling waktu server.
registerLive(Alpine);

/**
 * Jam aplikasi yang realtime.
 *
 * Diberi nilai awal dari server (timezone & waktu server), lalu di-tick
 * setiap detik di sisi klien. Tidak ada request AJAX per detik: cukup satu
 * render SSR untuk determinations timezone, sisanya murni klien.
 *
 * Format jam dirakit dari Intl.formatToParts supaya separator menit
 * selalu ":" - locale id-ID dengan hour12:false menghasilkan "11.52".
 */
Alpine.data('clock', () => {
    return {
        date: '',
        time: '',
        timer: null,

        /**
         * `this.$el` dipakai (bukan parameter factory) karena Alpine v3
         * tidak mengirim elemen ke provider `Alpine.data`.
         */
        init() {
            const el = this.$el;
            const timezone = el.dataset.timezone || 'Asia/Jakarta';

            const dateFormatter = new Intl.DateTimeFormat('id-ID', {
                timeZone: timezone,
                day: '2-digit',
                month: 'short',
                year: 'numeric',
            });

            const timeFormatter = new Intl.DateTimeFormat('id-ID', {
                timeZone: timezone,
                hour: '2-digit',
                minute: '2-digit',
                hourCycle: 'h23',
            });

            // Separator menit dirakit manual: locale id-ID dengan
            // hour12:false menghasilkan "11.52", bukan "11:52".
            const formatTime = (date) => {
                const parts = {};

                for (const part of timeFormatter.formatToParts(date)) {
                    if (part.type === 'hour' || part.type === 'minute') {
                        parts[part.type] = part.value;
                    }
                }

                return `${parts.hour}:${parts.minute}`;
            };

            this.tick = () => {
                const now = new Date();
                this.date = dateFormatter.format(now);
                this.time = formatTime(now);
            };

            this.tick();
            this.timer = setInterval(this.tick, 1000);
        },

        destroy() {
            if (this.timer) clearInterval(this.timer);
        },
    };
});

window.Alpine = Alpine;

Alpine.start();