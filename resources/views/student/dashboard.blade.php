@extends('layouts.app', ['role' => 'ESTUDIANTE'])

@section('title', 'Panel del estudiante')
@section('heading', 'Panel del estudiante')

@section('nav')
    <a href="{{ url('/student') }}" class="block rounded-lg bg-slate-800 px-3 py-2 text-white">Inicio</a>
    {{-- Próximas pantallas: Cursos disponibles, Mi pre-matrícula, Historial --}}
@endsection

@section('content')
<div class="rounded-xl bg-white p-6 shadow-sm">
    <h3 class="text-lg font-semibold">Bienvenido</h3>
    <p class="mt-1 text-sm text-slate-600">Sesión iniciada como <span id="who" class="font-medium"></span>.</p>
    <p class="mt-4 text-sm text-slate-500">Aquí irán los cursos disponibles y la pre-matrícula.</p>
</div>

@push('scripts')
<script>
document.addEventListener('pm:ready', (e) => {
    document.getElementById('who').textContent = e.detail.user.correo_institucional;
});
</script>
@endpush
@endsection
