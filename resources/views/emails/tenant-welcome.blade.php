@extends('emails.layouts.base')

@section('title', 'Bem-vindo ao SIG.APP')

@section('content')
    <h1 class="text-2xl font-bold text-gray-800 mb-4">Olá!</h1>
    
    <p class="text-gray-600 mt-4">
        Sua conta <strong>{{ $tenantName }}</strong> foi criada com sucesso e já está pronta para uso no nosso sistema.
    </p>
    
    <div class="text-center mt-6 mb-6">
        <a href="{{ $appUrl }}" class="inline-block bg-blue-600 text-white font-semibold px-6 py-3 rounded no-underline">
            Acessar o SIG.APP
        </a>
    </div>

    <p class="text-gray-600 mt-4">
        Se você tiver alguma dúvida ou precisar de ajuda para começar, basta entrar em contato com o nosso suporte.
    </p>
@endsection