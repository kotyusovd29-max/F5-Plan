<article
    class="integration-block sortable-item {{ $isOpen ? 'integration-block--open' : '' }}"
    data-sortable-id="{{ $integration->id }}"
    data-integration-block="{{ $integration->id }}"
    data-drag-delete-url="{{ route('projects.integrations.destroy', [$project, $integration]) }}"
    data-drag-delete-label="{{ $integration->name }}"
>
    <div class="integration-block__head">
        <span class="sortable-handle" title="Перетащить" aria-label="Перетащить">⠿</span>
        <button type="button" class="integration-block__toggle" data-toggle-integration="{{ $integration->id }}">
        <span class="integration-block__name">{{ $integration->name }}</span>
        @if ($integration->comment)
            <span class="integration-block__preview">{{ Str::limit($integration->comment, 100) }}</span>
        @endif
        <span class="integration-block__icon" aria-hidden="true">▼</span>
        </button>
    </div>

    <div class="integration-block__edit {{ $isOpen ? '' : 'is-hidden' }}" id="integration-edit-{{ $integration->id }}">
        <form
            method="POST"
            action="{{ route('projects.integrations.update', [$project, $integration]) }}"
            class="form"
        >
            @csrf
            @method('PUT')
            <input type="hidden" name="editing_integration_id" value="{{ $integration->id }}">

            <div class="form__group">
                <label for="integration_name_{{ $integration->id }}" class="form__label">Название</label>
                <input
                    type="text"
                    id="integration_name_{{ $integration->id }}"
                    name="name"
                    class="form__input @error('name') form__input--error @enderror"
                    value="{{ old('name', $integration->name) }}"
                    required
                >
                @error('name')
                    @if ((string) old('editing_integration_id') === (string) $integration->id)
                        <span class="form__error">{{ $message }}</span>
                    @endif
                @enderror
            </div>

            <div class="form__group">
                <label for="integration_comment_{{ $integration->id }}" class="form__label">Комментарий</label>
                <textarea
                    id="integration_comment_{{ $integration->id }}"
                    name="comment"
                    class="form__textarea"
                    rows="4"
                    placeholder="Для чего используется, настройки..."
                >{{ old('comment', $integration->comment) }}</textarea>
            </div>

            <button type="submit" class="btn btn--primary btn--sm">Сохранить</button>
        </form>

        <form
            method="POST"
            action="{{ route('projects.integrations.destroy', [$project, $integration]) }}"
            class="integration-block__delete-form"
            onsubmit="return confirm('Удалить «{{ $integration->name }}»?')"
        >
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn--danger btn--sm">Удалить</button>
        </form>
    </div>
</article>
