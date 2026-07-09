@php
    $addFormId = 'add-skk-' . $entityType;
    $isAddOpen = old('form_type') === 'new_skk_field' && old('entity_type') === $entityType;
@endphp

<div class="skk-section skk-section--card">
    <div class="skk-section__header">
        <h4 class="skk-section__title">{{ $label }}</h4>
        <button type="button" class="btn btn--primary btn--sm" data-toggle="{{ $addFormId }}">
            Добавить поле
        </button>
    </div>

    <div class="skk-section__add-form {{ $isAddOpen ? '' : 'is-hidden' }}" id="{{ $addFormId }}">
        <form method="POST" action="{{ route('projects.skk-fields.store', $project) }}" class="form">
            @csrf
            <input type="hidden" name="form_type" value="new_skk_field">
            <input type="hidden" name="entity_type" value="{{ $entityType }}">

            <div class="form__group">
                <label for="skk_name_{{ $entityType }}" class="form__label">Название поля</label>
                <input
                    type="text"
                    id="skk_name_{{ $entityType }}"
                    name="name"
                    class="form__input @error('name') form__input--error @enderror"
                    value="{{ $isAddOpen ? old('name') : '' }}"
                    placeholder="Например: Бюджет, Источник"
                    required
                >
                @error('name')
                    @if ($isAddOpen)
                        <span class="form__error">{{ $message }}</span>
                    @endif
                @enderror
            </div>

            <div class="form__group">
                <label for="skk_comment_{{ $entityType }}" class="form__label">Комментарий</label>
                <textarea
                    id="skk_comment_{{ $entityType }}"
                    name="comment"
                    class="form__textarea"
                    rows="3"
                    placeholder="Тип поля, обязательность, значения..."
                >{{ $isAddOpen ? old('comment') : '' }}</textarea>
            </div>

            <button type="submit" class="btn btn--primary btn--sm">Добавить</button>
        </form>
    </div>

    @if ($fields->isEmpty())
        <p class="skk-section__empty">Поля не добавлены.</p>
    @else
        <div
            class="integration-list"
            data-sortable-list
            data-drag-delete="1"
            data-reorder-url="{{ route('projects.skk-fields.reorder', $project) }}"
            data-entity-type="{{ $entityType }}"
            data-empty-text="Поля не добавлены."
            data-empty-class="skk-section__empty"
        >
            @foreach ($fields as $field)
                @include('projects._skk_field', [
                    'field' => $field,
                    'project' => $project,
                    'isOpen' => (string) old('editing_skk_field_id') === (string) $field->id,
                ])
            @endforeach
        </div>
    @endif
</div>
