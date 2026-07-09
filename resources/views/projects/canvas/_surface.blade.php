@foreach ($blocks as $block)
    @include('projects.canvas._block', ['block' => $block, 'project' => $project])
@endforeach
