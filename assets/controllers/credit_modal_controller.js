import { Controller } from '@hotwired/stimulus';

/*
 * Modale "gérer le solde d'impression" — partagée entre les clients et les
 * associations (cf. templates/admin/customer/index.html.twig et
 * templates/admin/association/{index,show}.html.twig).
 *
 * Scopée par "focus" (personnel ou mairie), passé par le bouton déclencheur
 * via data-credit-modal-focus-param : cliquer sur "Solde personnel" ne
 * montre que l'ajout au solde personnel, cliquer sur "Solde mairie" ne
 * montre que l'ajout au crédit mairie -- évite d'afficher les deux comme
 * actionnables en même temps, source de confusion (retour du 2026-08-25).
 *
 * "Débiter pour impression" est en revanche TOUJOURS visible, quel que
 * soit le focus : depuis la décision du 2026-08-25, la source débitée
 * (mairie ou personnel) n'est plus un choix laissé à l'admin -- elle est
 * entièrement déterminée côté serveur par PrintPolicyEvaluator
 * (éligibilité + priorité), exactement comme pour PrintGate. L'estimation
 * affichée ici utilise le tarif ASSOCIATION (majorant) : le montant
 * réellement débité peut être inférieur si la mairie finance tout ou
 * partie -- le serveur reste seul juge, cf. chargePrint().
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
        'personalCreditSection', 'municipalCreditSection', 'municipalAmount',
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

    // N'affiche que la section "Ajouter" pertinente pour le solde ciblé --
    // "Débiter pour impression" reste toujours visible (cf. PHPDoc de
    // fichier).
    _applyFocus() {
        const isMunicipal = this.currentFocus === 'municipal';

        this.titleTarget.textContent = isMunicipal ? 'Gérer le crédit mairie' : 'Gérer le solde personnel';
        this.balanceLabelTarget.textContent = isMunicipal ? 'Solde mairie' : 'Solde personnel';
        this.balanceValueTarget.textContent = ((isMunicipal ? this.currentMunicipalCents : this.currentPersonalCents) / 100).toFixed(2);

        this.personalCreditSectionTarget.classList.toggle('hidden', isMunicipal);
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

    async addMunicipalCredit() {
        const euros = parseFloat(this.municipalAmountTarget.value.replace(',', '.'));
        if (isNaN(euros) || euros <= 0) {
            return;
        }
        const cents = Math.round(euros * 100);

        await this._post(`${this.basePathValue}${this.currentId}/municipal-credits`, { mode: 'add', cents }, (data) => {
            this._updateRowBalance(undefined, data.municipalCredits);
            this._updatePageBalances(undefined, data.municipalCredits);
            window.showFlash?.('success', 'Crédit mairie ajouté.');
            this.close();
        });
    }

    // Le solde débité (mairie, personnel, ou les deux) est déterminé côté
    // serveur -- cf. PHPDoc de fichier. `fundingSource` dans la réponse
    // sert uniquement à personnaliser le message de confirmation.
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
            window.showFlash?.('success', this._fundingSourceMessage(data.fundingSource));
            this.close();
        });
    }

    _fundingSourceMessage(fundingSource) {
        switch (fundingSource) {
            case 'MUNICIPAL':
                return 'Débit appliqué (crédit mairie).';
            case 'MIXED':
                return 'Débit appliqué (mairie + personnel).';
            case 'ASSOCIATION_PERSONAL':
            case 'CUSTOMER':
                return 'Débit appliqué (solde personnel).';
            default:
                return 'Débit appliqué.';
        }
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
