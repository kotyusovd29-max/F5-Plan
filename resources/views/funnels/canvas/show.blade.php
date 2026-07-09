@extends('layouts.funnel-canvas')

@section('title', $funnel->name . ' — Доска воронки')

@section('content')
    <div
        class="project-canvas funnel-canvas"
        id="funnel-canvas"
        data-save-url="{{ route('projects.funnels.canvas.update', [$project, $funnel]) }}"
        data-refresh-url="{{ route('projects.funnels.canvas.refresh', [$project, $funnel]) }}"
        data-offset-x="{{ $funnel->canvas_offset_x }}"
        data-offset-y="{{ $funnel->canvas_offset_y }}"
        data-zoom="{{ $funnel->canvas_zoom }}"
    >
        <header class="canvas-toolbar">
            <div class="canvas-toolbar__left">
                <a href="{{ route('projects.canvas.show', $project) }}" class="canvas-toolbar__back">← К доске проекта</a>
                <h1 class="canvas-toolbar__title" data-canvas-project-title>{{ $funnel->name }}</h1>
            </div>

            <div class="canvas-toolbar__center">
                <form
                    method="POST"
                    action="{{ route('projects.funnels.update', [$project, $funnel]) }}"
                    class="canvas-toolbar__add-form"
                    data-canvas-form
                >
                    @csrf
                    @method('PUT')
                    <input
                        type="text"
                        name="name"
                        class="form__input form__input--sm"
                        value="{{ $funnel->name }}"
                        placeholder="Название воронки"
                        required
                    >
                    <button type="submit" class="btn btn--ghost btn--sm">Сохранить название</button>
                </form>
            </div>

            <div class="canvas-toolbar__right">
                <button type="button" class="btn btn--ghost btn--sm" data-canvas-zoom-out title="Уменьшить">−</button>
                <span class="canvas-toolbar__zoom" data-canvas-zoom-label>100%</span>
                <button type="button" class="btn btn--ghost btn--sm" data-canvas-zoom-in title="Увеличить">+</button>
                <button type="button" class="btn btn--ghost btn--sm" data-canvas-zoom-reset title="Сбросить вид">100%</button>
                <span class="canvas-toolbar__status" data-canvas-status>Сохранено</span>
            </div>
        </header>

        <div class="funnel-canvas__workspace">
            <div class="canvas-viewport" id="canvas-viewport">
                <div class="canvas-surface funnel-canvas-surface" id="canvas-surface">
                    @include('funnels.canvas._surface', ['project' => $project, 'funnel' => $funnel])
                </div>
            </div>

            <aside class="funnel-canvas__sidebar" id="funnel-canvas-sidebar">
                <div class="funnel-canvas-sidebar__header">
                    <h2 class="funnel-canvas-sidebar__title">Новый этап</h2>
                </div>
                <form
                    method="POST"
                    action="{{ route('projects.funnels.stages.store', [$project, $funnel]) }}"
                    class="form form--canvas funnel-canvas-sidebar__form"
                    data-canvas-form
                    data-canvas-reset-on-success
                >
                    @csrf
                    <div class="form__group">
                        <label class="form__label" for="funnel-stage-name">Название этапа</label>
                        <input
                            type="text"
                            id="funnel-stage-name"
                            name="name"
                            class="form__input"
                            placeholder="Название этапа"
                            required
                        >
                    </div>
                    <div class="form__group">
                        <label class="form__label" for="funnel-stage-action">Действие на этапе</label>
                        <textarea
                            id="funnel-stage-action"
                            name="action"
                            class="form__textarea"
                            rows="4"
                            placeholder="Действие"
                        ></textarea>
                    </div>
                    <div class="form__group">
                        <label class="form__label" for="funnel-stage-comment">Комментарий</label>
                        <textarea
                            id="funnel-stage-comment"
                            name="comment"
                            class="form__textarea"
                            rows="3"
                            placeholder="Комментарий"
                        ></textarea>
                    </div>
                    <button type="submit" class="btn btn--primary btn--block">+ Добавить этап</button>
                </form>
            </aside>
        </div>
    </div>
@endsection
