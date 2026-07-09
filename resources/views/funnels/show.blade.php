@extends('layouts.app')

@section('title', $funnel->name . ' — ' . $project->company_name)
@section('body_class', 'page--funnel')
@section('main_class', 'main--funnel')
@section('container_class', 'container--fluid')

@section('content')
    <div class="funnel-page">
        <header class="funnel-page__toolbar">
            <div class="funnel-page__toolbar-main">
                <a href="{{ route('projects.show', $project) }}" class="funnel-page__back">
                    ← {{ $project->company_name }}
                </a>
                <div class="funnel-page__title-wrap">
                    <h1 class="funnel-page__title">{{ $funnel->name }}</h1>
                    <p class="funnel-page__meta">
                        {{ $funnel->stages->count() }} этапов
                        @if ($funnel->description)
                            · {{ Str::limit($funnel->description, 120) }}
                        @endif
                    </p>
                </div>
            </div>

            <div class="funnel-page__toolbar-actions">
                <button
                    type="button"
                    class="btn btn--ghost btn--sm funnel-page__settings-btn {{ old('form_type') === 'funnel_settings' ? 'is-active' : '' }}"
                    data-toggle="funnel-settings"
                >
                    Настройки воронки
                </button>
                <a
                    href="{{ route('projects.funnels.canvas.show', [$project, $funnel]) }}"
                    class="btn btn--ghost btn--sm"
                >
                    Канвас
                </a>
            </div>
        </header>

        <section
            class="funnel-page__settings {{ old('form_type') === 'funnel_settings' ? '' : 'is-hidden' }}"
            id="funnel-settings"
        >
            <div class="funnel-page__settings-inner">
                <form
                    id="funnel-settings-form"
                    method="POST"
                    action="{{ route('projects.funnels.update', [$project, $funnel]) }}"
                    class="form funnel-page__settings-form"
                >
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="form_type" value="funnel_settings">

                    <div class="funnel-page__settings-grid">
                        <div class="form__group funnel-page__settings-field">
                            <label for="name" class="form__label funnel-page__settings-label">Название воронки</label>
                            <input
                                type="text"
                                id="name"
                                name="name"
                                class="form__input funnel-page__settings-input @error('name') form__input--error @enderror"
                                value="{{ old('name', $funnel->name) }}"
                                required
                            >
                            @error('name')
                                @if (old('form_type') === 'funnel_settings')
                                    <span class="form__error">{{ $message }}</span>
                                @endif
                            @enderror
                        </div>

                        <div class="form__group funnel-page__settings-field funnel-page__settings-description">
                            <label for="description" class="form__label funnel-page__settings-label">Информация по воронке</label>
                            <textarea
                                id="description"
                                name="description"
                                class="funnel-page__settings-textarea @error('description') form__input--error @enderror"
                                rows="6"
                                placeholder="Опишите назначение воронки, тип клиентов, особенности процесса..."
                            >{{ old('description', $funnel->description) }}</textarea>
                            @error('description')
                                @if (old('form_type') === 'funnel_settings')
                                    <span class="form__error">{{ $message }}</span>
                                @endif
                            @enderror
                        </div>
                    </div>
                </form>

                <div class="funnel-page__settings-actions">
                    <button type="submit" class="btn btn--primary btn--sm" form="funnel-settings-form">Сохранить воронку</button>
                    <form
                        method="POST"
                        action="{{ route('projects.funnels.destroy', [$project, $funnel]) }}"
                        class="funnel-page__settings-delete"
                        onsubmit="return confirm('Удалить воронку «{{ $funnel->name }}»? Все этапы будут удалены.')"
                    >
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn--danger btn--sm">Удалить воронку</button>
                    </form>
                </div>
            </div>
        </section>

        <section class="funnel-page__pipeline-wrap">
            <div class="funnel-page__pipeline-head">
                <div>
                    <h2 class="funnel-page__pipeline-title">Этапы воронки</h2>
                    <p class="funnel-page__pipeline-hint">Перетащите этап за ⠿ · нажмите на карточку для редактирования · цвет полосы задаётся в настройках этапа</p>
                </div>
            </div>

            <div
                class="funnel-pipeline"
                id="stage-flow"
                data-sortable-list
                data-sortable-direction="horizontal"
                data-reorder-url="{{ route('projects.funnels.stages.reorder', [$project, $funnel]) }}"
            >
                @if ($funnel->stages->isEmpty())
                    <div class="funnel-pipeline__empty">
                        <p class="funnel-pipeline__empty-text">Этапов пока нет. Добавьте первый этап воронки.</p>
                        <button type="button" class="btn btn--primary" data-show-new-stage data-after="">
                            Создать этап
                        </button>
                    </div>
                @else
                    @foreach ($funnel->stages as $index => $stage)
                        <div class="funnel-pipeline__col sortable-item" data-sortable-id="{{ $stage->id }}">
                            @include('funnels._stage', [
                                'stage' => $stage,
                                'number' => $index + 1,
                                'project' => $project,
                                'funnel' => $funnel,
                                'isOpen' => (string) old('editing_stage_id') === (string) $stage->id,
                            ])

                            <button
                                type="button"
                                class="funnel-pipeline__add"
                                data-show-new-stage
                                data-after="{{ $stage->id }}"
                                title="Добавить этап после «{{ $stage->name }}»"
                                aria-label="Добавить этап после «{{ $stage->name }}»"
                            >
                                +
                            </button>
                        </div>
                    @endforeach
                @endif

                <div
                    id="new-stage-form"
                    class="stage-form-new {{ old('form_type') === 'new_stage' ? '' : 'is-hidden' }}"
                >
                    @include('funnels._stage_form_new', ['project' => $project, 'funnel' => $funnel])
                </div>
            </div>
        </section>
    </div>

    @if (old('form_type') === 'new_stage')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const form = document.getElementById('new-stage-form');
                const afterId = @json(old('after_stage_id', ''));
                const trigger = document.querySelector(`[data-show-new-stage][data-after="${afterId}"]`)
                    || document.querySelector('[data-show-new-stage]');

                if (trigger && form) {
                    const anchor = trigger.closest('.funnel-pipeline__col') || trigger;
                    anchor.insertAdjacentElement('afterend', form);
                    form.classList.remove('is-hidden');
                }
            });
        </script>
    @endif
@endsection
