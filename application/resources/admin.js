/* Content Placements | MIT | IPS controls conditional form visibility. */
(function () {
    'use strict';

    const initialized = new WeakSet();

    function hookControls(form, mode) {
        const application = form.querySelector('select[name="rqbp_hook_app"]');
        const template = form.querySelector('select[name="rqbp_hook_template"]');
        const hook = form.querySelector('select[name="rqbp_hook"]');
        const position = form.querySelector('select[name="rqbp_position"]');
        if (!application || !template || !hook) {
            return;
        }
        const disabled = new WeakMap();
        [template, hook, position].filter(Boolean).forEach((select) => {
            Array.from(select.options).forEach((option) => disabled.set(option, option.disabled));
        });
        let positionMap = {};
        try {
            positionMap = JSON.parse(form.dataset.rqbpHookPositions || '{}');
        } catch (error) {
            // Missing or malformed hook metadata cannot enable a hook position.
        }

        function filter(select, accepts, chooseFirst) {
            const current = select.value;
            Array.from(select.options).forEach((option) => {
                const valid = option.value === '' || accepts(option.value);
                option.hidden = !valid;
                option.disabled = Boolean(disabled.get(option)) || !valid;
            });
            select.querySelectorAll('optgroup').forEach((group) => {
                group.hidden = !Array.from(group.children).some((option) => !option.hidden);
            });
            const available = Array.from(select.options).filter((option) => !option.disabled && !option.hidden);
            const preserved = available.find((option) => option.value === current && current !== '');
            const selected = preserved || (chooseFirst ? available.find((option) => option.value !== '') : null);
            select.value = selected?.value || '';
        }

        function positions() {
            if (!position) {
                return;
            }
            const allowed = mode() === 'css' || mode() === 'server'
                ? ['before', 'inside-start', 'inside-end', 'after']
                : positionMap && Array.isArray(positionMap[hook.value]) ? positionMap[hook.value] : [];
            const current = position.value;
            Array.from(position.options).forEach((option) => {
                option.disabled = Boolean(disabled.get(option)) || (option.value !== '' && !allowed.includes(option.value));
                // Native options stay visible so the available positions are clear.
            });
            const available = Array.from(position.options).filter((option) => option.value !== '' && !option.disabled);
            position.value = available.find((option) => option.value === current)?.value || available[0]?.value || '';
        }

        function hooks(chooseFirst) {
            filter(hook, (value) => Boolean(template.value) && value.startsWith(template.value + '|'), chooseFirst);
            positions();
        }
        function templates(chooseFirst) {
            filter(template, (value) => Boolean(application.value) && value.startsWith(application.value + '/'), chooseFirst);
            hooks(chooseFirst);
        }
        application.addEventListener('change', () => templates(true));
        template.addEventListener('change', () => hooks(true));
        hook.addEventListener('change', positions);
        form.addEventListener('change', (event) => {
            if (event.target.name === 'rqbp_mode') {
                positions();
            }
        });
        templates(false);
    }

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
            hookControls(form, mode);
            const validate = () => {
                input.setCustomValidity('');
                if (mode() !== 'css' && mode() !== 'server') {
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
