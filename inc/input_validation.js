/**
 * Global Input Restriction — Applied to all pages
 * Input Masking / Input Restriction (Frontend Validation)
 */

document.addEventListener('DOMContentLoaded', function () {

    // ─── Numbers Only (integers) ───────────────────────────────
    document.querySelectorAll('[data-input="numbers-only"]').forEach(function (el) {
        el.addEventListener('keypress', function (e) {
            if (!/[0-9]/.test(e.key) && e.key !== 'Backspace' && e.key !== 'Tab' && e.key !== 'Enter') {
                e.preventDefault();
            }
        });
        el.addEventListener('paste', function (e) {
            const paste = (e.clipboardData || window.clipboardData).getData('text');
            if (!/^[0-9]+$/.test(paste)) e.preventDefault();
        });
        el.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    });

    // ─── Decimal Numbers ───────────────────────────────────────
    document.querySelectorAll('[data-input="decimal-only"]').forEach(function (el) {
        el.addEventListener('keypress', function (e) {
            if (!/[0-9.]/.test(e.key) && e.key !== 'Backspace' && e.key !== 'Tab' && e.key !== 'Enter') {
                e.preventDefault();
            }
            // Only one decimal point allowed
            if (e.key === '.' && this.value.includes('.')) {
                e.preventDefault();
            }
        });
        el.addEventListener('paste', function (e) {
            const paste = (e.clipboardData || window.clipboardData).getData('text');
            if (!/^[0-9]*\.?[0-9]*$/.test(paste)) e.preventDefault();
        });
        el.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9.]/g, '');
            // Remove extra decimal points
            const parts = this.value.split('.');
            if (parts.length > 2) this.value = parts[0] + '.' + parts.slice(1).join('');
        });
    });

    // ─── Letters Only (names, positions) ──────────────────────
    document.querySelectorAll('[data-input="letters-only"]').forEach(function (el) {
        el.addEventListener('keypress', function (e) {
            if (!/[a-zA-Z\s\-\']/.test(e.key) && e.key !== 'Backspace' && e.key !== 'Tab' && e.key !== 'Enter') {
                e.preventDefault();
            }
        });
        el.addEventListener('paste', function (e) {
            const paste = (e.clipboardData || window.clipboardData).getData('text');
            if (!/^[a-zA-Z\s\-']+$/.test(paste)) e.preventDefault();
        });
        el.addEventListener('input', function () {
            this.value = this.value.replace(/[^a-zA-Z\s\-']/g, '');
        });
    });

    // ─── Alphanumeric (username) ───────────────────────────────
    document.querySelectorAll('[data-input="alphanumeric"]').forEach(function (el) {
        el.addEventListener('keypress', function (e) {
            if (!/[a-zA-Z0-9_]/.test(e.key) && e.key !== 'Backspace' && e.key !== 'Tab' && e.key !== 'Enter') {
                e.preventDefault();
            }
        });
        el.addEventListener('paste', function (e) {
            const paste = (e.clipboardData || window.clipboardData).getData('text');
            if (!/^[a-zA-Z0-9_]+$/.test(paste)) e.preventDefault();
        });
        el.addEventListener('input', function () {
            this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '');
        });
    });

    // ─── Phone/Contact Numbers (11 digits max) ─────────────────
    document.querySelectorAll('[data-input="phone"]').forEach(function (el) {
        el.setAttribute('maxlength', '11');
        el.addEventListener('keypress', function (e) {
            if (!/[0-9]/.test(e.key) && e.key !== 'Backspace' && e.key !== 'Tab' && e.key !== 'Enter') {
                e.preventDefault();
            }
        });
        el.addEventListener('paste', function (e) {
            const paste = (e.clipboardData || window.clipboardData).getData('text');
            if (!/^[0-9]+$/.test(paste)) e.preventDefault();
        });
        el.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);
        });
    });

    // ─── Letters and Numbers (alphanumeric with spaces) ────────
    document.querySelectorAll('[data-input="alphanumeric-space"]').forEach(function (el) {
        el.addEventListener('keypress', function (e) {
            if (!/[a-zA-Z0-9\s]/.test(e.key) && e.key !== 'Backspace' && e.key !== 'Tab' && e.key !== 'Enter') {
                e.preventDefault();
            }
        });
        el.addEventListener('paste', function (e) {
            const paste = (e.clipboardData || window.clipboardData).getData('text');
            if (!/^[a-zA-Z0-9\s]+$/.test(paste)) e.preventDefault();
        });
        el.addEventListener('input', function () {
            this.value = this.value.replace(/[^a-zA-Z0-9\s]/g, '');
        });
    });

});
