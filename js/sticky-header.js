document.addEventListener('DOMContentLoaded', function() {
    const mainHeader = document.querySelector('.header__main-wrapper'); 
    const stickyHeader = document.querySelector('.bulk-purchase-form__sticky-header');
    const table = document.querySelector('.bulk-purchase-form__table');

    function updateStickyOffset() {
        const headerHeight = mainHeader ? mainHeader.offsetHeight : 0;
        const totalOffset = headerHeight;
        stickyHeader.style.top = `${totalOffset}px`;
    }

    function updateStickyWidth() {
        if (table && stickyHeader) {
            stickyHeader.style.width = `${table.offsetWidth}px`;
        }
    }

    updateStickyWidth();
    window.addEventListener('resize', updateStickyWidth);
    updateStickyOffset();
    window.addEventListener('resize', updateStickyOffset);
});
