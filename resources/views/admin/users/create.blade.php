@extends('layouts.app')

@section('title', 'Новый пользователь — Админ-панель')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-header__title">Новый пользователь</h2>
            <p class="page-header__subtitle"><a href="{{ route('admin.users.index') }}">← К списку пользователей</a></p>
        </div>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('admin.users.store') }}" class="form form--wide">
            @csrf
            @include('admin.users._form')
            <button type="submit" class="btn btn--primary">Создать пользователя</button>
        </form>
    </div>
@endsection
