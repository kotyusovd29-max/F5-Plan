@php
    use App\Models\ProjectCanvasBlock;

    $entityType = str_starts_with($block->block_key, 'skk-')
        ? substr($block->block_key, 4)
        : null;
    $funnel = $block->block_type === ProjectCanvasBlock::TYPE_FUNNEL
        ? $project->funnels->firstWhere('id', $block->reference_id)
        : null;
    $skkFields = $entityType
        ? $project->skkFields->where('entity_type', $entityType)
        : collect();
    $blockWidth = $block->block_type === ProjectCanvasBlock::TYPE_FUNNEL
        ? max($block->width, 520)
        : max($block->width, 340);
    $isDeletable = in_array($block->block_type, [
        ProjectCanvasBlock::TYPE_SKK,
        ProjectCanvasBlock::TYPE_INTEGRATIONS,
        ProjectCanvasBlock::TYPE_FUNNEL,
    ], true);
@endphp

<article
    class="canvas-block canvas-block--{{ $block->block_type }} canvas-block--editable {{ $block->block_type === ProjectCanvasBlock::TYPE_FUNNEL ? 'canvas-block--funnel-preview' : '' }}"
    data-block-id="{{ $block->id }}"
    data-z-index="{{ $block->z_index }}"
    @if ($isDeletable)
        data-canvas-deletable="1"
        data-canvas-delete-url="{{ route('projects.canvas.blocks.destroy', [$project, $block]) }}"
        data-canvas-delete-label="{{ $block->title }}"
    @endif
    style="left: {{ $block->pos_x }}px; top: {{ $block->pos_y }}px; width: {{ $blockWidth }}px; z-index: {{ $block->z_index }};"
>
    <header class="canvas-block__header" data-canvas-drag-handle>
        <span class="canvas-block__title">{{ $block->title }}</span>
        <span class="canvas-block__drag-icon" aria-hidden="true">⠿</span>
    </header>

    <div class="canvas-block__body" data-canvas-no-drag>
        @if ($block->block_type === ProjectCanvasBlock::TYPE_INFO)
            <form method="POST" action="{{ route('projects.update', $project) }}" class="form form--canvas" data-canvas-form>
                @csrf
                @method('PUT')

                <div class="form__group">
                    <label for="canvas_company_name_{{ $block->id }}" class="form__label">Название компании</label>
                    <input
                        type="text"
                        id="canvas_company_name_{{ $block->id }}"
                        name="company_name"
                        class="form__input"
                        value="{{ $project->company_name }}"
                        required
                    >
                </div>

                <div class="form__group">
                    <label for="canvas_employees_{{ $block->id }}" class="form__label">Количество сотрудников</label>
                    <input
                        type="number"
                        id="canvas_employees_{{ $block->id }}"
                        name="employees_count"
                        class="form__input"
                        value="{{ $project->employees_count }}"
                        min="1"
                    >
                </div>

                <div class="form__group">
                    <label for="canvas_tasks_{{ $block->id }}" class="form__label">Задачи клиента</label>
                    <textarea
                        id="canvas_tasks_{{ $block->id }}"
                        name="client_tasks"
                        class="form__textarea"
                        rows="4"
                    >{{ $project->client_tasks }}</textarea>
                </div>

                <button type="submit" class="btn btn--primary btn--sm btn--block">Сохранить</button>
            </form>

        @elseif ($block->block_type === ProjectCanvasBlock::TYPE_SKK && $entityType)
            <div class="canvas-items">
                @forelse ($skkFields as $field)
                    <div class="canvas-item" data-canvas-item>
                        <button type="button" class="canvas-item__toggle" data-canvas-item-toggle>
                            <span>{{ $field->name }}</span>
                            <span class="canvas-item__icon" aria-hidden="true">▼</span>
                        </button>
                        <div class="canvas-item__panel is-hidden">
                            <form
                                method="POST"
                                action="{{ route('projects.skk-fields.update', [$project, $field]) }}"
                                class="form form--canvas"
                                data-canvas-form
                            >
                                @csrf
                                @method('PUT')
                                <div class="form__group">
                                    <label class="form__label">Название</label>
                                    <input type="text" name="name" class="form__input" value="{{ $field->name }}" required>
                                </div>
                                <div class="form__group">
                                    <label class="form__label">Комментарий</label>
                                    <textarea name="comment" class="form__textarea" rows="2">{{ $field->comment }}</textarea>
                                </div>
                                <button type="submit" class="btn btn--primary btn--sm">Сохранить</button>
                            </form>
                            <form
                                method="POST"
                                action="{{ route('projects.skk-fields.destroy', [$project, $field]) }}"
                                class="canvas-item__delete"
                                data-canvas-form
                                data-canvas-confirm="Удалить поле «{{ $field->name }}»?"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn--danger btn--sm">Удалить</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="canvas-block__empty">Поля не добавлены</p>
                @endforelse
            </div>

            <form
                method="POST"
                action="{{ route('projects.skk-fields.store', $project) }}"
                class="form form--canvas canvas-add-form"
                data-canvas-form
            >
                @csrf
                <input type="hidden" name="entity_type" value="{{ $entityType }}">
                <div class="form__group">
                    <label class="form__label">Новое поле</label>
                    <input type="text" name="name" class="form__input" placeholder="Название поля" required>
                </div>
                <div class="form__group">
                    <textarea name="comment" class="form__textarea" rows="2" placeholder="Комментарий (необязательно)"></textarea>
                </div>
                <button type="submit" class="btn btn--primary btn--sm btn--block">+ Добавить поле</button>
            </form>

        @elseif ($block->block_type === ProjectCanvasBlock::TYPE_INTEGRATIONS)
            <div class="canvas-items">
                @forelse ($project->integrations as $integration)
                    <div class="canvas-item" data-canvas-item>
                        <button type="button" class="canvas-item__toggle" data-canvas-item-toggle>
                            <span>{{ $integration->name }}</span>
                            <span class="canvas-item__icon" aria-hidden="true">▼</span>
                        </button>
                        <div class="canvas-item__panel is-hidden">
                            <form
                                method="POST"
                                action="{{ route('projects.integrations.update', [$project, $integration]) }}"
                                class="form form--canvas"
                                data-canvas-form
                            >
                                @csrf
                                @method('PUT')
                                <div class="form__group">
                                    <label class="form__label">Название</label>
                                    <input type="text" name="name" class="form__input" value="{{ $integration->name }}" required>
                                </div>
                                <div class="form__group">
                                    <label class="form__label">Комментарий</label>
                                    <textarea name="comment" class="form__textarea" rows="2">{{ $integration->comment }}</textarea>
                                </div>
                                <button type="submit" class="btn btn--primary btn--sm">Сохранить</button>
                            </form>
                            <form
                                method="POST"
                                action="{{ route('projects.integrations.destroy', [$project, $integration]) }}"
                                class="canvas-item__delete"
                                data-canvas-form
                                data-canvas-confirm="Удалить «{{ $integration->name }}»?"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn--danger btn--sm">Удалить</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="canvas-block__empty">Интеграции не добавлены</p>
                @endforelse
            </div>

            <form
                method="POST"
                action="{{ route('projects.integrations.store', $project) }}"
                class="form form--canvas canvas-add-form"
                data-canvas-form
            >
                @csrf
                <div class="form__group">
                    <label class="form__label">Новая интеграция</label>
                    <input type="text" name="name" class="form__input" placeholder="Название" required>
                </div>
                <div class="form__group">
                    <textarea name="comment" class="form__textarea" rows="2" placeholder="Комментарий"></textarea>
                </div>
                <button type="submit" class="btn btn--primary btn--sm btn--block">+ Добавить</button>
            </form>

        @elseif ($block->block_type === ProjectCanvasBlock::TYPE_FUNNEL && $funnel)
            @if ($funnel->description)
                <p class="canvas-block__text">{{ Str::limit($funnel->description, 160) }}</p>
            @endif

            @if ($funnel->stages->isEmpty())
                <p class="canvas-block__empty">Этапы не добавлены</p>
            @else
                <div class="canvas-funnel-preview">
                    @foreach ($funnel->stages as $index => $stage)
                        <div class="canvas-funnel-preview__step">
                            <span class="canvas-funnel-preview__number">{{ $index + 1 }}</span>
                            <span class="canvas-funnel-preview__name">{{ $stage->name }}</span>
                        </div>
                        @if (! $loop->last)
                            <span class="canvas-funnel-preview__arrow" aria-hidden="true">→</span>
                        @endif
                    @endforeach
                </div>
            @endif

            <a
                href="{{ route('projects.funnels.canvas.show', [$project, $funnel]) }}"
                class="btn btn--primary btn--sm btn--block canvas-funnel-preview__edit"
            >
                Редактировать воронку
            </a>
        @endif
    </div>
</article>
