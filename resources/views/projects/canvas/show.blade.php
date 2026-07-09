@extends('layouts.canvas')

@section('title', 'Доска — ' . $project->company_name)

@section('content')
    <div
        class="project-canvas"
        id="project-canvas"
        data-save-url="{{ route('projects.canvas.update', $project) }}"
        data-refresh-url="{{ route('projects.canvas.refresh', $project) }}"
        data-offset-x="{{ $project->canvas_offset_x }}"
        data-offset-y="{{ $project->canvas_offset_y }}"
        data-zoom="{{ $project->canvas_zoom }}"
    >
        <header class="canvas-toolbar">
            <div class="canvas-toolbar__left">
                <a href="{{ route('projects.show', $project) }}" class="canvas-toolbar__back">← К проекту</a>
                <h1 class="canvas-toolbar__title" data-canvas-project-title>{{ $project->company_name }}</h1>
            </div>

            <div class="canvas-toolbar__center">
                <form
                    method="POST"
                    action="{{ route('projects.funnels.store', $project) }}"
                    class="canvas-toolbar__add-form"
                    data-canvas-form
                >
                    @csrf
                    <input
                        type="text"
                        name="name"
                        class="form__input form__input--sm"
                        placeholder="Название воронки"
                        required
                    >
                    <button type="submit" class="btn btn--primary btn--sm">+ Воронка</button>
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

        <div class="canvas-viewport" id="canvas-viewport">
            <div class="canvas-surface" id="canvas-surface">
                @include('projects.canvas._surface', ['blocks' => $blocks, 'project' => $project])
            </div>
        </div>

        <div class="canvas-trash is-hidden" id="canvas-trash" aria-hidden="true">
            <div class="canvas-trash__inner">
                <span class="canvas-trash__icon" aria-hidden="true">🗑</span>
                <span class="canvas-trash__label">Перетащите сюда для удаления</span>
            </div>
        </div>
    </div>
@endsection
