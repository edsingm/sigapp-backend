@extends('emails.layouts.base')

@section('title', 'Redefina sua senha')

@section('content')
    <h1 class="text-2xl font-bold text-gray-800 mb-4">Olá!</h1>
    
    <p class="text-gray-600 mt-4">
        Recebemos uma solicitação para redefinir a senha da sua conta no SIG.APP.
    </p>
    
    <div class="text-center mt-6 mb-6">
        <a href="{{ $resetUrl }}" class="inline-block bg-blue-600 text-white font-semibold px-6 py-3 rounded no-underline">
            Redefinir Senha
        </a>
    </div>

    <p class="text-gray-600 mt-4">
        Este link expira em <strong>{{ $expireMinutes }} minutos</strong>.
    </p>
    
    <p class="text-gray-600 mt-4 text-sm">
        Se você não solicitou a redefinição de senha, pode ignorar este e-mail com segurança. Sua conta continua protegida.
    </p>
@endsection