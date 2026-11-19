document.addEventListener('DOMContentLoaded', () => {
    const components = [];

    document.querySelectorAll('select[data-custom-select]').forEach((select, index) => {
        if (!(select instanceof HTMLSelectElement)) {
            return;
        }

        const label = select.dataset.customSelectLabel || select.name;
        const originalId = select.id || `custom-select-${index + 1}`;
        const required = select.required;
        const wrapper = document.createElement('div');
        wrapper.className = 'custom-select';

        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);

        const trigger = document.createElement('button');
        trigger.id = originalId;
        trigger.type = 'button';
        trigger.className = 'custom-select-trigger';
        trigger.setAttribute('role', 'combobox');
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-controls', `${originalId}-options`);
        trigger.setAttribute('aria-required', String(required));
        trigger.setAttribute('aria-label', label);

        const value = document.createElement('span');
        const arrow = document.createElement('span');
        arrow.className = 'custom-select-arrow';
        arrow.setAttribute('aria-hidden', 'true');
        arrow.textContent = '▾';
        trigger.append(value, arrow);

        const menu = document.createElement('div');
        menu.id = `${originalId}-options`;
        menu.className = 'custom-select-menu';
        menu.setAttribute('role', 'listbox');
        menu.setAttribute('aria-label', `${label} options`);
        menu.hidden = true;

        const error = document.createElement('p');
        error.className = 'custom-select-error';
        error.setAttribute('role', 'alert');
        error.hidden = true;
        error.textContent = `Pilih ${label.toLowerCase()} terlebih dahulu.`;

        select.id = `${originalId}-native`;
        select.classList.add('custom-select-native');
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');
        select.required = false;
        wrapper.append(trigger, menu, error);

        const optionElements = [];
        Array.from(select.options).forEach((option) => {
            const item = document.createElement('div');
            item.className = 'custom-select-option';
            item.setAttribute('role', 'option');
            item.tabIndex = option.disabled ? -1 : 0;
            item.setAttribute('aria-disabled', String(option.disabled));
            item.textContent = option.textContent;
            item.addEventListener('click', () => {
                if (!option.disabled) {
                    select.value = option.value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    close(true);
                }
            });
            item.addEventListener('keydown', (event) => {
                const enabledItems = optionElements.filter((entry) => entry.getAttribute('aria-disabled') !== 'true');
                const currentIndex = enabledItems.indexOf(item);
                let nextIndex = currentIndex;

                if (event.key === 'ArrowDown') {
                    nextIndex = (currentIndex + 1) % enabledItems.length;
                } else if (event.key === 'ArrowUp') {
                    nextIndex = (currentIndex - 1 + enabledItems.length) % enabledItems.length;
                } else if (event.key === 'Home') {
                    nextIndex = 0;
                } else if (event.key === 'End') {
                    nextIndex = enabledItems.length - 1;
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    close(true);
                    return;
                } else if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    item.click();
                    return;
                } else {
                    return;
                }

                event.preventDefault();
                enabledItems[nextIndex]?.focus();
            });
            menu.appendChild(item);
            optionElements.push(item);
        });

        function render() {
            const selectedOption = select.options[select.selectedIndex];
            value.textContent = selectedOption ? selectedOption.textContent : '';
            trigger.setAttribute('aria-label', `${label}: ${value.textContent}`);
            optionElements.forEach((item, itemIndex) => {
                item.setAttribute('aria-selected', String(select.options[itemIndex].value === select.value));
            });
            error.hidden = true;
            trigger.removeAttribute('aria-invalid');
        }

        function close(returnFocus) {
            menu.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            if (returnFocus) {
                trigger.focus();
            }
        }

        function open() {
            menu.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            const selected = optionElements.find((item, itemIndex) =>
                select.options[itemIndex].value === select.value &&
                item.getAttribute('aria-disabled') !== 'true'
            );
            (selected || optionElements.find((item) => item.getAttribute('aria-disabled') !== 'true'))?.focus();
        }

        trigger.addEventListener('click', () => {
            if (menu.hidden) {
                open();
            } else {
                close(false);
            }
        });
        trigger.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                open();
            } else if (event.key === 'Escape' && !menu.hidden) {
                event.preventDefault();
                close(true);
            }
        });
        wrapper.addEventListener('focusout', (event) => {
            if (!wrapper.contains(event.relatedTarget)) {
                close(false);
            }
        });
        select.addEventListener('change', render);
        render();
        components.push({ select, trigger, error, required });
    });

    document.addEventListener('click', (event) => {
        components.forEach(({ trigger }) => {
            const wrapper = trigger.closest('.custom-select');
            if (wrapper && !wrapper.contains(event.target)) {
                const menu = wrapper.querySelector('.custom-select-menu');
                menu.hidden = true;
                trigger.setAttribute('aria-expanded', 'false');
            }
        });
    });

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const invalid = components.find(({ select, required }) =>
                required && select.form === form && !select.value
            );
            if (invalid) {
                event.preventDefault();
                invalid.trigger.setAttribute('aria-invalid', 'true');
                invalid.error.hidden = false;
                invalid.trigger.focus();
            }
        });
    });
});
