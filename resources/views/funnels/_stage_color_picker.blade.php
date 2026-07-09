@php
    $pickerId = $pickerId ?? 'stage-color-'.uniqid();
    $colorValue = old('color', $value ?? '#99ccff');
@endphp

<div class="stage-color-picker" data-stage-color-picker>
    <span class="form__label">Цвет полосы этапа</span>
    <div class="stage-color-picker__swatches" role="listbox" aria-label="Выбор цвета этапа">
        @foreach (\App\Support\FunnelStageColors::palette() as $paletteColor)
            <button
                type="button"
                class="stage-color-picker__swatch {{ strtolower($colorValue) === strtolower($paletteColor) ? 'is-selected' : '' }}"
                data-color="{{ $paletteColor }}"
                style="background-color: {{ $paletteColor }}"
                title="{{ $paletteColor }}"
                aria-label="Цвет {{ $paletteColor }}"
            ></button>
        @endforeach
        <label class="stage-color-picker__custom" title="Свой цвет">
            <input
                type="color"
                id="{{ $pickerId }}"
                name="color"
                value="{{ $colorValue }}"
                data-stage-color-input
            >
            <span class="stage-color-picker__custom-icon" aria-hidden="true">+</span>
        </label>
    </div>
</div>
