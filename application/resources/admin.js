/* Content Placements | MIT | IPS controls conditional form visibility. */
(function () {
    'use strict';

    const initialized = new WeakSet();

    function initialize() {
        document.querySelectorAll('[name="rqbp_selector"]').forEach((input) => {
            if (initialized.has(input)) {
                return;
            }
            initialized.add(input);
            const form = input.form;
            if (!form) {
                return;
            }
            const mode = () => form.querySelector('[name="rqbp_mode"]:checked')?.value || form.querySelector('select[name="rqbp_mode"], input[name="rqbp_mode"]')?.value;
            const validate = () => {
                input.setCustomValidity('');
                if (mode() !== 'css') {
                    return true;
                }
                const selector = input.value.trim();
                if (!selector) {
                    input.setCustomValidity(input.dataset.rqbpRequired || form.dataset.rqbpRequired || 'Enter a CSS selector.');
                } else if (selector.length > 1000) {
                    input.setCustomValidity(input.dataset.rqbpTooLong || form.dataset.rqbpTooLong || 'The CSS selector must be at most 1000 characters.');
                } else {
                    try {
                        document.querySelector(selector);
                    } catch (error) {
                        input.setCustomValidity(input.dataset.rqbpInvalid || form.dataset.rqbpInvalid || 'This CSS selector is not valid in this browser.');
                    }
                }
                return !input.validationMessage;
            };
            input.addEventListener('input', validate);
            input.addEventListener('blur', validate);
            form.addEventListener('change', validate);
            form.addEventListener('submit', (event) => {
                if (!validate()) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    input.reportValidity();
                }
            }, true);
            validate();
        });
    }

    if (window.jQuery) {
        window.jQuery(document).on('contentChange.rqblockplacementsAdmin', initialize);
    }
    document.addEventListener('contentChange', initialize);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
}());
