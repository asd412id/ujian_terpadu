import './bootstrap';
import Alpine from 'alpinejs';
import Persist from '@alpinejs/persist';
import { ujianApp } from './ujian';
import { richEditor } from './rich-editor';

// Register Alpine plugins
Alpine.plugin(Persist);

// Register Alpine components
Alpine.data('ujianApp', ujianApp);
Alpine.data('richEditor', richEditor);

// Global Alpine Store: Sidebar
Alpine.store('sidebar', {
    open: false,
    toggle() { this.open = !this.open; }
});

// Global Alpine Store: Toast notifications
Alpine.store('toast', {
    messages: [],
    show(msg, type = 'success', duration = 4000) {
        const id = Date.now();
        this.messages.push({ id, msg, type });
        setTimeout(() => this.dismiss(id), duration);
    },
    dismiss(id) {
        this.messages = this.messages.filter(m => m.id !== id);
    }
});

// Start Alpine
window.Alpine = Alpine;
Alpine.start();
