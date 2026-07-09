<article
    class="funnel-stage-block"
    data-stage-block="{{ $stage->id }}"
    data-stage-id="{{ $stage->id }}"
    data-sort-order="{{ $stage->sort_order }}"
    style="left: {{ $stage->canvas_pos_x }}px; top: {{ $stage->canvas_pos_y }}px;"
>
    <header class="funnel-stage-block__header" data-canvas-drag-handle>
        <span class="funnel-stage-block__number">Этап {{ $number }}</span>
        <span class="funnel-stage-block__title">{{ $stage->name }}</span>
        <span class="funnel-stage-block__drag" aria-hidden="true">⠿</span>
    </header>

    <div class="funnel-stage-block__body" data-canvas-no-drag>
        <button type="button" class="funnel-stage-block__toggle" data-canvas-item-toggle>
            Редактировать этап
            <span class="canvas-item__icon" aria-hidden="true">▼</span>
        </button>

        <div class="canvas-item__panel is-hidden">
            <form
                method="POST"
                action="{{ route('projects.funnels.stages.update', [$project, $funnel, $stage]) }}"
                class="form form--canvas"
                data-canvas-form
            >
                @csrf
                @method('PUT')
                <div class="form__group">
                    <label class="form__label">Название</label>
                    <input type="text" name="name" class="form__input" value="{{ $stage->name }}" required>
                </div>
                <div class="form__group">
                    <label class="form__label">Действие</label>
                    <textarea name="action" class="form__textarea" rows="3">{{ $stage->action }}</textarea>
                </div>
                <div class="form__group">
                    <label class="form__label">Комментарий</label>
                    <textarea name="comment" class="form__textarea" rows="3">{{ $stage->comment }}</textarea>
                </div>
                <button type="submit" class="btn btn--primary btn--sm">Сохранить</button>
            </form>
            <form
                method="POST"
                action="{{ route('projects.funnels.stages.destroy', [$project, $funnel, $stage]) }}"
                class="canvas-item__delete"
                data-canvas-form
                data-canvas-confirm="Удалить этап «{{ $stage->name }}»?"
            >
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn--danger btn--sm">Удалить этап</button>
            </form>
        </div>

        @if ($stage->action)
            <p class="funnel-stage-block__preview">{{ Str::limit($stage->action, 100) }}</p>
        @elseif ($stage->comment)
            <p class="funnel-stage-block__preview">{{ Str::limit($stage->comment, 100) }}</p>
        @endif
    </div>
</article>
