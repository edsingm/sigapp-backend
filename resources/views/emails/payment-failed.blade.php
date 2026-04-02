@extends('emails.layouts.base')

@section('title', 'Falha no Pagamento - SIG.APP')

@section('content')
    <h1 class="text-2xl font-bold text-red-600 mb-4">Olá, {{ $tenantName }}!</h1>
    
    <div class="bg-red-50 border-l-4 border-red-600 p-4 mb-6">
        <p class="text-gray-800 font-semibold">Atenção ao status da sua assinatura</p>
    </div>
    
    <p class="text-gray-600 mt-4">
        @if ($attemptCount > 0)
            Tentamos cobrar a sua assinatura <strong>{{ $attemptCount }} vez(es)</strong>, mas infelizmente não conseguimos processar o pagamento com o seu método atual.
        @else
            Identificamos uma pendência no pagamento da sua assinatura.
        @endif
    </p>

    <p class="text-gray-600 mt-4">
        Sua conta poderá ser <strong>suspensa</strong> caso o pagamento não seja regularizado em breve. 
        Para não perder o acesso ao SIG.APP, por favor, atualize ou confirme seu método de pagamento.
    </p>
    
    @if ($invoiceUrl)
        <div class="text-center mt-6 mb-6">
            <a href="{{ $invoiceUrl }}" class="inline-block bg-red-600 text-white font-semibold px-6 py-3 rounded no-underline">
                Pagar Fatura
            </a>
        </div>
    @endif

    <p class="text-gray-600 mt-4">
        Em caso de dúvidas, nossa central de suporte está sempre à disposição.
    </p>
@endsection