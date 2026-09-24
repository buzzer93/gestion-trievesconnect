import { Controller } from '@hotwired/stimulus';

/*
 * Filtre en direct les lignes d'un tableau par nom, téléphone, ou tout
 * texte visible de la ligne (recherche insensible à la casse, simple
 * sous-chaîne). Filtrage purement côté client : les listes concernées
 * (clients, associations) restent de taille modeste -- pas besoin d'une
 * recherche serveur/paginée tant que le volume réel ne le justifie pas
 * (cf. règles projet anti-surengineering).
 *
 * Markup attendu :
 *   <div data-controller="table-filter">
 *     <input data-table-filter-target="input" data-action="input->table-filter#filter">
 *     <table>
 *       <tbody>
 *         <tr data-table-filter-target="row">...</tr>
 *       </tbody>
 *     </table>
 *     <tr data-table-filter-target="empty" class="hidden">Aucun résultat</tr>
 *   </div>
 */
export default class extends Controller {
    static targets = ['input', 'row', 'empty'];

    filter() {
        const query = this.inputTarget.value.trim().toLowerCase();
        let visibleCount = 0;

        this.rowTargets.forEach((row) => {
            const matches = !query || row.textContent.toLowerCase().includes(query);
            row.classList.toggle('hidden', !matches);
            if (matches) {
                visibleCount++;
            }
        });

        if (this.hasEmptyTarget) {
            this.emptyTarget.classList.toggle('hidden', visibleCount > 0);
        }
    }
}
