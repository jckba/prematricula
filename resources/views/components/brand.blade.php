@props(['compact' => false])
<div {{ $attributes->merge(['class' => 'brand'.($compact ? ' brand-compact' : '')]) }}>
    @unless ($compact)
        <img src="{{ asset('images/unt.png') }}" alt="Universidad Nacional de Trujillo" class="brand-logo brand-logo-unt" width="800" height="427">
        <div class="brand-divider" aria-hidden="true"></div>
    @endunless
    <img src="{{ asset('images/logo_info.png') }}" alt="Escudo de la Escuela Profesional de Informática de la UNT" class="brand-logo brand-logo-school" width="221" height="221">
    @if ($compact)
        <div class="brand-wordmark">UNT<span>Universidad Nacional de Trujillo</span></div>
    @else
        <div class="brand-school">Escuela Profesional de<strong>Informática</strong></div>
    @endif
</div>
