import { Controller } from '@hotwired/stimulus';

/*
 * Modale "gérer le solde d'impression" — partagée entre les clients et les
 * associations (cf. templates/admin/customer/index.html.twig et
 * templates/admin/association/{index,show}.html.twig).
 *
 * Scopée par "focus" (personnel ou mairie), passé par le bouton déclencheur
 * via data-credit-modal-focus-param : cliquer sur "Solde personnel" ne
 * montre que la gestion du solde personnel (+ le débit impression, qui
 * reste une action liée au comptoir/personnel), cliquer sur "Solde mairie"
 * ne montre que l'ajout au crédit mairie. Évite d'afficher les deux soldes
 * comme actionnables en même temps, source de confusion (retour du
 * 2026-08-25) -- même si le débit d'impression touche réellement les deux
 * soldes côté serveur (mairie en priorité, cf. PrintCostCalculator /
 * AssociationRepository::debitForPrintJob), ça reste un détail
 * d'implémentation que l'admin n'a pas besoin de piloter depuis l'écran
 * "gérer le crédit mairie".
 *
 * Calcule l'estimation du débit à partir des tarifs configurés en admin
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
 */
export default class extends Controller {
    static targets = [
        'modalRoot', 'title', 'balanceLabel', 'balanceValue',
        'amount', 'colorMode', 'paperSize', 'copies', 'estimatedCost',
        'personalCreditSection', 'printChargeSection',
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
        this.currentFocus = 'personal';
        this.currentPersonalCents = 0;
        this.currentMunicipalCents = 0;
    }

    open(event) {
        const { id, personal, municipal, focus } = event.params;
        this.currentId = id;
        this.currentFocus = focus || 'personal';
        this.currentPersonalCents = personal ?? 0;
        this.currentMunicipalCents = municipal ?? 0;

        this._applyFocus();

        this.amountTarget.value = '1.00';
        if (this.hasMunicipalAmountTarget) {
            this.municipalAmountTarget.value = '1.00';
        }
        this._computeEstimate();

        this.modalRootTarget.dispatchEvent(new CustomEvent('modal:open'));
    }

    close() {
        this.modalRootTarget.dispatchEvent(new CustomEvent('modal:close'));
    }

    // Affiche uniquement les sections pertinentes pour le solde ciblé --
    // cf. le PHPDoc en tête de fichier pour le pourquoi.
    _applyFocus() {
        const isMunicipal = this.currentFocus === 'municipal';

        this.titleTarget.textContent = isMunicipal ? 'Gérer le crédit mairie' : 'Gérer le solde personnel';
        this.balanceLabelTarget.textContent = isMunicipal ? 'Solde mairie' : 'Solde personnel';
        this.balanceValueTarget.textContent = ((isMunicipal ? this.currentMunicipalCents : this.currentPersonalCents) / 100).toFixed(2);

        this.personalCreditSectionTarget.classList.toggle('hidden', isMunicipal);
        this.printChargeSectionTarget.classList.toggle('hidden', isMunicipal);
        if (this.hasMunicipalCreditSectionTarget) {
            this.municipalCreditSectionTarget.classList.toggle('hidden', !isMunicipal);
        }
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
            this._updateRowBalance(data.credits, undefined);
            this._updatePageBalances(data.credits, undefined);
            window.showFlash?.('success', 'Crédit ajouté.');
            this.close();
        });
    }

    // mode fourni par le bouton cliqué (data-credit-modal-mode-param="add"
    // ou "remove") -- même endpoint, même logique, un seul handler.
    async adjustMunicipalCredit(event) {
        const mode = event.params.mode;
        const euros = parseFloat(this.municipalAmountTarget.value.replace(',', '.'));
        if (isNaN(euros) || euros <= 0) {
            return;
        }
        const cents = Math.round(euros * 100);

        await this._post(`${this.basePathValue}${this.currentId}/municipal-credits`, { mode, cents }, (data) => {
            this._updateRowBalance(undefined, data.municipalCredits);
            this._updatePageBalances(undefined, data.municipalCredits);
            window.showFlash?.('success', 'add' === mode ? 'Crédit mairie ajouté.' : 'Crédit mairie débité.');
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
            // Le débit touche potentiellement les deux soldes (priorité
            // mairie), donc on répercute les deux -- même si cette section
            // n'est visible que côté "personnel".
            this._updateRowBalance(personal, data.municipalCredits);
            this._updatePageBalances(personal, data.municipalCredits);
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
