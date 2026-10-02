import './bootstrap';

import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';

// Focus trap untuk modal: keyboard user tidak dapat keluar dari dialog
// yang terbuka (design.md §23 accessibility).
Alpine.plugin(focus);

window.Alpine = Alpine;

Alpine.start();