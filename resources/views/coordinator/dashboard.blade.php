@extends('layouts.app', ['role' => 'COORDINADOR'])

@section('title', 'Panel del coordinador')
@section('heading', 'Panel del coordinador')

@section('nav')
    <a href="{{ url('/coordinator') }}" class="block rounded-lg bg-slate-800 px-3 py-2 text-white">Inicio</a>
    {{-- Próximas pantallas: Solicitudes de pre-matrícula --}}
@endsection

@section('content')
<div class="rounded-xl bg-white p-6 shadow-sm">
    <h3 class="text-lg font-semibold">Bienvenido</h3>
    <p class="mt-1 text-sm text-slate-600">Sesión iniciada como <span id="who" class="font-medium"></span>.</p>
    <p class="mt-4 text-sm text-slate-500">Aquí irá la revisión de solicitudes de pre-matrícula.</p>
</div>

@push('scripts')
<script>
document.addEventListener('pm:ready', (e) => {
    document.getElementById('who').textContent = e.detail.user.correo_institucional;
});
</script>
@endpush
@endsection
