@extends('layouts.guest')

@section('title', 'Вход — ' . config('app.name'))

@section('content')
    <div class="login-overlay">
        <div class="login-window">
            <div class="login-window__header">
                <h1 class="login-window__title">{{ config('app.name') }}</h1>
                <p class="login-window__subtitle">Войдите в систему для продолжения</p>
            </div>

            <form method="POST" action="{{ route('login') }}" class="form">
                @csrf

                <div class="form__group">
                    <label for="email" class="form__label">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form__input @error('email') form__input--error @enderror"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="username"
                    >
                    @error('email')
                        <span class="form__error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form__group">
                    <label for="password" class="form__label">Пароль</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form__input @error('password') form__input--error @enderror"
                        required
                        autocomplete="current-password"
                    >
                    @error('password')
                        <span class="form__error">{{ $message }}</span>
                    @enderror
                </div>

                <label class="form__checkbox">
                    <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                    <span>Запомнить меня</span>
                </label>

                <button type="submit" class="btn btn--primary btn--block">Войти</button>
            </form>
        </div>
    </div>
@endsection
