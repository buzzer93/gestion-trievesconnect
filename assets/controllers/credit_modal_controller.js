import { Controller } from '@hotwired/stimulus';

/*
 * Modale "gérer le solde d'impression" — partagée entre les clients et les
 * associations (cf. templates/admin/customer/index.html.twig et
 * templates/admin/association/{index,show}.html.twig).
 *
 * Pilote l'ajout de crédit personnel et le débit d'impression, avec un
 * calcul d'estimation client basé sur les tarifs configurés en admin
 * (page "Tarifs d'impression"), passés via ratesValue — pas de grille de
 * prix codée en dur ici : source unique avec le calcul serveur
 * (PrintCostCalculator), pour que l'estimation affichée corresponde
 * toujours au montant réellement débité.
 *
 * Le controller englobe à la fois le(s) bouton(s) déclencheurs et la modale
 * (data-controller="modal", séparé, sur la modale elle-même -- ciblée via
 * modalRootTarget) : les boutons vivent dans un tableau, hors de la modale,
 * donc ils doivent partager le même élément data-controller="credit-modal"
 * ancêtre pour que leurs data-action résolvent.
 *
 * hasMunicipalValue distingue client (un seul solde) et association (solde
 * personnel + solde mairie, débité en priorité côté serveur).
 */
export default class extends Controller {
    static targets = [
        'modalRoot', 'balancePersonal', 'balanceMunicipal', 'municipalRow',
        'amount', 'colorMode', 'paperSize', 'copies', 'estimatedCost',
        'municipalCreditSection', 'municipalAmount',
        // Optionnelles : encarts soldes affichés hors modale (fiche association).
        'pageBalancePersonal', 'pageBalanceMunicipal',
    ];

    static values = {
        basePath: String, // ex: '/admin/customer/' -- on complète avec '{id}/credits' etc.
        rates: Array,
        hasMunicipal: Boolean,
    };

    connect() {
        this.currentId = null;
    }

    open(event) {
        const { id, personal, municipal } = event.params;
        this.currentId = id;

        this.balancePersonalTarget.textContent = (personal / 100).toFixed(2);
        if (this.hasMunicipalValue && this.hasBalanceMunicipalTarget) {
            this.balanceMunicipalTarget.textContent = (municipal / 100).toFixed(2);
        }
        if (this.hasMunicipalRowTarget) {
            this.municipalRowTarget.classList.toggle('hidden', !this.hasMunicipalValue);
        }

        this.amountTarget.value = '1.00';
        this._computeEstimate();

        this.modalRootTarget.dispatchEvent(new CustomEvent('modal:open'));
    }

    close() {
        this.modalRootTarget.dispatchEvent(new CustomEvent('modal:close'));
    }

    _computeEstimate() {
        const colorMode = this.colorModeTarget.value;
        const paperSize = this.paperSizeTarget.value;
        const copies = Math.max(1, parseInt(this.copiesTarget.value, 10) || 1);

        const rate = this.ratesValue.find(r => r.colorMode === colorMode && r.paperSize === paperSize);
        const cents = rate ? rate.priceCents * copies : 0;
        this.estimatedCostTarget.textContent = (cents / 100).toFixed(2);

        return cents;
    }

    recomputeEstimate() {
        this._computeEstimate();
    }

    async addCredit() {
        const euros = parseFloat(this.amountTarget.value.replace(',', '.'));
        if (isNaN(euros) || euros <= 0) {
            return;
        }
        const cents = Math.round(euros * 100);

        await this._post(`${this.basePathValue}${this.currentId}/credits`, { mode: 'add', cents }, (data) => {
            this._updateRowBalance(data.credits);
            this._updatePageBalances(data.credits);
            this.close();
        });
    }

    async addMunicipalCredit() {
        const euros = parseFloat(this.municipalAmountTarget.value.replace(',', '.'));
        if (isNaN(euros) || euros <= 0) {
            return;
        }
        const cents = Math.round(euros * 100);

        await this._post(`${this.basePathValue}${this.currentId}/municipal-credits`, { mode: 'add', cents }, (data) => {
            this.balanceMunicipalTarget.textContent = (data.municipalCredits / 100).toFixed(2);
            this._updateRowBalance(undefined, data.municipalCredits);
            this._updatePageBalances(undefined, data.municipalCredits);
            window.showFlash?.('success', 'Crédit mairie ajouté.');
            this.close();
        });
    }

    async chargePrint() {
        const cents = this._computeEstimate();
        if (cents <= 0) {
            window.showFlash?.('error', 'Tarif non configuré pour cette combinaison.');
            return;
        }

        await this._post(`${this.basePathValue}${this.currentId}/print-charge`, {
            colorMode: this.colorModeTarget.value,
            paperSize: this.paperSizeTarget.value,
            copies: Math.max(1, parseInt(this.copiesTarget.value, 10) || 1),
        }, (data) => {
            const personal = data.personalCredits ?? data.credits;
            this._updateRowBalance(personal, data.municipalCredits);
            this._updatePageBalances(personal, data.municipalCredits);
            this.balancePersonalTarget.textContent = (personal / 100).toFixed(2);
            if (this.hasMunicipalValue && undefined !== data.municipalCredits && this.hasBalanceMunicipalTarget) {
                this.balanceMunicipalTarget.textContent = (data.municipalCredits / 100).toFixed(2);
            }
            window.showFlash?.('success', 'Débit appliqué.');
            this.close();
        });
    }

    async _post(url, body, onSuccess) {
        try {
            const resp = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });
            const data = await resp.json();
            if (!resp.ok) {
                window.showFlash?.('error', data.error || 'Erreur');
                return;
            }
            onSuccess(data);
        } catch (err) {
            window.showFlash?.('error', 'Erreur réseau');
        }
    }

    // Deux boutons peuvent partager le même data-id sur une même ligne
    // (solde personnel + solde mairie) : on les met tous les deux à jour,
    // et seulement pour le solde réellement fourni (undefined = inchangé).
    _updateRowBalance(personalCents, municipalCents) {
        const buttons = document.querySelectorAll(`.credit-btn[data-id="${this.currentId}"]`);

        buttons.forEach((btn) => {
            if (undefined !== personalCents) {
                btn.dataset.creditModalPersonalParam = personalCents;
                const span = btn.querySelector('[data-credit-modal-balance="personal"]');
                if (span) {
                    span.textContent = (personalCents / 100).toFixed(2) + ' €';
                }
            }
            if (undefined !== municipalCents) {
                btn.dataset.creditModalMunicipalParam = municipalCents;
                const span = btn.querySelector('[data-credit-modal-balance="municipal"]');
                if (span) {
                    span.textContent = (municipalCents / 100).toFixed(2) + ' €';
                }
            }
        });
    }

    _updatePageBalances(personalCents, municipalCents) {
        if (undefined !== personalCents && this.hasPageBalancePersonalTarget) {
            this.pageBalancePersonalTarget.textContent = (personalCents / 100).toFixed(2) + ' €';
        }
        if (undefined !== municipalCents && this.hasPageBalanceMunicipalTarget) {
            this.pageBalanceMunicipalTarget.textContent = (municipalCents / 100).toFixed(2) + ' €';
        }
    }
}
