@extends('layouts.app')

@section('title', $project->company_name . ' — ' . config('app.name'))

@section('content')
    <div class="project-page">
        <div class="page-header">
        <div>
            <h2 class="page-header__title">{{ $project->company_name }}</h2>
            <p class="page-header__subtitle">
                <a href="{{ route('home') }}">← К проектам</a>
                · Автор: {{ $project->user->name }}
            </p>
        </div>
        <div class="page-header__actions">
            <a href="{{ route('projects.ai.chat', $project) }}" class="btn btn--ghost">
                ИИ-чат
            </a>
            <a href="{{ route('projects.canvas.show', $project) }}" class="btn btn--ghost">
                Доска
            </a>
            <a href="{{ route('projects.export.docx', $project) }}" class="btn btn--primary">
                Сохранить в DOCX
            </a>
        </div>
    </div>

    @include('projects._project_nav', ['project' => $project, 'current' => 'project'])

    <div class="project-workspace project-workspace--open">
        <section class="workspace-card workspace-card--primary">
            <header class="workspace-card__header">
                <div>
                    <h3 class="workspace-card__title">Основная информация</h3>
                    <p class="workspace-card__subtitle">Название компании, команда и задачи клиента</p>
                </div>
            </header>

            <div class="workspace-card__body">
                <div class="project-summary">
                    <div class="project-summary__item">
                        <span class="project-summary__label">Компания</span>
                        <span class="project-summary__value">{{ $project->company_name }}</span>
                    </div>
                    <div class="project-summary__item">
                        <span class="project-summary__label">Сотрудников</span>
                        <span class="project-summary__value">{{ $project->employees_count ?? '—' }}</span>
                    </div>
                    <div class="project-summary__item project-summary__item--wide">
                        <span class="project-summary__label">Задачи клиента</span>
                        <span class="project-summary__value">{{ $project->client_tasks ? Str::limit($project->client_tasks, 160) : '—' }}</span>
                    </div>
                </div>

                <form method="POST" action="{{ route('projects.update', $project) }}" class="form form--wide workspace-form">
                    @csrf
                    @method('PUT')

                    <div class="form__row">
                        <div class="form__group">
                            <label for="company_name" class="form__label">Название компании</label>
                            <input
                                type="text"
                                id="company_name"
                                name="company_name"
                                class="form__input @error('company_name') form__input--error @enderror"
                                value="{{ old('company_name', $project->company_name) }}"
                                required
                            >
                            @error('company_name')
                                <span class="form__error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form__group">
                            <label for="employees_count" class="form__label">Количество сотрудников</label>
                            <input
                                type="number"
                                id="employees_count"
                                name="employees_count"
                                class="form__input @error('employees_count') form__input--error @enderror"
                                value="{{ old('employees_count', $project->employees_count) }}"
                                min="1"
                            >
                            @error('employees_count')
                                <span class="form__error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="form__group">
                        <label for="client_tasks" class="form__label">Задачи клиента</label>
                        <textarea
                            id="client_tasks"
                            name="client_tasks"
                            class="form__textarea @error('client_tasks') form__input--error @enderror"
                            rows="5"
                        >{{ old('client_tasks', $project->client_tasks) }}</textarea>
                        @error('client_tasks')
                            <span class="form__error">{{ $message }}</span>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn--primary">Сохранить</button>
                </form>
            </div>
        </section>

        <section class="workspace-card">
            <header class="workspace-card__header">
                <div>
                    <h3 class="workspace-card__title">{{ \App\Models\ProjectSkkField::SECTION_LABEL }}</h3>
                    <p class="workspace-card__subtitle">Сделки, контакты и компании</p>
                </div>
            </header>

            <div class="workspace-card__body workspace-card__body--flush" id="project-skk">
                @php
                    $skkByType = $project->skkFields->groupBy('entity_type');
                @endphp

                <div class="workspace-grid workspace-grid--skk">
                    @include('projects._skk_section', [
                        'entityType' => 'deals',
                        'label' => 'Сделки',
                        'project' => $project,
                        'fields' => $skkByType->get('deals', collect()),
                    ])

                    @include('projects._skk_section', [
                        'entityType' => 'contacts',
                        'label' => 'Контакты',
                        'project' => $project,
                        'fields' => $skkByType->get('contacts', collect()),
                    ])

                    @include('projects._skk_section', [
                        'entityType' => 'companies',
                        'label' => 'Компании',
                        'project' => $project,
                        'fields' => $skkByType->get('companies', collect()),
                    ])
                </div>
            </div>
        </section>

        <section class="workspace-card">
            <header class="workspace-card__header">
                <div>
                    <h3 class="workspace-card__title">Интеграции и виджеты</h3>
                    <p class="workspace-card__subtitle">Подключённые сервисы и дополнения</p>
                </div>
                <button type="button" class="btn btn--primary btn--sm" data-toggle="add-integration">
                    Добавить
                </button>
            </header>

            <div class="workspace-card__body workspace-card__body--flush">
                <div class="workspace-card__inline-form {{ old('form_type') === 'new_integration' ? '' : 'is-hidden' }}" id="add-integration">
                    <form method="POST" action="{{ route('projects.integrations.store', $project) }}" class="form form--wide">
                        @csrf
                        <input type="hidden" name="form_type" value="new_integration">

                        <div class="form__group">
                            <label for="integration_name" class="form__label">Название интеграции или виджета</label>
                            <input
                                type="text"
                                id="integration_name"
                                name="name"
                                class="form__input @error('name') form__input--error @enderror"
                                value="{{ old('name') }}"
                                placeholder="Например: Телефония, WhatsApp"
                                required
                            >
                            @error('name')
                                @if (old('form_type') === 'new_integration')
                                    <span class="form__error">{{ $message }}</span>
                                @endif
                            @enderror
                        </div>

                        <div class="form__group">
                            <label for="integration_comment" class="form__label">Комментарий</label>
                            <textarea
                                id="integration_comment"
                                name="comment"
                                class="form__textarea"
                                rows="4"
                                placeholder="Для чего используется, настройки..."
                            >{{ old('comment') }}</textarea>
                        </div>

                        <button type="submit" class="btn btn--primary">Добавить</button>
                    </form>
                </div>

                @if ($project->integrations->isEmpty())
                    <p class="workspace-card__empty">Интеграций и виджетов пока нет.</p>
                @else
                    <div
                        class="integration-list integration-list--panel"
                        data-sortable-list
                        data-drag-delete="1"
                        data-reorder-url="{{ route('projects.integrations.reorder', $project) }}"
                        data-empty-text="Интеграций и виджетов пока нет."
                        data-empty-class="workspace-card__empty"
                    >
                        @foreach ($project->integrations as $integration)
                            @include('projects._integration', [
                                'integration' => $integration,
                                'project' => $project,
                                'isOpen' => (string) old('editing_integration_id') === (string) $integration->id,
                            ])
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        <section class="workspace-card">
            <header class="workspace-card__header">
                <div>
                    <h3 class="workspace-card__title">Воронки</h3>
                    <p class="workspace-card__subtitle">Этапы продаж и бизнес-процессы</p>
                </div>
                <button type="button" class="btn btn--primary btn--sm" data-toggle="add-funnel">
                    Добавить воронку
                </button>
            </header>

            <div class="workspace-card__body">
                <div class="workspace-card__inline-form {{ $errors->has('name') && ! old('form_type') ? '' : 'is-hidden' }}" id="add-funnel">
                    <form method="POST" action="{{ route('projects.funnels.store', $project) }}" class="form form--wide">
                        @csrf

                        <div class="form__group">
                            <label for="funnel_name" class="form__label">Название воронки</label>
                            <input
                                type="text"
                                id="funnel_name"
                                name="name"
                                class="form__input @error('name') form__input--error @enderror"
                                value="{{ old('name') }}"
                                placeholder="Например: Продажи"
                                required
                            >
                            @error('name')
                                <span class="form__error">{{ $message }}</span>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn--primary">Создать воронку</button>
                    </form>
                </div>

                @if ($project->funnels->isEmpty())
                    <p class="workspace-card__empty">Воронок пока нет. Нажмите «Добавить воронку».</p>
                @else
                    <div class="funnel-cards">
                        @foreach ($project->funnels as $funnel)
                            @php
                                $stagesCount = $funnel->stages_count;
                                $stagesMod10 = $stagesCount % 10;
                                $stagesMod100 = $stagesCount % 100;
                                $stagesLabel = ($stagesMod10 === 1 && $stagesMod100 !== 11)
                                    ? 'этап'
                                    : (($stagesMod10 >= 2 && $stagesMod10 <= 4 && ($stagesMod100 < 10 || $stagesMod100 >= 20)) ? 'этапа' : 'этапов');
                            @endphp
                            <article class="funnel-card">
                                <a href="{{ route('projects.funnels.show', [$project, $funnel]) }}" class="funnel-card__main">
                                    <h4 class="funnel-card__name">{{ $funnel->name }}</h4>
                                    <p class="funnel-card__meta">{{ $stagesCount }} {{ $stagesLabel }}</p>
                                </a>
                                <div class="funnel-card__actions">
                                    <a
                                        href="{{ route('projects.funnels.canvas.show', [$project, $funnel]) }}"
                                        class="btn btn--ghost btn--sm funnel-card__canvas"
                                    >
                                        Доска
                                    </a>
                                    <form
                                        method="POST"
                                        action="{{ route('projects.funnels.destroy', [$project, $funnel]) }}"
                                        class="funnel-card__delete"
                                        onsubmit="return confirm('Удалить воронку «{{ $funnel->name }}»? Все этапы будут удалены.')"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn--danger btn--sm" title="Удалить воронку">×</button>
                                    </form>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    </div>
    </div>

    <div class="drag-trash is-hidden" id="project-trash" aria-hidden="true">
        <div class="drag-trash__inner">
            <span class="drag-trash__icon" aria-hidden="true">🗑</span>
            <span class="drag-trash__label">Перетащите сюда для удаления</span>
        </div>
    </div>

    @if ($errors->any() || old('form_type') || old('editing_skk_field_id') || old('editing_integration_id'))
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                @if (old('editing_skk_field_id'))
                    const skkFieldId = '{{ old('editing_skk_field_id') }}';
                    document.getElementById(`skk-field-edit-${skkFieldId}`)?.classList.remove('is-hidden');
                    document.querySelector(`[data-skk-field-block="${skkFieldId}"]`)?.classList.add('integration-block--open');
                @elseif (old('form_type') === 'new_skk_field' && old('entity_type'))
                    document.getElementById('add-skk-{{ old('entity_type') }}')?.classList.remove('is-hidden');
                @elseif (old('editing_integration_id'))
                    const integrationId = '{{ old('editing_integration_id') }}';
                    document.getElementById(`integration-edit-${integrationId}`)?.classList.remove('is-hidden');
                    document.querySelector(`[data-integration-block="${integrationId}"]`)?.classList.add('integration-block--open');
                @elseif (old('form_type') === 'new_integration')
                    document.getElementById('add-integration')?.classList.remove('is-hidden');
                @elseif ($errors->has('name') && !old('form_type'))
                    document.getElementById('add-funnel')?.classList.remove('is-hidden');
                @endif
            });
        </script>
    @endif
@endsection
