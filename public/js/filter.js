/**
 * Ajoute un écouteur d'événement pour la touche clavier "keyup" sur l'entrée de filtre.
 * Appelle la fonction filterTable() en réponse à l'événement.
 */
var filter_input = document.getElementById('filterInput');
filter_input.addEventListener("keyup", function () {
    filterTable();
});


window.onload = function () {
    filter_input.focus();
    const inputField = document.getElementById('labeling_barCode');
    if (inputField) {
        inputField.focus(); // Vérifie que l'élément existe avant d'appliquer le focus
    }
};
/**
 * Filtrer le tableau en fonction de la valeur de l'entrée de filtre.
 */
function filterTable() {
    // Déclare les variables
    var input, filter, table, tr, td_barCode, td_name, barCodeValue, nameValue, i;

    // Récupérer l'élément d'entrée du filtre
    input = filter_input;

    // Convertir la valeur de l'entrée en majuscules pour un filtrage insensible à la casse
    filter = input.value.toUpperCase();

    // Obtenir le tableau et ses lignes
    table = document.getElementById("sortableTable");
    tr = table.getElementsByTagName("tr");
    // Boucle à travers toutes les lignes du tableau et masque celles qui ne correspondent pas à la requête de recherche
    for (i = 1; i < tr.length; i++) {
        // Obtenir la première cellule de table (td) dans chaque ligne
        td_barCode = tr[i].getElementsByClassName("bareCode")[0];
        td_name = tr[i].getElementsByClassName("product_name")[0];
        

        // Vérifiez si td_barCode et td_name existent
        if (td_barCode && td_name) {
            // Récupérer le contenu textuel de la cellule de table
            barCodeValue = td_barCode.textContent || td_barCode.innerText;
            nameValue = td_name.textContent || td_name.innerText;
            // Vérifiez si le contenu textuel contient la chaîne de filtre
            if (barCodeValue.toUpperCase().indexOf(filter) > -1 || nameValue.toUpperCase().indexOf(filter) > -1) {
                // Affichez la ligne si elle correspond au filtre
                tr[i].style.display = "";
            } else {
                // Masquez la ligne si elle ne correspond pas au filtre
                tr[i].style.display = "none";
            }
        }
    }
}
