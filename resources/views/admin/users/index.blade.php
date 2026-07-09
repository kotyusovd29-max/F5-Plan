@extends('layouts.app')

@section('title', 'Пользователи — Админ-панель')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-header__title">Пользователи</h2>
            <p class="page-header__subtitle">Управление доступом к системе</p>
        </div>
        <a href="{{ route('admin.users.create') }}" class="btn btn--primary">Добавить пользователя</a>
    </div>

    <div class="card card--table">
        <table class="table">
            <thead>
                <tr>
                    <th>Имя</th>
                    <th>Email</th>
                    <th>Роль</th>
                    <th>Дата регистрации</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>
                            @if ($user->is_admin)
                                <span class="badge badge--admin">Администратор</span>
                            @else
                                <span class="badge">Сотрудник</span>
                            @endif
                        </td>
                        <td>{{ $user->created_at->format('d.m.Y') }}</td>
                        <td class="table__actions">
                            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn--ghost btn--sm">Изменить</a>
                            @if ($user->id !== auth()->id())
                                <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="inline-form" onsubmit="return confirm('Удалить пользователя?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn--danger btn--sm">Удалить</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="table__empty">Пользователей пока нет</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $users->links() }}
@endsection
