import { Controller } from '@hotwired/stimulus';

/*
 * Fermeture d'un élément (alerte, flash) avec fade out court.
 *
 * Markup attendu :
 *   <div data-controller="dismissible">
 *     ...
 *     <button data-action="dismissible#dismiss">×</button>
 *   </div>
 */
export default class extends Controller {
    dismiss() {
        this.element.style.transition = 'opacity 200ms ease';
        this.element.style.opacity = '0';
        setTimeout(() => this.element.remove(), 200);
    }
}
