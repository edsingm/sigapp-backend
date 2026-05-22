@extends('emails.layouts.base')

@section('title', 'Período de avaliação - SIG.APP')

@section('content')
    <h2 style="margin: 0 0 16px; font-size: 20px; font-weight: 700; color: #18181b; letter-spacing: -0.01em;">Olá, {{ $tenantName }}!</h2>

    <div style="background-color: #fefce8; border-left: 4px solid #eab308; padding: 16px 20px; margin-bottom: 20px; border-radius: 4px;">
        <p style="margin: 0; font-size: 14px; font-weight: 600; color: #713f12;">
            Seu período de teste termina em {{ $daysText }} ({{ $formattedDate }}).
        </p>
    </div>

    <p style="margin: 0 0 12px; font-size: 15px; color: #52525b; line-height: 1.6;">
        Esperamos que você esteja aproveitando o SIG.APP! Para continuar usando sem interrupção, sua assinatura será ativada automaticamente após o período de teste.
    </p>

    <p style="margin: 0 0 24px; font-size: 15px; color: #52525b; line-height: 1.6;">
        Certifique-se de que seu método de pagamento está atualizado para evitar qualquer indisponibilidade.
    </p>

    <div style="text-align: center; margin: 24px 0;">
        <a href="{{ $billingUrl }}"
           style="display: inline-block; padding: 14px 32px; background-color: #eab308; color: #422006; font-size: 15px; font-weight: 600; text-decoration: none; border-radius: 8px;">
            Gerenciar Assinatura
        </a>
    </div>

    <p style="margin: 24px 0 0; font-size: 14px; color: #71717a; line-height: 1.5;">
        Dúvidas? Responda este e-mail ou fale com nosso suporte.
    </p>
@endsection
