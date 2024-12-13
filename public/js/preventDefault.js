
document.addEventListener('DOMContentLoaded', function () {
    setTimeout(function () {
        const form = document.getElementById('item');
        if (form) {
            form.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === 'NumpadEnter') {
                    event.preventDefault();
                }
            });
        } else {
            console.error('Form not found!');
        }
    }, 500); // Délai de 0.5 seconde
});