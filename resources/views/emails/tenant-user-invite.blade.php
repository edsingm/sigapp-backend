@extends('emails.layouts.base')

@section('title', 'Convite para o SIG.APP')

@section('content')
    <h2 style="margin: 0 0 16px; font-size: 20px; font-weight: 700; color: #18181b; letter-spacing: -0.01em;">
        Você foi convidado
    </h2>

    <p style="margin: 0 0 12px; font-size: 15px; color: #52525b; line-height: 1.6;">
        Olá{{ $userName !== '' ? ', '.$userName : '' }}!
    </p>

    <p style="margin: 0 0 12px; font-size: 15px; color: #52525b; line-height: 1.6;">
        Você recebeu um convite para acessar o <strong style="color: #18181b;">SIG.APP</strong>
        na conta <strong style="color: #18181b;">{{ $tenantName }}</strong>.
    </p>

    <p style="margin: 0 0 24px; font-size: 15px; color: #52525b; line-height: 1.6;">
        Para começar, defina sua senha de acesso clicando no botão abaixo:
    </p>

    <div style="text-align: center; margin: 24px 0;">
        <a href="{{ $inviteUrl }}"
           class="email-button"
           style="display: inline-block; padding: 14px 32px; background-color: #2563eb; color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; border-radius: 8px;">
            Aceitar convite e definir senha
        </a>
    </div>

    <p style="margin: 24px 0 0; font-size: 14px; color: #71717a; line-height: 1.5;">
        Este link expira em <strong>{{ $expireMinutes }} minutos</strong>.
    </p>

    <p style="margin: 8px 0 0; font-size: 13px; color: #a1a1aa; line-height: 1.5;">
        Se você não esperava este convite, ignore este e-mail. Nenhuma ação será tomada.
    </p>
@endsection
