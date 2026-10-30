@extends('layouts.guest')
@section('title', 'Acceso al portal académico')
@section('content')
<section class="login-intro">
    <x-brand />
    <div class="intro-content"><p class="eyebrow">PORTAL ACADÉMICO</p><h1>Prematrícula<br>UNT Informática</h1><p>Tu información académica y tus solicitudes, en un solo lugar.</p></div>
    <p class="intro-footer">Universidad Nacional de Trujillo · Trujillo, Perú</p>
</section>
<section class="login-panel">
    <div class="login-card">
        <h2>Continúa al portal académico</h2>
        <p class="muted">Tienes una sesión web anterior. Continúa al inicio de sesión del portal para acceder a las funciones de prematrícula con tus credenciales institucionales.</p>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="btn login-submit" type="submit">Continuar al inicio de sesión</button>
        </form>
    </div>
</section>
@endsection
