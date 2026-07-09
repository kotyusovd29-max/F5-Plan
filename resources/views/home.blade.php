@extends('layouts.app')

@section('title', 'Главная — ' . config('app.name'))

@section('content')
    <div class="projects-section">
        <div class="page-header">
            <div>
                <h2 class="page-header__title">Мои проекты</h2>
                <p class="page-header__subtitle">Планы внедрения amoCRM</p>
            </div>
            <a href="{{ route('projects.create') }}" class="btn btn--primary">Создать проект</a>
        </div>

        <div class="tabs">
            <a
                href="{{ route('home', ['view' => 'mine']) }}"
                class="tabs__item {{ $view === 'mine' ? 'tabs__item--active' : '' }}"
            >
                Мои проекты
            </a>
            <a
                href="{{ route('home', ['view' => 'all']) }}"
                class="tabs__item {{ $view === 'all' ? 'tabs__item--active' : '' }}"
            >
                Все проекты
            </a>
        </div>

        @if ($view === 'all')
            <section class="employees-section">
                <h3 class="employees-section__title">Сотрудники</h3>

                @if ($employees->isEmpty())
                    <p class="employees-section__empty">Проектов пока нет.</p>
                @else
                    <div class="employees-list">
                        @foreach ($employees as $employee)
                            <a
                                href="{{ route('home', ['view' => 'all', 'user' => $employee->id]) }}"
                                class="employee-card {{ $selectedUser?->id === $employee->id ? 'employee-card--active' : '' }}"
                            >
                                <span class="employee-card__name">{{ $employee->name }}</span>
                                <span class="employee-card__count">
                                    @php
                                        $count = $employee->projects_count;
                                        $label = ($count % 10 === 1 && $count % 100 !== 11) ? 'проект' : (
                                            ($count % 10 >= 2 && $count % 10 <= 4 && ($count % 100 < 10 || $count % 100 >= 20)) ? 'проекта' : 'проектов'
                                        );
                                    @endphp
                                    {{ $count }} {{ $label }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>

            @if ($selectedUser)
                <h3 class="projects-section__subtitle">Проекты: {{ $selectedUser->name }}</h3>
            @endif
        @endif

        @if ($view === 'mine' && $projects->isEmpty())
            <div class="card card--empty">
                <p>У вас пока нет проектов. Нажмите «Создать проект», чтобы начать.</p>
            </div>
        @elseif ($view === 'all' && ! $selectedUser)
            <div class="card card--empty">
                <p>Выберите сотрудника, чтобы увидеть его проекты.</p>
            </div>
        @elseif ($projects->isEmpty())
            <div class="card card--empty">
                <p>У сотрудника пока нет проектов.</p>
            </div>
        @else
            @include('projects._grid', ['projects' => $projects])
        @endif
    </div>
@endsection
