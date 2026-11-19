@extends('layouts.guest')

@section('title', 'Registro de estudiante')

@section('content')

    <section class="login-intro" aria-labelledby="institution-name">
        <div class="login-photo" aria-hidden="true">
            <img
                src="{{ asset('images/reseña_frontis_escuela_informatica.webp') }}"
                alt=""
                width="900"
                height="500"
            >
        </div>

        <div class="login-identity relative z-10 flex flex-col items-center text-center">
            <img
                src="{{ asset('images/logo_info.png') }}"
                alt="Escuela Profesional de Informática de la UNT"
                class="brand-logo login-hero-logo"
                width="1402"
                height="1122"
            >

            <h1 id="institution-name">Prematrícula</h1>
            <p>Escuela Profesional de Informática<br>UNT</p>
            <span class="login-gold-line" aria-hidden="true"></span>
        </div>
    </section>

    <section class="login-panel" aria-labelledby="register-heading">
        <div class="login-card relative z-10 w-full">

            <div class="flex flex-col items-center text-center">
                <img
                    src="{{ asset('images/logo_info.png') }}"
                    alt="Escuela Profesional de Informática de la UNT"
                    class="brand-logo login-card-logo"
                    width="1402"
                    height="1122"
                >

                <h2 id="register-heading">Registro de estudiante</h2>

                <p>Ingresa tus datos para crear tu cuenta.</p>
            </div>

            @if ($errors->any())
                <div class="notice notice-danger" role="alert">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('register.store') }}" method="POST">
                @csrf

                <div class="field">
                    <label for="codigo_universitario">Código universitario</label>
                    <input
                        id="codigo_universitario"
                        name="codigo_universitario"
                        type="text"
                        value="{{ old('codigo_universitario') }}"
                        placeholder="Tu código de matricula"
                        inputmode="numeric"
                        pattern="[0-9]{10}"
                        maxlength="10"
                        minlength="10"
                        autocomplete="off"
                        required
                    >
                </div>

                <div class="field">
                    <label for="nombres">Nombres</label>
                    <input
                        id="nombres"
                        name="nombres"
                        type="text"
                        value="{{ old('nombres') }}"
                        placeholder="Ingresa tus nombres"
                        maxlength="100"
                        required
                    >
                </div>

                <div class="field">
                    <label for="apellidos">Apellidos</label>
                    <input
                        id="apellidos"
                        name="apellidos"
                        type="text"
                        value="{{ old('apellidos') }}"
                        placeholder="Ingresa tus apellidos"
                        maxlength="150"
                        required
                    >
                </div>

                <div class="field">
                    <label for="correo_institucional">Correo institucional</label>
                    <input
                        id="correo_institucional"
                        name="correo_institucional"
                        type="email"
                        value="{{ old('correo_institucional') }}"
                        placeholder="correo@unitru.edu.pe"
                        maxlength="255"
                        autocomplete="email"
                        required
                    >
                </div>

                <button class="btn login-submit" type="submit">
                    Registrarme
                </button>
            </form>

            <div class="login-help">
                <p>
                    ¿Ya tienes una cuenta?
                    <a href="{{ route('login') }}">Inicia sesión</a>
                </p>
            </div>

        </div>
    </section>

@endsection
