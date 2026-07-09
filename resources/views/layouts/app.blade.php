<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="page @yield('body_class')">
    <header class="header">
        <div class="container header__inner">
            <a href="{{ route('home') }}" class="header__title">{{ config('app.name') }}</a>

            <nav class="header__nav">
                @auth
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('admin.users.index') }}" class="header__link">Админ-панель</a>
                    @endif
                    <span class="header__user">{{ auth()->user()->name }}</span>
                    <form action="{{ route('logout') }}" method="POST" class="header__logout-form">
                        @csrf
                        <button type="submit" class="btn btn--ghost btn--sm">Выйти</button>
                    </form>
                @endauth
            </nav>
        </div>
    </header>

    <main class="main @yield('main_class')">
        <div class="container @yield('container_class')">
            @if (session('success'))
                <div class="alert alert--success">{{ session('success') }}</div>
            @endif

            @if (session('error'))
                <div class="alert alert--error">{{ session('error') }}</div>
            @endif

            @yield('content')
        </div>
    </main>
</body>
</html>
