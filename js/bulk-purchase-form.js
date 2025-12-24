(function() {
    // Variable to hold quantity inputs, scoped to this closure
    let quantityInputs = [];
    let form = null;
    let stickyHeader = null;

    const formatter = new Intl.NumberFormat('es-CR', {
        style: 'currency',
        currency: 'CRC',
        minimumFractionDigits: 2
    });

    // Core initialization function
    function initBulkPurchaseForm() {
        console.log('Bulk Purchase Form Script Initializing...');
        
        form = document.querySelector('.bulk-purchase-form');
        quantityInputs = document.querySelectorAll('.bulk-purchase-form__quantity-input');
        stickyHeader = document.querySelector('.bulk-purchase-form__sticky-header');

        if (!form || quantityInputs.length === 0) {
            console.warn('Bulk Purchase Form elements not found yet.');
            return false;
        }

        console.log(`Found ${quantityInputs.length} quantity inputs.`);

        // Event Listeners for Inputs
        quantityInputs.forEach(input => {
            input.addEventListener('focus', function() {
                if (this.value === '0') this.value = '';
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

        // Initial Run
        updateTotals();
        updateStickyOffset();
        syncInputsWithCart();

        window.addEventListener('resize', debounce(updateStickyOffset, 100));

        // Integration with Side Cart / WooCommerce Events
        jQuery(document.body).on('added_to_cart xoo_wsc_cart_updated wc_fragments_refreshed', function() {
            console.log('External cart update detected, syncing form...');
            syncInputsWithCart();
        });

        return true;
    }

    // --- AJAX Sync with Cart ---
    function syncInputsWithCart() {
        if (typeof obdc_vars === 'undefined' || !obdc_vars.ajax_url) {
            console.warn('OBDC Vars not defined, skipping AJAX cart sync.');
            return;
        }

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
                    console.log('Cart state retrieved from server:', cartData);

                    let changed = false;
                    quantityInputs.forEach(input => {
                        const match = input.name.match(/quantity\[(\d+)\]/);
                        if (match && match[1]) {
                            const productId = parseInt(match[1], 10);
                            const currentQty = cartData[productId] ? parseInt(cartData[productId], 10) : 0;
                            
                            if (parseInt(input.value, 10) !== currentQty) {
                                input.value = currentQty;
                                changed = true;
                            }
                        }
                    });
                    
                    if (changed) {
                        console.log('Inputs updated from cart data, recalculating totals.');
                    }
                    updateTotals();
                }
            },
            error: function(err) {
                console.error('Failed to sync cart:', err);
            }
        });
    }

    function updateStickyOffset() {
        if (stickyHeader && form) {
            const height = stickyHeader.offsetHeight;
            form.style.setProperty('--sticky-header-height', height + 'px');
        }
    }

    function updateTotals() {
        console.log('updateTotals execution triggered');
        let mainTotal = 0;

        if (!quantityInputs || quantityInputs.length === 0) {
             quantityInputs = document.querySelectorAll('.bulk-purchase-form__quantity-input');
        }

        quantityInputs.forEach((input, index) => {
            const row = input.closest('.bulk-purchase-form__product-row');
            if (!row) return;

            const priceCell = row.querySelector('.bulk-purchase-form__product-price');
            let price = 0;

            if (priceCell && priceCell.dataset.price) {
                price = parseFloat(priceCell.dataset.price);
            } else if (priceCell) {
                // Fallback for parsing
                const priceText = priceCell.textContent;
                price = parseFloat(priceText.replace('₡', '').replace(/\./g, '').replace(',', '.').trim());
            }
            
            if (isNaN(price)) price = 0;
            
            let quantity = parseInt(input.value, 10);
            if (isNaN(quantity)) quantity = 0;

            const lineTotal = price * quantity;

            const totalCell = row.querySelector('.bulk-purchase-form__product-total');
            if (totalCell) {
                const formattedLineTotal = formatter.format(lineTotal);
                totalCell.textContent = formattedLineTotal;
            }

            mainTotal += lineTotal;
        });

        const orderTotalElement = document.querySelector('#orderTotal');
        if (orderTotalElement) {
            const formattedMainTotal = formatter.format(mainTotal);
            console.log('New Main Total:', formattedMainTotal);
            orderTotalElement.textContent = formattedMainTotal;
        } else {
            console.error('Total element #orderTotal NOT FOUND in DOM');
        }
    }

    // Helpers
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    // LOADING LOGIC
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBulkPurchaseForm);
    } else {
        const success = initBulkPurchaseForm();
        if (!success) {
            // If it failed (elements not found), it might be a dynamic Bricks load
            // Retry a few times
            let retries = 0;
            const interval = setInterval(() => {
                if (initBulkPurchaseForm() || retries > 10) {
                    clearInterval(interval);
                }
                retries++;
            }, 500);
        }
    }

    // Handle bfcache
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            console.log('Page restored from cache, re-syncing...');
            syncInputsWithCart();
        }
    });

})();