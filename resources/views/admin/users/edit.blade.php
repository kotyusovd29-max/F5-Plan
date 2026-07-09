@extends('layouts.app')

@section('title', 'Редактирование — ' . $user->name)

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-header__title">Редактирование пользователя</h2>
            <p class="page-header__subtitle"><a href="{{ route('admin.users.index') }}">← К списку пользователей</a></p>
        </div>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="form form--wide">
            @csrf
            @method('PUT')
            @include('admin.users._form', ['user' => $user])
            <button type="submit" class="btn btn--primary">Сохранить изменения</button>
        </form>
    </div>
@endsection
