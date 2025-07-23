window.addEventListener('DOMContentLoaded', event => {
    // Initialiser tous les tableaux avec la classe "datatable"
    document.querySelectorAll('.datatable').forEach(table => {
        new simpleDatatables.DataTable(table, {
            perPageSelect: [5, 10, 15, 20, 25],
            labels: {
                placeholder: "Recherche…",                    // input de recherche
                perPage:      "Entrées par page",     // 10 entrées par page
                noRows:       "Aucune donnée",
                info:         "Affichage {start} à {end} sur {rows} entrées"
            }
        });
    });
});
