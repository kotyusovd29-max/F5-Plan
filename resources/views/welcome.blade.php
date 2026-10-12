@extends('layouts.app')

@section('title', 'Главная — ' . config('app.name'))

@section('content')
    <div class="card">
        <h2>План внедрения amoCRM</h2>
        <p>
            Система для подготовки планов работ по внедрению amoCRM.
            Laravel {{ app()->version() }} установлен и готов к разработке.
                    </p>
                </div>
@endsection
