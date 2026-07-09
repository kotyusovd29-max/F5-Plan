@php
    $current = $current ?? 'project';
@endphp

<nav class="project-tabs" aria-label="Разделы проекта">
    <a
        href="{{ route('projects.show', $project) }}"
        class="project-tabs__link {{ $current === 'project' ? 'project-tabs__link--active' : '' }}"
    >
        Проект
    </a>
    <a
        href="{{ route('projects.ai.chat', $project) }}"
        class="project-tabs__link {{ $current === 'ai' ? 'project-tabs__link--active' : '' }}"
    >
        ИИ-чат
    </a>
    <a
        href="{{ route('projects.canvas.show', $project) }}"
        class="project-tabs__link {{ $current === 'canvas' ? 'project-tabs__link--active' : '' }}"
    >
        Доска
    </a>
</nav>
