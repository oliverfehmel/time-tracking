import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['sidebar', 'backdrop'];

    connect() {
        this.isOpen = false;
    }

    toggleSidebar() {
        this.isOpen ? this.closeSidebar() : this.openSidebar();
    }

    openSidebar() {
        this.isOpen = true;
        this.sidebarTarget.classList.add('is-open');
        this.backdropTarget.hidden = false;
        document.body.classList.add('no-scroll');
    }

    closeSidebar() {
        this.isOpen = false;
        this.sidebarTarget.classList.remove('is-open');
        this.backdropTarget.hidden = true;
        document.body.classList.remove('no-scroll');
    }
}
