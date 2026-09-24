import './bootstrap';

import Alpine from 'alpinejs';
import { registerAdminAlpine } from './admin-alpine';
import { initAdminScroll } from './admin-scroll';

window.Alpine = Alpine;

registerAdminAlpine(Alpine);

Alpine.start();

initAdminScroll();
