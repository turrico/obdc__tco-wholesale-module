document.addEventListener('DOMContentLoaded', function() {
    console.log('Bulk Purchase Form Script Loaded');
    const form = document.querySelector('.bulk-purchase-form');
    const quantityInputs = document.querySelectorAll('.bulk-purchase-form__quantity-input');
    const stickyHeader = document.querySelector('.bulk-purchase-form__sticky-header');

    if (quantityInputs.length === 0) {
        console.warn('No quantity inputs found for bulk purchase form.');
    }

    const formatter = new Intl.NumberFormat('es-CR', {
        style: 'currency',
        currency: 'CRC',
        minimumFractionDigits: 2
    });

    // --- AJAX Sync with Cart ---
    function syncInputsWithCart() {
        if (typeof obdc_vars === 'undefined' || !obdc_vars.ajax_url) {
            console.warn('OBDC Vars not defined, skipping AJAX cart sync.');
            return;
        }

        // Use jQuery for compatibility with WP AJAX
        jQuery.ajax({
            url: obdc_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'obdc_get_cart_quantities',
                nonce: obdc_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    const cartData = response.data;
                    console.log('Cart synced:', cartData);

                    // Reset all inputs to 0 first (in case item was removed)
                    // Then update with fresh data
                    quantityInputs.forEach(input => {
                        // Extract product ID from name="quantity[123]"
                        const match = input.name.match(/quantity\[(\d+)\]/);
                        if (match && match[1]) {
                            const productId = parseInt(match[1], 10);
                            const currentQty = cartData[productId] ? cartData[productId] : 0;
                            
                            // Only update if different to avoid overriding user typing if they are super fast
                            if (parseInt(input.value) !== currentQty) {
                                input.value = currentQty;
                            }
                        }
                    });
                    
                    // Recalculate totals after sync
                    updateTotals();
                }
            },
            error: function(err) {
                console.error('Failed to sync cart:', err);
            }
        });
    }

    // Debounce function
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
            const priceCell = row.querySelector('.bulk-purchase-form__product-price');
            let price = 0;

            if (priceCell && priceCell.dataset.price) {
                price = parseFloat(priceCell.dataset.price);
            } else {
                const priceText = priceCell ? priceCell.textContent : '0';
                price = parseFloat(priceText.replace('₡', '').replace(/\./g, '').replace(',', '.').trim());
            }
            
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
        input.addEventListener('focus', function() {
            if (this.value === '0') {
                this.value = '';
            }
        });

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
    
    // Trigger AJAX sync to handle cache/back button
    syncInputsWithCart();

    // Listen for pageshow (fixes Back button on Safari/Firefox bfcache)
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            console.log('Page restored from bfcache, syncing cart...');
            syncInputsWithCart();
        }
    });

    window.addEventListener('resize', debounce(updateStickyOffset, 100));
});