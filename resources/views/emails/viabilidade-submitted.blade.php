@extends('emails.layouts.base')

@section('title', 'Viabilidade aguardando aprovação - SIG.APP')

@section('content')
    <h2 style="margin: 0 0 16px; font-size: 20px; font-weight: 700; color: #18181b; letter-spacing: -0.01em;">Olá!</h2>

    <div style="background-color: #eff6ff; border-left: 4px solid #2563eb; padding: 16px 20px; margin-bottom: 20px; border-radius: 4px;">
        <p style="margin: 0; font-size: 14px; font-weight: 600; color: #1e40af;">
            Uma nova viabilidade aguarda sua decisão.
        </p>
    </div>

    <p style="margin: 0 0 12px; font-size: 15px; color: #52525b; line-height: 1.6;">
        A viabilidade do terreno <strong>{{ $terrenoNome }}</strong> foi submetida para aprovação.
    </p>

    <p style="margin: 24px 0 0; font-size: 14px; color: #71717a; line-height: 1.5;">
        Acesse o sistema para revisar os dados e tomar uma decisão.
    </p>
@endsection
