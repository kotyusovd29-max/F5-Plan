import { initSortableLists } from './sortable-lists';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-toggle]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const targetId = trigger.getAttribute('data-toggle');
            const target = document.getElementById(targetId);

            if (!target) {
                return;
            }

            const isHidden = target.classList.toggle('is-hidden');
            trigger.classList.toggle('is-open', !isHidden);

            if (!isHidden && targetId === 'add-funnel') {
                target.querySelector('input[name="name"]')?.focus();
            }

            if (targetId === 'funnel-settings') {
                trigger.classList.toggle('is-active', !isHidden);
            }

            if (!isHidden && targetId === 'add-integration') {
                target.querySelector('input[name="name"]')?.focus();
            }

            if (targetId.startsWith('add-skk-') && !isHidden) {
                target.querySelector('input[name="name"]')?.focus();
            }
        });
    });

    document.querySelectorAll('[data-toggle-skk-field]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const fieldId = trigger.getAttribute('data-toggle-skk-field');
            const editPanel = document.getElementById(`skk-field-edit-${fieldId}`);
            const block = trigger.closest('[data-skk-field-block]');

            if (!editPanel || !block) {
                return;
            }

            const isHidden = editPanel.classList.toggle('is-hidden');
            block.classList.toggle('integration-block--open', !isHidden);
        });
    });

    document.querySelectorAll('[data-toggle-integration]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const integrationId = trigger.getAttribute('data-toggle-integration');
            const editPanel = document.getElementById(`integration-edit-${integrationId}`);
            const block = trigger.closest('[data-integration-block]');

            if (!editPanel || !block) {
                return;
            }

            const isHidden = editPanel.classList.toggle('is-hidden');
            block.classList.toggle('integration-block--open', !isHidden);
        });
    });

    const newStageForm = document.getElementById('new-stage-form');
    const stageFlow = document.getElementById('stage-flow');

    document.querySelectorAll('[data-show-new-stage]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            if (!newStageForm || !stageFlow) {
                return;
            }

            const afterId = trigger.getAttribute('data-after') || '';
            const afterInput = document.getElementById('after_stage_id');

            if (afterInput) {
                afterInput.value = afterId;
            }

            const anchor = trigger.closest('.funnel-pipeline__col') || trigger.closest('.stage-flow__connector') || trigger;
            anchor.insertAdjacentElement('afterend', newStageForm);
            newStageForm.classList.remove('is-hidden');
            newStageForm.querySelector('#new_stage_name')?.focus();
        });
    });

    document.querySelectorAll('[data-hide-new-stage]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!newStageForm || !stageFlow) {
                return;
            }

            newStageForm.classList.add('is-hidden');
            stageFlow.appendChild(newStageForm);
        });
    });

    document.querySelectorAll('[data-toggle-stage]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const stageId = trigger.getAttribute('data-toggle-stage');
            const editPanel = document.getElementById(`stage-edit-${stageId}`);
            const block = trigger.closest('[data-stage-block]');

            if (!editPanel || !block) {
                return;
            }

            const isHidden = editPanel.classList.toggle('is-hidden');
            block.classList.toggle('stage-block--open', !isHidden);
        });
    });

    document.querySelectorAll('[data-stage-color-picker]').forEach((picker) => {
        const input = picker.querySelector('[data-stage-color-input]');
        const swatches = picker.querySelectorAll('[data-color]');
        const block = picker.closest('[data-stage-block]');

        const setColor = (color) => {
            if (!color || !input) {
                return;
            }

            input.value = color;
            swatches.forEach((swatch) => {
                swatch.classList.toggle('is-selected', swatch.dataset.color?.toLowerCase() === color.toLowerCase());
            });

            if (block) {
                block.style.setProperty('--stage-color', color);
                const bar = block.querySelector('.stage-block__bar');
                if (bar) {
                    bar.style.backgroundColor = color;
                }
            }
        };

        swatches.forEach((swatch) => {
            swatch.addEventListener('click', () => setColor(swatch.dataset.color));
        });

        input?.addEventListener('input', () => setColor(input.value));
    });

    initSortableLists();
});
