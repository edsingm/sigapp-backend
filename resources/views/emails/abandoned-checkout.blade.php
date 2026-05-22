@extends('emails.layouts.base')

@section('title', 'Seu cadastro no SIG.APP')

@section('content')
    <h2 style="margin: 0 0 16px; font-size: 20px; font-weight: 700; color: #18181b; letter-spacing: -0.01em;">Olá, {{ $tenantName }}!</h2>

    <p style="margin: 0 0 12px; font-size: 15px; color: #52525b; line-height: 1.6;">
        Você iniciou o cadastro no SIG.APP, mas o processo não foi concluído.
    </p>

    <p style="margin: 0 0 24px; font-size: 15px; color: #52525b; line-height: 1.6;">
        Suas informações temporárias foram removidas por segurança, mas você pode começar um novo cadastro quando quiser.
    </p>

    <div style="text-align: center; margin: 24px 0;">
        <a href="{{ $signupUrl }}"
           style="display: inline-block; padding: 14px 32px; background-color: #2563eb; color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; border-radius: 8px;">
            Criar Nova Conta
        </a>
    </div>

    <p style="margin: 24px 0 0; font-size: 14px; color: #71717a; line-height: 1.5;">
        Se teve alguma dificuldade ou quer saber mais sobre o SIG.APP, responda este e-mail — ficaremos felizes em ajudar.
    </p>
@endsection
