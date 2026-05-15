import { Controller } from '@hotwired/stimulus';

/*
 * Rend une ligne de tableau cliquable vers une URL (généralement la page d'édition).
 * Les clics sur un élément interactif (lien, bouton, champ, formulaire) conservent
 * leur comportement natif et ne déclenchent pas la navigation de la ligne.
 *
 * Markup attendu :
 *   <tr data-controller="row-link"
 *       data-row-link-url-value="{{ path('admin.product.edit', { id: product.id }) }}">
 *     ...
 *   </tr>
 *
 * Ctrl/Cmd + clic ou clic milieu : ouverture dans un nouvel onglet.
 */
const SKIP_SELECTOR = 'a, button, input, select, textarea, label, form';

export default class extends Controller {
    static values = { url: String };

    connect() {
        this.element.classList.add('cursor-pointer');
        this._onClick = this._onClick.bind(this);
        this.element.addEventListener('click', this._onClick);
        this.element.addEventListener('auxclick', this._onClick);
    }

    disconnect() {
        this.element.removeEventListener('click', this._onClick);
        this.element.removeEventListener('auxclick', this._onClick);
    }

    _onClick(event) {
        if (!this.urlValue) return;
        if (event.target.closest(SKIP_SELECTOR)) return;

        if (event.metaKey || event.ctrlKey || event.button === 1) {
            window.open(this.urlValue, '_blank');
            return;
        }

        if (event.button !== 0) return;

        window.location.href = this.urlValue;
    }
}
