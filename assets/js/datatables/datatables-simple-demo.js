window.addEventListener('DOMContentLoaded', event => {
    // Initialiser tous les tableaux avec la classe "datatable"
    document.querySelectorAll('.datatable').forEach(table => {
        new simpleDatatables.DataTable(table);
    });
});
