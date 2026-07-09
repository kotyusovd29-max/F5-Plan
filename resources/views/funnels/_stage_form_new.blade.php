@php
    use App\Support\FunnelStageColors;

    $defaultColor = FunnelStageColors::defaultForIndex($funnel->stages->count());
@endphp

<form
    method="POST"
    action="{{ route('projects.funnels.stages.store', [$project, $funnel]) }}"
    class="form stage-form-new__inner"
>
    @csrf

    <input type="hidden" name="form_type" value="new_stage">
    <input type="hidden" name="after_stage_id" id="after_stage_id" value="{{ old('after_stage_id', '') }}">

    <p class="stage-form-new__title">Новый этап</p>

    <div class="form__group">
        <label for="new_stage_name" class="form__label">Название этапа</label>
        <input
            type="text"
            id="new_stage_name"
            name="name"
            class="form__input @error('name') form__input--error @enderror"
            value="{{ old('name') }}"
            placeholder="Например: Новая заявка"
            required
        >
        @error('name')
            <span class="form__error">{{ $message }}</span>
        @enderror
    </div>

    @include('funnels._stage_color_picker', [
        'value' => old('color', $defaultColor),
        'pickerId' => 'new_stage_color',
    ])

    <div class="form__group">
        <label for="new_stage_action" class="form__label">Действие на этапе</label>
        <textarea
            id="new_stage_action"
            name="action"
            class="form__textarea"
            rows="3"
            placeholder="Что происходит на этом этапе..."
        >{{ old('action') }}</textarea>
    </div>

    <div class="form__group">
        <label for="new_stage_comment" class="form__label">Комментарий к этапу</label>
        <textarea
            id="new_stage_comment"
            name="comment"
            class="form__textarea"
            rows="3"
            placeholder="Дополнительные заметки, инструменты, ответственные..."
        >{{ old('comment') }}</textarea>
    </div>

    <div class="stage-form-new__actions">
        <button type="submit" class="btn btn--primary btn--sm">Сохранить этап</button>
        <button type="button" class="btn btn--ghost btn--sm" data-hide-new-stage>Отмена</button>
    </div>
</form>
