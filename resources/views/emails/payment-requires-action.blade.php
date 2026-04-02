@extends('emails.layouts.base')

@section('title', 'Confirmação de pagamento necessária')

@section('content')
    <h1 class="text-2xl font-bold text-gray-800 mb-4">Olá!</h1>
    
    <div class="bg-blue-50 border-l-4 border-blue-600 p-4 mb-6">
        <p class="text-gray-800 font-semibold">Autenticação do Banco Requerida (SCA / 3D Secure)</p>
    </div>

    <p class="text-gray-600 mt-4">
        O seu banco exige uma confirmação de segurança adicional para que possamos processar o pagamento da sua assinatura.
    </p>

    <p class="text-gray-600 mt-4">
        Para prosseguir e evitar a interrupção da sua conta, por favor, clique no botão abaixo para aprovar a transação em um ambiente seguro do seu emissor de cartão.
    </p>

    <div class="text-center mt-6 mb-6">
        <a href="{{ $paymentUrl }}" class="inline-block bg-blue-600 text-white font-semibold px-6 py-3 rounded no-underline">
            Confirmar Pagamento
        </a>
    </div>

    <p class="text-gray-600 mt-4 text-sm text-gray-400">
        Este link expira em breve. Se você não reconhece esta solicitação ou a cobrança foi cancelada, ignore este e-mail.
    </p>
@endsection