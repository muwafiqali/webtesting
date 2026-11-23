document.addEventListener('DOMContentLoaded', () => {
    const pickers = [];

    document.querySelectorAll('[data-time-picker]').forEach((picker) => {
        const hourValue = picker.querySelector('[data-time-hour-value]');
        const minuteValue = picker.querySelector('[data-time-minute-value]');
        const trigger = picker.querySelector('[data-time-trigger]');
        const display = picker.querySelector('[data-time-display]');
        const preview = picker.querySelector('[data-time-preview]');
        const panel = picker.querySelector('[data-time-panel]');
        const error = picker.querySelector('[data-time-error]');
        const hourList = picker.querySelector('[data-time-hour-options]');
        const minuteList = picker.querySelector('[data-time-minute-options]');
        const hourOptions = Array.from(picker.querySelectorAll('[data-time-option="hour"]'));
        const minuteOptions = Array.from(picker.querySelectorAll('[data-time-option="minute"]'));

        if (!(hourValue instanceof HTMLInputElement) || !(minuteValue instanceof HTMLInputElement)
            || !(trigger instanceof HTMLButtonElement) || !(display instanceof HTMLElement)
            || !(preview instanceof HTMLElement) || !(panel instanceof HTMLElement)
            || !(error instanceof HTMLElement) || !(hourList instanceof HTMLElement)
            || !(minuteList instanceof HTMLElement)) {
            return;
        }

        const selectedDuringOpen = new Set();

        const close = (returnFocus) => {
            panel.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            selectedDuringOpen.clear();
            if (returnFocus) {
                trigger.focus();
            }
        };

        const focusSelected = () => {
            const selectedHour = hourOptions.find((option) => option.dataset.value === hourValue.value);
            const selectedMinute = minuteOptions.find((option) => option.dataset.value === minuteValue.value);
            selectedHour?.scrollIntoView({ block: 'nearest' });
            selectedMinute?.scrollIntoView({ block: 'nearest' });
            (selectedHour || hourOptions[0])?.focus();
        };

        const open = () => {
            selectedDuringOpen.clear();
            panel.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            focusSelected();
        };

        const update = () => {
            let hour = hourValue.value;
            let minute = minuteValue.value;
            if (hour === '24' && minute && minute !== '00') {
                minute = '00';
                minuteValue.value = minute;
            }

            hourOptions.forEach((option) => {
                const selected = option.dataset.value === hour;
                option.setAttribute('aria-selected', String(selected));
            });
            minuteOptions.forEach((option) => {
                const disabled = hour === '24' && option.dataset.value !== '00';
                option.disabled = disabled;
                option.setAttribute('aria-disabled', String(disabled));
                option.setAttribute('aria-selected', String(option.dataset.value === minute));
            });

            const complete = hour !== '' && minute !== '';
            const value = complete ? `${hour}:${minute}` : hour ? `${hour}:--` : minute ? `--:${minute}` : 'Pilih waktu';
            display.textContent = value;
            preview.textContent = complete ? value : '--:--';
            trigger.dataset.empty = String(!complete);
            trigger.setAttribute('aria-label', complete ? `Waktu ${value}, ubah waktu` : 'Pilih waktu');
            if (complete) {
                trigger.removeAttribute('aria-invalid');
                error.hidden = true;
            }
        };

        const choose = (type, value) => {
            if (type === 'hour') {
                hourValue.value = value;
                selectedDuringOpen.add('hour');
                if (value === '24') {
                    minuteValue.value = '00';
                    selectedDuringOpen.add('minute');
                }
            } else {
                if (hourValue.value === '24' && value !== '00') {
                    return;
                }
                minuteValue.value = value;
                selectedDuringOpen.add('minute');
            }

            update();
            if (selectedDuringOpen.has('hour') && selectedDuringOpen.has('minute')) {
                close(true);
            }
        };

        [...hourOptions, ...minuteOptions].forEach((option) => {
            option.addEventListener('click', () => {
                choose(option.dataset.timeOption, option.dataset.value);
            });
        });

        [hourList, minuteList].forEach((list) => {
            list.addEventListener('keydown', (event) => {
                const options = Array.from(list.querySelectorAll('[data-time-option]:not(:disabled)'));
                const currentIndex = options.indexOf(document.activeElement);
                let nextIndex = currentIndex;

                if (event.key === 'ArrowDown') {
                    nextIndex = Math.min(currentIndex + 1, options.length - 1);
                } else if (event.key === 'ArrowUp') {
                    nextIndex = Math.max(currentIndex - 1, 0);
                } else if (event.key === 'Home') {
                    nextIndex = 0;
                } else if (event.key === 'End') {
                    nextIndex = options.length - 1;
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    close(true);
                    return;
                } else if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    document.activeElement?.click();
                    return;
                } else {
                    return;
                }

                event.preventDefault();
                options[nextIndex]?.focus();
            });
        });

        trigger.addEventListener('click', () => {
            if (panel.hidden) {
                open();
            } else {
                close(false);
            }
        });
        trigger.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                open();
            } else if (event.key === 'Escape' && !panel.hidden) {
                event.preventDefault();
                close(true);
            }
        });
        picker.querySelector('[data-time-close]')?.addEventListener('click', () => close(true));
        picker.addEventListener('focusout', (event) => {
            if (!picker.contains(event.relatedTarget)) {
                close(false);
            }
        });
        update();
        pickers.push({ picker, hourValue, minuteValue, trigger, panel, error, focusSelected });
    });

    document.addEventListener('click', (event) => {
        pickers.forEach(({ picker, trigger, panel }) => {
            if (!picker.contains(event.target)) {
                panel.hidden = true;
                trigger.setAttribute('aria-expanded', 'false');
            }
        });
    });

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const invalid = pickers.find(({ picker, hourValue, minuteValue }) =>
                picker.closest('form') === form && (!hourValue.value || !minuteValue.value)
            );
            if (!invalid) {
                return;
            }

            event.preventDefault();
            invalid.panel.hidden = false;
            invalid.trigger.setAttribute('aria-expanded', 'true');
            invalid.trigger.setAttribute('aria-invalid', 'true');
            invalid.error.hidden = false;
            invalid.focusSelected();
        });
    });
});
