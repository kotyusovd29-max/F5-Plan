@extends('layouts.app')

@section('title', 'Новый проект — ' . config('app.name'))

@section('content')
    <div class="form-page">
        <div class="form-panel">
            <div class="form-panel__accent" aria-hidden="true"></div>

            <div class="form-panel__body">
                <div class="form-panel__header">
                    <a href="{{ route('home') }}" class="form-panel__back">← К проектам</a>
                    <h2 class="form-panel__title">Создать проект</h2>
                    <p class="form-panel__subtitle">Заполните основную информацию о клиенте для начала работы над планом внедрения</p>
                </div>

                <form method="POST" action="{{ route('projects.store') }}" class="form">
                    @csrf

                    <div class="form__group">
                        <label for="company_name" class="form__label">Название компании</label>
                        <input
                            type="text"
                            id="company_name"
                            name="company_name"
                            class="form__input @error('company_name') form__input--error @enderror"
                            value="{{ old('company_name') }}"
                            placeholder="Например: ООО «Ромашка»"
                            required
                            autofocus
                        >
                        @error('company_name')
                            <span class="form__error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form__group">
                        <label for="employees_count" class="form__label">Количество сотрудников</label>
                        <input
                            type="number"
                            id="employees_count"
                            name="employees_count"
                            class="form__input @error('employees_count') form__input--error @enderror"
                            value="{{ old('employees_count') }}"
                            min="1"
                            placeholder="Сколько человек будет работать в CRM"
                        >
                        @error('employees_count')
                            <span class="form__error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form__group">
                        <label for="client_tasks" class="form__label">Задачи клиента</label>
                        <textarea
                            id="client_tasks"
                            name="client_tasks"
                            class="form__textarea @error('client_tasks') form__input--error @enderror"
                            rows="5"
                            placeholder="Какие задачи должен решить amoCRM для этого клиента..."
                        >{{ old('client_tasks') }}</textarea>
                        @error('client_tasks')
                            <span class="form__error">{{ $message }}</span>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn--primary btn--block btn--lg">Создать проект</button>
                </form>
            </div>
        </div>
    </div>
@endsection
