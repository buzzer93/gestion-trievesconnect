import { Controller } from '@hotwired/stimulus';

/*
 * Amélioration progressive du bouton toggle enabled/disabled : désactive
 * le bouton pendant la requête pour éviter un double-clic. Le formulaire
 * fonctionne intégralement sans JS (simple POST classique) -- ce
 * contrôleur n'ajoute qu'un état de chargement, rien d'indispensable.
 */
export default class extends Controller {
    static targets = ['button'];

    submit() {
        if (this.hasButtonTarget) {
            this.buttonTarget.disabled = true;
            this.buttonTarget.textContent = '...';
        }
    }

    connect() {
        this.element.addEventListener('submit', () => this.submit());
    }
}
