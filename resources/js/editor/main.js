import '../../css/editor.css';
import Alpine from 'alpinejs';
import editor from './editor.js';
import richField from './rich.js';

Alpine.data('trameEditor', editor);
Alpine.data('richField', richField);

window.Alpine = Alpine;
Alpine.start();
