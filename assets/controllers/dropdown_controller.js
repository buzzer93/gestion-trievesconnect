import { Controller } from '@hotwired/stimulus';

/*
 * Toggle générique pour menus dropdown / panels.
 *
 * Markup attendu :
 *   <div data-controller="dropdown" data-dropdown-open-class="block" data-dropdown-closed-class="hidden">
 *     <button data-action="dropdown#toggle" data-dropdown-target="button" aria-expanded="false">...</button>
 *     <div data-dropdown-target="menu" class="hidden">...</div>
 *   </div>
 *
 * Ferme automatiquement au clic à l'extérieur et à la touche Escape.
 */
export default class extends Controller {
    static targets = ['menu', 'button'];
    static classes = ['open', 'closed'];

    connect() {
        this._onDocClick = this._onDocClick.bind(this);
        this._onKey = this._onKey.bind(this);
        document.addEventListener('click', this._onDocClick);
        document.addEventListener('keydown', this._onKey);
    }

    disconnect() {
        document.removeEventListener('click', this._onDocClick);
        document.removeEventListener('keydown', this._onKey);
    }

    toggle(event) {
        event?.preventDefault();
        const isOpen = !this.menuTarget.classList.contains('hidden');
        isOpen ? this.close() : this.open();
    }

    open() {
        this.menuTarget.classList.remove('hidden');
        if (this.hasButtonTarget) {
            this.buttonTarget.setAttribute('aria-expanded', 'true');
        }
    }

    close() {
        this.menuTarget.classList.add('hidden');
        if (this.hasButtonTarget) {
            this.buttonTarget.setAttribute('aria-expanded', 'false');
        }
    }

    _onDocClick(event) {
        if (!this.element.contains(event.target)) {
            this.close();
        }
    }

    _onKey(event) {
        if (event.key === 'Escape') {
            this.close();
        }
    }
}
