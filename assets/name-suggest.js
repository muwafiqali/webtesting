document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-name-suggest]').forEach((wrapper) => {
        const input = wrapper.querySelector('[role="combobox"]');
        const popup = wrapper.querySelector('[data-name-popup]');
        const options = Array.from(wrapper.querySelectorAll('[data-name-option]'));
        const emptyMessage = wrapper.querySelector('[data-name-empty]');
        if (!(input instanceof HTMLInputElement) || !(popup instanceof HTMLElement) || !(emptyMessage instanceof HTMLElement)) {
            return;
        }
        let activeIndex = -1;

        const visibleOptions = () => options.filter((option) => !option.hidden);

        function resetActiveOption() {
            activeIndex = -1;
            options.forEach((option) => option.setAttribute('aria-selected', 'false'));
            input.removeAttribute('aria-activedescendant');
        }

        function filterOptions() {
            const query = input.value.trim().toLocaleLowerCase();
            let visibleCount = 0;
            options.forEach((option) => {
                option.hidden = !option.textContent.trim().toLocaleLowerCase().includes(query);
                if (!option.hidden) {
                    visibleCount += 1;
                }
            });
            emptyMessage.hidden = visibleCount > 0;
            resetActiveOption();
        }

        function open() {
            filterOptions();
            popup.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }

        function close() {
            popup.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            resetActiveOption();
        }

        function setActiveOption(index) {
            const matches = visibleOptions();
            if (!matches.length) {
                resetActiveOption();
                return;
            }

            activeIndex = (index + matches.length) % matches.length;
            matches.forEach((option, optionIndex) => {
                option.setAttribute('aria-selected', String(optionIndex === activeIndex));
            });
            const activeOption = matches[activeIndex];
            input.setAttribute('aria-activedescendant', activeOption.id);
            activeOption.scrollIntoView({ block: 'nearest' });
        }

        function selectOption(option) {
            input.value = option.textContent.trim();
            input.focus();
            close();
        }

        input.addEventListener('focus', open);
        input.addEventListener('input', open);
        input.addEventListener('keydown', (event) => {
            const matches = visibleOptions();
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                if (popup.hidden) {
                    open();
                }
                setActiveOption(activeIndex + (event.key === 'ArrowDown' ? 1 : -1));
            } else if (event.key === 'Enter' && !popup.hidden && activeIndex >= 0) {
                event.preventDefault();
                selectOption(matches[activeIndex]);
            } else if (event.key === 'Escape' && !popup.hidden) {
                event.preventDefault();
                close();
            }
        });

        options.forEach((option) => {
            option.addEventListener('click', () => selectOption(option));
        });

        wrapper.addEventListener('focusout', (event) => {
            if (!wrapper.contains(event.relatedTarget)) {
                close();
            }
        });

        document.addEventListener('pointerdown', (event) => {
            if (!wrapper.contains(event.target)) {
                close();
            }
        });
    });
});
