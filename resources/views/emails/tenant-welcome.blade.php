@extends('emails.layouts.base')

@section('title', 'Bem-vindo ao SIG.APP')

@section('content')
    <h2 style="margin: 0 0 16px; font-size: 20px; font-weight: 700; color: #18181b; letter-spacing: -0.01em;">Bem-vindo, {{ $tenantName }}!</h2>

    <p style="margin: 0 0 12px; font-size: 15px; color: #52525b; line-height: 1.6;">
        Sua conta foi criada com sucesso e já está pronta para uso. Você pode acessar agora mesmo o sistema e começar a gerenciar sua operação.
    </p>

    <p style="margin: 0 0 24px; font-size: 15px; color: #52525b; line-height: 1.6;">
        No SIG.APP você tem tudo que precisa para organizar seu negócio em um só lugar.
    </p>

    <div style="text-align: center; margin: 24px 0;">
        <a href="{{ $appUrl }}"
           style="display: inline-block; padding: 14px 32px; background-color: #2563eb; color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; border-radius: 8px;">
            Acessar o SIG.APP
        </a>
    </div>

    <p style="margin: 24px 0 0; font-size: 14px; color: #71717a; line-height: 1.5;">
        Precisa de ajuda? Responda este e-mail ou consulte nossa central de ajuda.
    </p>
@endsection
