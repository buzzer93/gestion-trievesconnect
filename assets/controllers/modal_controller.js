import { Controller } from '@hotwired/stimulus';

/*
 * Modale accessible — remplace bootstrap.Modal.
 *
 * Markup attendu :
 *   <div data-controller="modal" id="someModal"
 *        class="hidden fixed inset-0 z-50 ...">
 *     <div data-modal-target="backdrop" data-action="click->modal#hideOnBackdrop"
 *          class="absolute inset-0 bg-black/70"></div>
 *     <div data-modal-target="dialog" role="dialog" aria-modal="true"
 *          class="relative ...">
 *       ...
 *       <button data-action="modal#hide">Fermer</button>
 *     </div>
 *   </div>
 *
 * Ouverture programmatique :
 *   document.getElementById('someModal').dispatchEvent(new CustomEvent('modal:open'));
 *
 * Ferme à Escape ou clic backdrop.
 */
export default class extends Controller {
    static targets = ['dialog', 'backdrop'];

    connect() {
        this._onKey = this._onKey.bind(this);
        this._onOpenEvent = this._onOpenEvent.bind(this);
        this._onCloseEvent = this._onCloseEvent.bind(this);
        document.addEventListener('keydown', this._onKey);
        this.element.addEventListener('modal:open', this._onOpenEvent);
        this.element.addEventListener('modal:close', this._onCloseEvent);
    }

    disconnect() {
        document.removeEventListener('keydown', this._onKey);
        this.element.removeEventListener('modal:open', this._onOpenEvent);
        this.element.removeEventListener('modal:close', this._onCloseEvent);
    }

    show() {
        this.element.classList.remove('hidden');
        this.element.classList.add('flex');
        document.body.style.overflow = 'hidden';
        this._previouslyFocused = document.activeElement;
        const focusable = this.hasDialogTarget
            ? this.dialogTarget.querySelector('input, button, [tabindex]:not([tabindex="-1"])')
            : null;
        focusable?.focus();
    }

    hide() {
        this.element.classList.add('hidden');
        this.element.classList.remove('flex');
        document.body.style.overflow = '';
        this._previouslyFocused?.focus?.();
    }

    hideOnBackdrop(event) {
        if (event.target === this.backdropTarget) {
            this.hide();
        }
    }

    _onOpenEvent(event) {
        event?.stopPropagation();
        this.show();
    }

    _onCloseEvent(event) {
        event?.stopPropagation();
        this.hide();
    }

    _onKey(event) {
        if (event.key === 'Escape' && !this.element.classList.contains('hidden')) {
            this.hide();
        }
    }
}
