@extends('emails.layouts.base')

@section('title', 'Seu período de avaliação termina em breve')

@section('content')
    <h1 class="text-2xl font-bold text-gray-800 mb-4">Olá, {{ $tenantName }}!</h1>
    
    <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mb-6">
        <p class="text-gray-800 font-semibold">Seu período de teste no SIG.APP termina em {{ $daysText }}.</p>
    </div>

    <p class="text-gray-600 mt-4">
        Esperamos que você esteja aproveitando as funcionalidades da sua assinatura!
    </p>

    <p class="text-gray-600 mt-4">
        Seu período de avaliação gratuita termina no dia <strong>{{ $formattedDate }}</strong>.
        Após essa data, a cobrança do plano que você escolheu no cadastro será iniciada automaticamente.
    </p>

    <p class="text-gray-600 mt-4">
        Para continuar usando o SIG.APP sem interrupção e sem perda de acesso, certifique-se de que seu método de pagamento cadastrado está atualizado.
    </p>

    <div class="text-center mt-6 mb-6">
        <a href="{{ $billingUrl }}" class="inline-block bg-yellow-500 text-white font-semibold px-6 py-3 rounded no-underline shadow">
            Gerenciar Assinatura
        </a>
    </div>

    <p class="text-gray-600 mt-4 text-sm">
        Em caso de dúvidas, sinta-se à vontade para nos contatar.
    </p>
@endsection