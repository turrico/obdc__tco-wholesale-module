document.addEventListener('DOMContentLoaded', function() {
    console.log('Bulk Purchase Form Script Loaded');
    const form = document.querySelector('.bulk-purchase-form');
    const quantityInputs = document.querySelectorAll('.bulk-purchase-form__quantity-input');
    const stickyHeader = document.querySelector('.bulk-purchase-form__sticky-header');

    const formatter = new Intl.NumberFormat('es-CR', {
        style: 'currency',
        currency: 'CRC',
        minimumFractionDigits: 2
    });

    // Debounce function to limit the rate at which a function can fire
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Function to calculate and set the sticky offset
    function updateStickyOffset() {
        if (stickyHeader && form) {
            // Using requestAnimationFrame for smoother visual updates during resize if needed,
            // but for a simple height calculation, direct access is usually fine.
            // Debouncing is the primary performance gain here.
            const height = stickyHeader.offsetHeight;
            form.style.setProperty('--sticky-header-height', height + 'px');
        }
    }

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

    // Initial calculations
    updateTotals();
    updateStickyOffset();

    // Re-calculate on resize with debounce (wait 100ms)
    window.addEventListener('resize', debounce(updateStickyOffset, 100));
});