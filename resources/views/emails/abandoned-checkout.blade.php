@extends('emails.layouts.base')

@section('title', 'Sua conta foi removida - SIG.APP')

@section('content')
    <h1 class="text-2xl font-bold text-gray-800 mb-4">Olá, {{ $tenantName }}!</h1>
    
    <p class="text-gray-600 mt-4">
        Notamos que você iniciou o cadastro no SIG.APP, mas não chegou a concluir todas as etapas do plano <strong>{{ $planSlug }}</strong>.
    </p>

    <p class="text-gray-600 mt-4">
        Por inatividade, sua conta temporária foi removida do nosso banco de dados. Mas não se preocupe! 
        Se você quiser tentar novamente e conhecer todos os benefícios do SIG.APP, clique abaixo para iniciar um novo cadastro.
    </p>
    
    <div class="text-center mt-6 mb-6">
        <a href="{{ $signupUrl }}" class="inline-block bg-blue-600 text-white font-semibold px-6 py-3 rounded no-underline">
            Iniciar Novo Cadastro
        </a>
    </div>

    <p class="text-gray-600 mt-4">
        Se você teve alguma dificuldade durante o cadastro ou precisar de ajuda, responda este e-mail para falar com nosso suporte.
    </p>
@endsection