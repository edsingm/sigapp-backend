@extends('emails.layouts.base')

@section('title', 'Redefina sua senha - SIG.APP')

@section('content')
    <h2 style="margin: 0 0 16px; font-size: 20px; font-weight: 700; color: #18181b; letter-spacing: -0.01em;">Redefinição de senha</h2>

    <p style="margin: 0 0 12px; font-size: 15px; color: #52525b; line-height: 1.6;">
        Recebemos uma solicitação para redefinir a senha da sua conta no SIG.APP.
    </p>

    <p style="margin: 0 0 24px; font-size: 15px; color: #52525b; line-height: 1.6;">
        Clique no botão abaixo para criar uma nova senha:
    </p>

    <div style="text-align: center; margin: 24px 0;">
        <a href="{{ $resetUrl }}"
           style="display: inline-block; padding: 14px 32px; background-color: #2563eb; color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; border-radius: 8px;">
            Redefinir Senha
        </a>
    </div>

    <p style="margin: 24px 0 0; font-size: 14px; color: #71717a; line-height: 1.5;">
        Este link expira em <strong>{{ $expireMinutes }} minutos</strong>.
    </p>

    <p style="margin: 8px 0 0; font-size: 13px; color: #a1a1aa; line-height: 1.5;">
        Se você não solicitou a redefinição de senha, ignore este e-mail. Sua conta permanece segura.
    </p>
@endsection
