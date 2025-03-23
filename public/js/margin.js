setTimeout(function () {
    const purchasePriceInput = document.getElementById('product_purchasePrice');
    const sellingPriceInput = document.getElementById('product_sellingPrice');
    const marginRateInput = document.getElementById('product_marginRate');

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
},400);
