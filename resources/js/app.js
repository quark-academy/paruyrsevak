import './bootstrap';
import { initCatalogue } from './catalogue';
import { initFavoriteButtons, initFavorites } from './favorites';
import { lockPageZoom } from './gestures';
import { initSearchModal } from './search-modal';

lockPageZoom();
initCatalogue();
initFavorites();
initFavoriteButtons();
initSearchModal();
