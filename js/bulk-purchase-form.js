document.addEventListener('DOMContentLoaded', function() {
    console.log('Bulk Purchase Form Script Loaded');
    const form = document.querySelector('.bulk-purchase-form');
    const quantityInputs = document.querySelectorAll('.bulk-purchase-form__quantity-input');
    const stickyHeader = document.querySelector('.bulk-purchase-form__sticky-header');

    // Check if inputs were found to debug potential loading issues
    if (quantityInputs.length === 0) {
        console.warn('No quantity inputs found for bulk purchase form.');
    }

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
            const height = stickyHeader.offsetHeight;
            form.style.setProperty('--sticky-header-height', height + 'px');
        }
    }

    function updateTotals() {
        let mainTotal = 0;

        quantityInputs.forEach(input => {
            const row = input.closest('.bulk-purchase-form__product-row');
            
            // ROBUST PRICE PARSING: Use data-price attribute instead of text content
            const priceCell = row.querySelector('.bulk-purchase-form__product-price');
            let price = 0;

            if (priceCell && priceCell.dataset.price) {
                price = parseFloat(priceCell.dataset.price);
            } else {
                // Fallback (should not be reached if HTML is correct)
                console.warn('Missing data-price attribute, attempting fallback parse');
                const priceText = priceCell ? priceCell.textContent : '0';
                price = parseFloat(priceText.replace('₡', '').replace(/\./g, '').replace(',', '.').trim());
            }
            
            // Parse quantity, defaulting to 0 if NaN (empty or invalid)
            let quantity = parseInt(input.value, 10);
            if (isNaN(quantity)) {
                quantity = 0;
            }

            const lineTotal = price * quantity;

            const totalCell = row.querySelector('.bulk-purchase-form__product-total');
            if (totalCell) {
                totalCell.textContent = formatter.format(lineTotal);
            }

            mainTotal += lineTotal;
        });

        const orderTotalElement = document.querySelector('#orderTotal');
        if (orderTotalElement) {
            orderTotalElement.textContent = formatter.format(mainTotal);
        }
    }

    quantityInputs.forEach(input => {
        // IMPROVED UX: Clear '0' on focus instead of selecting text. 
        // This avoids triggering the mobile OS copy/paste menu ("secondary menu").
        input.addEventListener('focus', function() {
            if (this.value === '0') {
                this.value = '';
            }
        });

        // Reset to 0 if left empty on blur
        input.addEventListener('blur', function() {
            if (this.value === '' || isNaN(parseInt(this.value, 10))) {
                this.value = '0';
                updateTotals();
            }
        });

        input.addEventListener('change', updateTotals);
        input.addEventListener('input', updateTotals);
    });

    // Initial calculations
    updateTotals();
    updateStickyOffset();

    // Re-calculate on resize with debounce (wait 100ms)
    window.addEventListener('resize', debounce(updateStickyOffset, 100));
});