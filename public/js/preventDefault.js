
document.addEventListener('DOMContentLoaded', function () {
    setTimeout(function () {
        const form = document.getElementById('form');
        if (form) {
            form.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === 'NumpadEnter') {

                    // Si le focus est sur un <textarea>, autoriser le retour à la ligne
                    if (target.tagName === "TEXTAREA") {
                        return; // Laisse le comportement par défaut pour le textarea
                    }
                    // Sinon, empêcher le comportement par défaut (soumission du formulaire)
                    event.preventDefault();
                }
            });
        } else {
            console.error('Form not found!');
        }
    }, 500); // Délai de 0.5 seconde
});