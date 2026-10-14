import './bootstrap';
import { initCatalogue } from './catalogue';
import { lockPageZoom } from './gestures';
import { initSearchModal } from './search-modal';

lockPageZoom();
initCatalogue();
initSearchModal();
