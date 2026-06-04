@extends('emails.layouts.base')

@section('title', 'Etapa de legalização atualizada - SIG.APP')

@section('content')
    <h2 style="margin: 0 0 16px; font-size: 20px; font-weight: 700; color: #18181b; letter-spacing: -0.01em;">Olá!</h2>

    <p style="margin: 0 0 12px; font-size: 15px; color: #52525b; line-height: 1.6;">
        A etapa <strong>{{ $etapaTitulo }}</strong>
        @if ($terrenoNome)
            do terreno <strong>{{ $terrenoNome }}</strong>
        @endif
        foi atualizada para <strong>{{ $status }}</strong>.
    </p>

    <p style="margin: 24px 0 0; font-size: 14px; color: #71717a; line-height: 1.5;">
        Acesse o sistema para acompanhar o progresso da legalização.
    </p>
@endsection
