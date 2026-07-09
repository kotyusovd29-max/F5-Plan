@php
    $user = $user ?? null;
    $isEdit = $user !== null;
    $isSelf = $isEdit && $user->id === auth()->id();
@endphp

<div class="form__group">
    <label for="name" class="form__label">Имя</label>
    <input
        type="text"
        id="name"
        name="name"
        class="form__input @error('name') form__input--error @enderror"
        value="{{ old('name', $user?->name) }}"
        required
    >
    @error('name')
        <span class="form__error">{{ $message }}</span>
    @enderror
</div>

<div class="form__group">
    <label for="email" class="form__label">Email</label>
    <input
        type="email"
        id="email"
        name="email"
        class="form__input @error('email') form__input--error @enderror"
        value="{{ old('email', $user?->email) }}"
        required
    >
    @error('email')
        <span class="form__error">{{ $message }}</span>
    @enderror
</div>

<div class="form__group">
    <label for="password" class="form__label">
        Пароль
        @if ($isEdit)
            <span class="form__hint">оставьте пустым, если не меняете</span>
        @endif
    </label>
    <input
        type="password"
        id="password"
        name="password"
        class="form__input @error('password') form__input--error @enderror"
        {{ $isEdit ? '' : 'required' }}
        autocomplete="new-password"
    >
    @error('password')
        <span class="form__error">{{ $message }}</span>
    @enderror
</div>

<div class="form__group">
    <label for="password_confirmation" class="form__label">Подтверждение пароля</label>
    <input
        type="password"
        id="password_confirmation"
        name="password_confirmation"
        class="form__input"
        {{ $isEdit ? '' : 'required' }}
        autocomplete="new-password"
    >
</div>

@if (! $isSelf)
    <label class="form__checkbox">
        <input
            type="checkbox"
            name="is_admin"
            value="1"
            {{ old('is_admin', $user?->is_admin) ? 'checked' : '' }}
        >
        <span>Администратор (полный доступ и админ-панель)</span>
    </label>
@endif
