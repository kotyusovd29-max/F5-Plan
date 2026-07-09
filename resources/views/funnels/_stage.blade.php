@php
    $barColor = $stage->barColor();
    $actionText = trim((string) $stage->action);
@endphp

<article
    class="stage-block {{ $isOpen ? 'stage-block--open' : '' }}"
    data-stage-block="{{ $stage->id }}"
    style="--stage-color: {{ $barColor }}"
>
    <div class="stage-block__stack">
        <div class="stage-block__card">
            <div class="stage-block__head">
                <span class="sortable-handle sortable-handle--stage" title="Перетащить" aria-label="Перетащить">⠿</span>
                <button type="button" class="stage-block__toggle" data-toggle-stage="{{ $stage->id }}">
                    <span class="stage-block__number">Этап {{ $number }}</span>
                    <span class="stage-block__name">{{ $stage->name }}</span>
                    <span class="stage-block__icon" aria-hidden="true">▼</span>
                </button>
            </div>
        </div>

        <button type="button" class="stage-block__action" data-toggle-stage="{{ $stage->id }}" tabindex="0">
            <span class="stage-block__action-label">Действие на этапе</span>
            @if ($actionText !== '')
                <span class="stage-block__action-text">{{ $actionText }}</span>
            @else
                <span class="stage-block__action-text stage-block__action-text--empty">Не указано — нажмите, чтобы добавить</span>
            @endif
        </button>

        <div class="stage-block__bar" style="background-color: {{ $barColor }}" aria-hidden="true"></div>
    </div>

    <div class="stage-block__edit {{ $isOpen ? '' : 'is-hidden' }}" id="stage-edit-{{ $stage->id }}">
            <form
                method="POST"
                action="{{ route('projects.funnels.stages.update', [$project, $funnel, $stage]) }}"
                class="form"
            >
                @csrf
                @method('PUT')
                <input type="hidden" name="editing_stage_id" value="{{ $stage->id }}">

                <div class="form__group">
                    <label for="stage_name_{{ $stage->id }}" class="form__label">Название этапа</label>
                    <input
                        type="text"
                        id="stage_name_{{ $stage->id }}"
                        name="name"
                        class="form__input @error('name') form__input--error @enderror"
                        value="{{ old('name', $stage->name) }}"
                        required
                    >
                    @error('name')
                        @if ((string) old('editing_stage_id') === (string) $stage->id)
                            <span class="form__error">{{ $message }}</span>
                        @endif
                    @enderror
                </div>

                @include('funnels._stage_color_picker', [
                    'value' => $barColor,
                    'pickerId' => 'stage_color_'.$stage->id,
                ])

                <div class="form__group">
                    <label for="stage_action_{{ $stage->id }}" class="form__label">Действие на этапе</label>
                    <textarea
                        id="stage_action_{{ $stage->id }}"
                        name="action"
                        class="form__textarea"
                        rows="3"
                        placeholder="Что происходит на этом этапе..."
                    >{{ old('action', $stage->action) }}</textarea>
                </div>

                <div class="form__group">
                    <label for="stage_comment_{{ $stage->id }}" class="form__label">Комментарий к этапу</label>
                    <textarea
                        id="stage_comment_{{ $stage->id }}"
                        name="comment"
                        class="form__textarea"
                        rows="3"
                        placeholder="Дополнительные заметки, инструменты, ответственные..."
                    >{{ old('comment', $stage->comment) }}</textarea>
                </div>

                <div class="stage-block__actions">
                    <button type="submit" class="btn btn--primary btn--sm">Сохранить этап</button>
                </div>
            </form>

            <form
                method="POST"
                action="{{ route('projects.funnels.stages.destroy', [$project, $funnel, $stage]) }}"
                class="stage-block__delete-form"
                onsubmit="return confirm('Удалить этап «{{ $stage->name }}»?')"
            >
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn--danger btn--sm">Удалить этап</button>
            </form>
    </div>
</article>
