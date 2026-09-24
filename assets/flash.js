/*
 * Flash dynamique côté JS — markup compatible components/_alert.html.twig.
 * Exposé globalement (window.showFlash) pour être appelé depuis n'importe
 * quel controller Stimulus (ex: credit_modal_controller.js) sans dépendance
 * directe entre eux — évite de dupliquer ce petit bout de DOM dans chaque
 * controller qui a besoin d'un retour utilisateur après un fetch.
 */
const CONFIG = {
    success: { border: 'border-l-accent', icon_color: 'text-accent', icon: 'fa-solid fa-circle-check' },
    error: { border: 'border-l-error', icon_color: 'text-error', icon: 'fa-solid fa-triangle-exclamation' },
    warning: { border: 'border-l-warning', icon_color: 'text-warning', icon: 'fa-solid fa-circle-exclamation' },
    info: { border: 'border-l-info', icon_color: 'text-info', icon: 'fa-solid fa-circle-info' },
};

export function showFlash(type, message, timeout = 3500) {
    const container = document.querySelector('main') || document.body;
    const c = CONFIG[type] || CONFIG.info;

    const alert = document.createElement('div');
    alert.className = `flex items-start gap-3 bg-surface-300 border border-border border-l-4 ${c.border} text-text-primary rounded-sm px-4 py-3 shadow-ambient mb-3 transition-opacity duration-300`;
    alert.setAttribute('role', type === 'error' ? 'alert' : 'status');
    alert.innerHTML = `<i class="${c.icon} ${c.icon_color} mt-0.5 shrink-0" aria-hidden="true"></i><div class="grow text-sm"></div>`;
    alert.querySelector('div').textContent = message;

    container.insertBefore(alert, container.firstChild);
    setTimeout(() => {
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 300);
    }, timeout - 300);
}

window.showFlash = showFlash;
