<div class="project-list">
    @foreach ($projects as $project)
        <a href="{{ route('projects.show', $project) }}" class="project-card project-card--link">
            <h3 class="project-card__title">{{ $project->company_name }}</h3>

            @if ($project->client_tasks)
                <p class="project-card__tasks">{{ Str::limit($project->client_tasks, 60) }}</p>
            @endif

            <div class="project-card__footer">
                @if ($project->employees_count)
                    <span class="project-card__meta">{{ $project->employees_count }} сотр.</span>
                @endif
                <span class="project-card__meta">{{ $project->created_at->format('d.m.Y') }}</span>
            </div>
        </a>
    @endforeach
</div>
