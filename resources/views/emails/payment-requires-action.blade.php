@extends('emails.layouts.base')

@section('title', 'Confirmação de segurança necessária - SIG.APP')

@section('content')
    <h2 style="margin: 0 0 16px; font-size: 20px; font-weight: 700; color: #18181b; letter-spacing: -0.01em;">Confirmação de segurança</h2>

    <div style="background-color: #eff6ff; border-left: 4px solid #2563eb; padding: 16px 20px; margin-bottom: 20px; border-radius: 4px;">
        <p style="margin: 0; font-size: 14px; font-weight: 600; color: #1e3a5f;">
            Seu banco solicitou uma autenticação adicional para concluir o pagamento.
        </p>
    </div>

    <p style="margin: 0 0 12px; font-size: 15px; color: #52525b; line-height: 1.6;">
        Para garantir a segurança da sua transação, o banco emissor do seu cartão precisa que você confirme esta compra usando o sistema de autenticação 3D Secure (SCA).
    </p>

    <p style="margin: 0 0 24px; font-size: 15px; color: #52525b; line-height: 1.6;">
        Clique no botão abaixo para concluir a autenticação em um ambiente seguro.
    </p>

    <div style="text-align: center; margin: 24px 0;">
        <a href="{{ $paymentUrl }}"
           style="display: inline-block; padding: 14px 32px; background-color: #2563eb; color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; border-radius: 8px;">
            Confirmar Pagamento
        </a>
    </div>

    <p style="margin: 24px 0 0; font-size: 13px; color: #a1a1aa; line-height: 1.5;">
        Este link expira em breve. Se você não reconhece esta solicitação, ignore este e-mail.
    </p>
@endsection
