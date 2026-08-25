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
 * Deux façons d'ouvrir la modale :
 * - depuis une ligne de tableau (délégation via data-action sur le bouton,
 *   avec les data-*-param portant l'id et les soldes courants) ;
 * - pour une association déjà connue de la page (fiche détail), les mêmes
 *   data-*-param sont posés directement sur le bouton unique.
 *
 * hasMunicipalValue distingue client (un seul solde) et association (solde
 * personnel + solde mairie, débité en priorité côté serveur).
 */
export default class extends Controller {
    static targets = [
        'balancePersonal', 'balanceMunicipal', 'municipalRow',
        'amount', 'colorMode', 'paperSize', 'copies', 'estimatedCost',
    ];

    static values = {
        chargeUrlTemplate: String, // ex: '/admin/customer/__id__/print-charge'
        creditUrlTemplate: String, // ex: '/admin/customer/__id__/credits'
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

        this.element.dispatchEvent(new CustomEvent('modal:open'));
    }

    close() {
        this.element.dispatchEvent(new CustomEvent('modal:close'));
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

        await this._post(this._url(this.creditUrlTemplateValue), { mode: 'add', cents }, (data) => {
            this._updateRowBalance(data.credits);
            this.close();
        });
    }

    async chargePrint() {
        const cents = this._computeEstimate();
        if (cents <= 0) {
            window.showFlash?.('error', 'Tarif non configuré pour cette combinaison.');
            return;
        }

        await this._post(this._url(this.chargeUrlTemplateValue), {
            colorMode: this.colorModeTarget.value,
            paperSize: this.paperSizeTarget.value,
            copies: Math.max(1, parseInt(this.copiesTarget.value, 10) || 1),
        }, (data) => {
            const personal = data.personalCredits ?? data.credits;
            this._updateRowBalance(personal, data.municipalCredits);
            this.balancePersonalTarget.textContent = (personal / 100).toFixed(2);
            if (this.hasMunicipalValue && undefined !== data.municipalCredits && this.hasBalanceMunicipalTarget) {
                this.balanceMunicipalTarget.textContent = (data.municipalCredits / 100).toFixed(2);
            }
            window.showFlash?.('success', 'Débit appliqué.');
            this.close();
        });
    }

    _url(template) {
        return template.replace('__id__', this.currentId);
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

    _updateRowBalance(personalCents, municipalCents) {
        const btn = document.querySelector(`.credit-btn[data-id="${this.currentId}"]`);
        if (!btn) {
            return;
        }
        btn.dataset.creditModalPersonalParam = personalCents;
        const span = btn.querySelector('[data-credit-modal-balance]');
        if (span) {
            span.textContent = (personalCents / 100).toFixed(2) + ' €';
        }
        if (undefined !== municipalCents) {
            btn.dataset.creditModalMunicipalParam = municipalCents;
        }
    }
}
