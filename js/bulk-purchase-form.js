document.addEventListener('DOMContentLoaded', function() {
    console.log('Bulk Purchase Form Script Loaded');
    const quantityInputs = document.querySelectorAll('.bulk-purchase-form__quantity-input');

    const formatter = new Intl.NumberFormat('es-CR', {
        style: 'currency',
        currency: 'CRC',
        minimumFractionDigits: 2
    });

    function updateTotals() {
        let mainTotal = 0;

        quantityInputs.forEach(input => {
            const row = input.closest('.bulk-purchase-form__product-row');
            const priceText = row.querySelector('.bulk-purchase-form__product-price').textContent;
            // Fix: Remove '₡', remove thousands separator (.), replace decimal separator (,) with (.)
            const price = parseFloat(priceText.replace('₡', '').replace(/\./g, '').replace(',', '.').trim());
            const quantity = parseInt(input.value, 10);
            const lineTotal = price * quantity;

            const totalCell = row.querySelector('.bulk-purchase-form__product-total');
            totalCell.textContent = formatter.format(lineTotal);

            mainTotal += lineTotal;
        });

        document.querySelector('#orderTotal').textContent = formatter.format(mainTotal);
    }

    quantityInputs.forEach(input => {
        input.addEventListener('change', updateTotals);
        input.addEventListener('input', updateTotals);
    });
    updateTotals();
});
