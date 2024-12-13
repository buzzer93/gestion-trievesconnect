setTimeout(function () {
    // Vérifiez si les éléments "produit" existent
    let purchasePriceInput = document.getElementById('product_purchasePrice');
    let sellingPriceInput = document.getElementById('product_sellingPrice');
    let marginRateInput = document.getElementById('product_marginRate');

    // Si les éléments "produit" n'existent pas, utilisez les éléments "service"
    if (!purchasePriceInput || !sellingPriceInput || !marginRateInput) {
        purchasePriceInput = document.getElementById('service_purchasePrice');
        sellingPriceInput = document.getElementById('service_sellingPrice');
        marginRateInput = document.getElementById('service_marginRate');
    }

    // Testez les valeurs des inputs (facultatif)
    if (purchasePriceInput && sellingPriceInput && marginRateInput) {
        console.log('Les champs ont été correctement récupérés.');
    } else {
        console.error('Impossible de récupérer les champs nécessaires.');
    }
    // Fonction pour mettre à jour le prix de vente
    const updateSellingPrice = () => {
        const purchasePrice = parseFloat(purchasePriceInput.value) || 0;
        const marginRate = parseFloat(marginRateInput.value) || 0;

        if (!isNaN(marginRate) && purchasePrice > 0) {
            // Calculer le prix de vente à partir du prix d'achat et du taux de marge
            const sellingPrice = purchasePrice * (1 + marginRate / 100) * 1.2;
            sellingPriceInput.value = sellingPrice.toFixed(2);
        } else {
            sellingPriceInput.value = ''; // Réinitialiser si le calcul échoue
        }
    };

    // Fonction pour calculer le taux de marge
    const calculateMarginRate = () => {
        const purchasePrice = parseFloat(purchasePriceInput.value) || 0;
        const sellingPrice = parseFloat(sellingPriceInput.value) || 0;

        if (purchasePrice > 0) {
            // Calculer le taux de marge à partir du prix de vente et du prix d'achat
            const sellingPriceHT = sellingPrice / 1.2;
            const marginRate = (sellingPriceHT / purchasePrice - 1) * 100;
            marginRateInput.value = marginRate.toFixed(2);
        }
        else {
            const marginRate = sellingPrice / 1.2;
            marginRateInput.value = marginRate.toFixed(2);
        }

    };

    // Fonction d'initialisation
    const init = () => {
        calculateMarginRate();
    };

    // Ajout des écouteurs d'événements
    purchasePriceInput.addEventListener('blur', updateSellingPrice);
    marginRateInput.addEventListener('blur', updateSellingPrice);
    sellingPriceInput.addEventListener('blur', calculateMarginRate);

    // Appel de la fonction d'initialisation lors du chargement de la page
    init();
}, 400);
