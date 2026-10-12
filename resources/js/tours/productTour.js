import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import { router } from '@inertiajs/vue3';

const STORAGE_KEY = 'f5plan.productTour';

export function readTourState() {
    try {
        const raw = sessionStorage.getItem(STORAGE_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

export function writeTourState(state) {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state));
}

export function clearTourState() {
    sessionStorage.removeItem(STORAGE_KEY);
}

function waitForSelector(selector, timeoutMs = 8000) {
    return new Promise((resolve, reject) => {
        const started = Date.now();

        const tick = () => {
            const el = document.querySelector(selector);

            if (el) {
                resolve(el);
                return;
            }

            if (Date.now() - started > timeoutMs) {
                reject(new Error(`Элемент не найден: ${selector}`));
                return;
            }

            window.requestAnimationFrame(tick);
        };

        tick();
    });
}

function visit(url) {
    return new Promise((resolve) => {
        router.visit(url, {
            preserveState: false,
            preserveScroll: false,
            onFinish: () => resolve(),
        });
    });
}

function buildSteps(training) {
    return [
        {
            id: 'kb-start',
            routeHint: '/knowledge',
            element: '[data-tour="kb-training-start"]',
            popover: {
                title: 'Интерактивное обучение',
                description: 'Пройдём по учебному проекту: карточка клиента, воронки, поля, интеграции, ИИ и доска. В любой момент можно нажать «Пропустить» или крестик.',
                side: 'bottom',
                align: 'start',
            },
        },
        {
            id: 'nav-projects',
            element: '[data-tour="nav-projects"]',
            popover: {
                title: 'Раздел «Проекты»',
                description: 'Здесь список ваших планов внедрения. Учебный проект открывается из базы знаний и не мешает обычным спискам.',
                side: 'right',
                align: 'start',
            },
        },
        {
            id: 'project-nav',
            url: training.show_url,
            element: '[data-tour="project-nav"]',
            popover: {
                title: 'Навигация по проекту',
                description: 'Вкладки: «Проект» (редактор), «ИИ-чат», «Документы» и «Доска». Экспорт DOCX/XLSX — справа.',
                side: 'bottom',
                align: 'start',
            },
        },
        {
            id: 'project-company',
            url: training.show_url,
            element: '#project-company',
            popover: {
                title: 'Карточка компании',
                description: 'База плана: название, сайт, сфера, задачи клиента. Чем полнее бриф, тем полезнее подсказки ИИ.',
                side: 'bottom',
                align: 'start',
            },
        },
        {
            id: 'project-funnels',
            url: training.show_url,
            element: '#project-funnels',
            popover: {
                title: 'Воронки и этапы',
                description: 'Описывайте этапы продаж, автозадачи менеджера и SLA. Можно открыть отдельную доску воронки.',
                side: 'top',
                align: 'start',
            },
        },
        {
            id: 'project-skk',
            url: training.show_url,
            element: '#project-skk',
            popover: {
                title: 'Поля CRM',
                description: 'Поля сделок, контактов и компаний. Здесь задаются типы, обязательность и отображение в карточке.',
                side: 'top',
                align: 'start',
            },
        },
        {
            id: 'project-integrations',
            url: training.show_url,
            element: '#project-integrations',
            popover: {
                title: 'Интеграции и виджеты',
                description: 'Каталог виджетов F5 и сценарии: телефония, мессенджеры, таблицы и другие подключения.',
                side: 'top',
                align: 'start',
            },
        },
        {
            id: 'project-work-stages',
            url: training.show_url,
            element: '#project-work-stages',
            popover: {
                title: 'Этапы работ и смета',
                description: 'Состав работ, сроки и стоимость. Отсюда удобно готовить коммерческое предложение.',
                side: 'top',
                align: 'start',
            },
        },
        {
            id: 'project-ai',
            url: training.show_url,
            element: '[data-tour="project-ai"]',
            popover: {
                title: 'ИИ-помощник',
                description: 'Аудит плана, автозадачи, разбор ТЗ/КП. Можно писать в чат и прикреплять файлы.',
                side: 'left',
                align: 'start',
            },
        },
        {
            id: 'ai-chat',
            url: training.ai_chat_url,
            element: '[data-tour="ai-chat-panel"]',
            popover: {
                title: 'ИИ-чат проекта',
                description: 'Два потока: план проекта и подготовка встречи. ИИ предлагает изменения — их можно применить одной кнопкой.',
                side: 'top',
                align: 'center',
            },
        },
        {
            id: 'canvas',
            url: training.canvas_url,
            element: '[data-tour="project-canvas"]',
            popover: {
                title: 'Доска проекта',
                description: 'Визуальная схема блоков: воронки, поля и интеграции на одном холсте для обсуждения с клиентом.',
                side: 'top',
                align: 'center',
            },
        },
        {
            id: 'finish',
            url: '/knowledge',
            element: '[data-tour="kb-training-start"]',
            popover: {
                title: 'Обучение завершено',
                description: 'Можно вернуться к учебному проекту или создать свой план на главной. База знаний всегда под рукой слева.',
                side: 'bottom',
                align: 'start',
            },
        },
    ];
}

let activeDriver = null;
let tourRunning = false;

export function stopProductTour() {
    tourRunning = false;

    if (activeDriver) {
        try {
            activeDriver.destroy();
        } catch {
            // ignore
        }
        activeDriver = null;
    }

    clearTourState();
}

export async function startProductTour(training, { fromStep = 0 } = {}) {
    if (!training?.show_url) {
        return;
    }

    if (activeDriver) {
        try {
            activeDriver.destroy();
        } catch {
            // ignore
        }
        activeDriver = null;
    }

    const steps = buildSteps(training);
    const startIndex = Math.max(0, Math.min(fromStep, steps.length - 1));
    tourRunning = true;

    writeTourState({ active: true, step: startIndex });

    const runFrom = async (index) => {
        if (!tourRunning) {
            return;
        }

        if (index >= steps.length) {
            stopProductTour();
            return;
        }

        const step = steps[index];
        const isLast = index === steps.length - 1;
        writeTourState({ active: true, step: index });

        if (step.url) {
            const targetPath = new URL(step.url, window.location.origin).pathname;

            if (!window.location.pathname.startsWith(targetPath)) {
                await visit(step.url);
            }
        }

        try {
            await waitForSelector(step.element);
        } catch {
            await runFrom(index + 1);
            return;
        }

        if (!tourRunning) {
            return;
        }

        activeDriver = driver({
            showProgress: true,
            animate: true,
            allowClose: true,
            overlayColor: 'rgba(15, 23, 20, 0.55)',
            stagePadding: 8,
            stageRadius: 12,
            nextBtnText: isLast ? 'Готово' : 'Далее',
            prevBtnText: 'Назад',
            doneBtnText: 'Готово',
            progressText: `${index + 1} из ${steps.length}`,
            onDestroyStarted: (_el, _step, opts) => {
                // User cancelled via overlay/close — stop the whole tour.
                if (!tourRunning) {
                    opts.driver.destroy();
                    return;
                }

                // If destroy came from Next/Prev handlers we already cleared activeDriver.
                if (activeDriver === opts.driver) {
                    tourRunning = false;
                    activeDriver = null;
                    clearTourState();
                    opts.driver.destroy();
                }
            },
            steps: [{
                element: step.element,
                popover: {
                    title: `${step.popover.title} (${index + 1}/${steps.length})`,
                    description: `${step.popover.description} Можно закрыть тур в любой момент (✕ или Esc).`,
                    side: step.popover.side,
                    align: step.popover.align,
                    onNextClick: async (_el, _step, opts) => {
                        const current = opts.driver;
                        activeDriver = null;
                        current.destroy();
                        await runFrom(index + 1);
                    },
                    onPrevClick: async (_el, _step, opts) => {
                        const current = opts.driver;
                        activeDriver = null;
                        current.destroy();
                        await runFrom(Math.max(0, index - 1));
                    },
                    onCloseClick: (_el, _step, opts) => {
                        tourRunning = false;
                        activeDriver = null;
                        clearTourState();
                        opts.driver.destroy();
                    },
                },
            }],
        });

        activeDriver.drive();
    };

    await runFrom(startIndex);
}

export async function resumeProductTourIfNeeded(training) {
    if (tourRunning || activeDriver) {
        return;
    }

    const state = readTourState();

    if (!state?.active || !training?.show_url) {
        return;
    }

    await startProductTour(training, { fromStep: state.step ?? 0 });
}
