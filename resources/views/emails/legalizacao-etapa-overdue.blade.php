@extends('emails.layouts.base')

@section('title', 'Etapa de legalização atrasada - SIG.APP')

@section('content')
    <h2 style="margin: 0 0 16px; font-size: 20px; font-weight: 700; color: #18181b; letter-spacing: -0.01em;">Olá!</h2>

    <div style="background-color: #fef2f2; border-left: 4px solid #dc2626; padding: 16px 20px; margin-bottom: 20px; border-radius: 4px;">
        <p style="margin: 0; font-size: 14px; font-weight: 600; color: #991b1b;">
            Uma etapa de legalização está atrasada!
        </p>
    </div>

    <p style="margin: 0 0 12px; font-size: 15px; color: #52525b; line-height: 1.6;">
        A etapa <strong>{{ $etapaNome }}</strong>
        @if ($terrenoNome)
            do terreno <strong>{{ $terrenoNome }}</strong>
        @endif
        está com o prazo vencido.
    </p>

    <p style="margin: 24px 0 0; font-size: 14px; color: #71717a; line-height: 1.5;">
        Acesse o sistema para regularizar a situação o quanto antes.
    </p>
@endsection
