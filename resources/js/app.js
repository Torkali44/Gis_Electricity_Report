import './bootstrap';

import Alpine from 'alpinejs';
import { registerAdminAlpine } from './admin-alpine';
import { registerSectionAnnotate } from './section-annotate';
import { initAdminScroll } from './admin-scroll';

window.Alpine = Alpine;

registerAdminAlpine(Alpine);
registerSectionAnnotate(Alpine);

Alpine.start();

initAdminScroll();
