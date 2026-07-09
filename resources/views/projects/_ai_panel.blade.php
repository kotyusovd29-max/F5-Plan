<div
    id="project-ai-panel"
    class="ai-panel is-hidden"
    data-generate-url="{{ route('projects.ai.generate', $project) }}"
    data-apply-url="{{ route('projects.ai.apply', $project) }}"
    aria-hidden="true"
>
    <div class="ai-panel__backdrop" data-ai-close></div>

    <div class="ai-panel__dialog" role="dialog" aria-modal="true" aria-labelledby="project-ai-title">
        <header class="ai-panel__header">
            <div>
                <h3 id="project-ai-title" class="ai-panel__title">Заполнить с ИИ</h3>
                <p class="ai-panel__subtitle">F5AI предложит поля, интеграции и воронки по данным компании</p>
            </div>
            <button type="button" class="ai-panel__close" data-ai-close aria-label="Закрыть">×</button>
        </header>

        <div class="ai-panel__body">
            <section class="ai-panel__section" data-ai-step="form">
                <form id="project-ai-form" class="form form--wide">
                    <div class="form__row">
                        <div class="form__group">
                            <label for="ai_company_name" class="form__label">Название компании</label>
                            <input
                                type="text"
                                id="ai_company_name"
                                name="company_name"
                                class="form__input"
                                value="{{ $project->company_name }}"
                                required
                            >
                        </div>

                        <div class="form__group">
                            <label for="ai_employees_count" class="form__label">Количество сотрудников</label>
                            <input
                                type="number"
                                id="ai_employees_count"
                                name="employees_count"
                                class="form__input"
                                value="{{ $project->employees_count }}"
                                min="1"
                            >
                        </div>
                    </div>

                    <div class="form__group">
                        <label for="ai_client_tasks" class="form__label">Задачи клиента</label>
                        <textarea
                            id="ai_client_tasks"
                            name="client_tasks"
                            class="form__textarea"
                            rows="4"
                        >{{ $project->client_tasks }}</textarea>
                    </div>

                    <div class="form__group">
                        <label for="ai_extra_instructions" class="form__label">Дополнительные требования</label>
                        <textarea
                            id="ai_extra_instructions"
                            name="extra_instructions"
                            class="form__textarea"
                            rows="3"
                            placeholder="Например: B2B-продажи, 2 воронки, поля для бюджета и источника лида"
                        ></textarea>
                    </div>

                    <fieldset class="ai-panel__fieldset">
                        <legend class="form__label">Что сгенерировать</legend>
                        <label class="ai-panel__checkbox">
                            <input type="checkbox" name="sections[]" value="skk_fields" checked>
                            <span>Поля (сделки, контакты, компании)</span>
                        </label>
                        <label class="ai-panel__checkbox">
                            <input type="checkbox" name="sections[]" value="integrations" checked>
                            <span>Интеграции и виджеты</span>
                        </label>
                        <label class="ai-panel__checkbox">
                            <input type="checkbox" name="sections[]" value="funnels" checked>
                            <span>Воронки с этапами</span>
                        </label>
                    </fieldset>

                    <p class="ai-panel__note">Новые элементы будут добавлены к существующим, ничего не удалится.</p>

                    <div class="ai-panel__actions">
                        <button type="button" class="btn btn--ghost" data-ai-close>Отмена</button>
                        <button type="submit" class="btn btn--primary" data-ai-generate>
                            Сгенерировать
                        </button>
                    </div>
                </form>
            </section>

            <section class="ai-panel__section is-hidden" data-ai-step="loading">
                <div class="ai-panel__loading">
                    <div class="ai-panel__spinner" aria-hidden="true"></div>
                    <p>ИИ анализирует данные компании…</p>
                    <p class="ai-panel__loading-hint">Обычно это занимает до минуты</p>
                </div>
            </section>

            <section class="ai-panel__section is-hidden" data-ai-step="preview">
                <div class="ai-panel__summary" data-ai-summary></div>
                <div class="ai-panel__preview" data-ai-preview></div>

                <div class="ai-panel__actions">
                    <button type="button" class="btn btn--ghost" data-ai-back>Назад</button>
                    <button type="button" class="btn btn--primary" data-ai-apply>
                        Применить в проект
                    </button>
                </div>
            </section>
        </div>

        <div class="ai-panel__status is-hidden" data-ai-status role="status"></div>
    </div>
</div>
