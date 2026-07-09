<svg class="funnel-canvas__arrows" id="funnel-canvas-arrows" aria-hidden="true">
    <defs>
        <marker id="funnel-arrowhead" markerWidth="10" markerHeight="7" refX="9" refY="3.5" orient="auto">
            <polygon points="0 0, 10 3.5, 0 7" fill="#f97316"></polygon>
        </marker>
    </defs>
</svg>

@foreach ($funnel->stages as $index => $stage)
    @include('funnels.canvas._stage_block', [
        'stage' => $stage,
        'number' => $index + 1,
        'project' => $project,
        'funnel' => $funnel,
    ])
@endforeach
