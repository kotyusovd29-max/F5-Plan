<article
    class="integration-block sortable-item {{ $isOpen ? 'integration-block--open' : '' }}"
    data-sortable-id="{{ $field->id }}"
    data-skk-field-block="{{ $field->id }}"
    data-drag-delete-url="{{ route('projects.skk-fields.destroy', [$project, $field]) }}"
    data-drag-delete-label="{{ $field->name }}"
>
    <div class="integration-block__head">
        <span class="sortable-handle" title="Перетащить" aria-label="Перетащить">⠿</span>
        <button type="button" class="integration-block__toggle" data-toggle-skk-field="{{ $field->id }}">
        <span class="integration-block__name">{{ $field->name }}</span>
        @if ($field->comment)
            <span class="integration-block__preview">{{ Str::limit($field->comment, 100) }}</span>
        @endif
        <span class="integration-block__icon" aria-hidden="true">▼</span>
        </button>
    </div>

    <div class="integration-block__edit {{ $isOpen ? '' : 'is-hidden' }}" id="skk-field-edit-{{ $field->id }}">
        <form
            method="POST"
            action="{{ route('projects.skk-fields.update', [$project, $field]) }}"
            class="form"
        >
            @csrf
            @method('PUT')
            <input type="hidden" name="editing_skk_field_id" value="{{ $field->id }}">

            <div class="form__group">
                <label for="skk_field_name_{{ $field->id }}" class="form__label">Название поля</label>
                <input
                    type="text"
                    id="skk_field_name_{{ $field->id }}"
                    name="name"
                    class="form__input @error('name') form__input--error @enderror"
                    value="{{ old('name', $field->name) }}"
                    required
                >
                @error('name')
                    @if ((string) old('editing_skk_field_id') === (string) $field->id)
                        <span class="form__error">{{ $message }}</span>
                    @endif
                @enderror
            </div>

            <div class="form__group">
                <label for="skk_field_comment_{{ $field->id }}" class="form__label">Комментарий</label>
                <textarea
                    id="skk_field_comment_{{ $field->id }}"
                    name="comment"
                    class="form__textarea"
                    rows="3"
                    placeholder="Тип поля, обязательность, значения..."
                >{{ old('comment', $field->comment) }}</textarea>
            </div>

            <button type="submit" class="btn btn--primary btn--sm">Сохранить</button>
        </form>

        <form
            method="POST"
            action="{{ route('projects.skk-fields.destroy', [$project, $field]) }}"
            class="integration-block__delete-form"
            onsubmit="return confirm('Удалить поле «{{ $field->name }}»?')"
        >
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn--danger btn--sm">Удалить</button>
        </form>
    </div>
</article>
