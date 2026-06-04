@extends('emails.layouts.base')

@section('title', 'Viabilidade decidida - SIG.APP')

@section('content')
    <h2 style="margin: 0 0 16px; font-size: 20px; font-weight: 700; color: #18181b; letter-spacing: -0.01em;">Olá!</h2>

    @if ($aprovada)
        <div style="background-color: #f0fdf4; border-left: 4px solid #16a34a; padding: 16px 20px; margin-bottom: 20px; border-radius: 4px;">
            <p style="margin: 0; font-size: 14px; font-weight: 600; color: #166534;">
                A viabilidade do terreno <strong>{{ $terrenoNome }}</strong> foi <strong>aprovada</strong>.
            </p>
        </div>
        <p style="margin: 0 0 12px; font-size: 15px; color: #52525b; line-height: 1.6;">
            O projeto pode avançar para as próximas etapas do fluxo de trabalho.
        </p>
    @else
        <div style="background-color: #fef2f2; border-left: 4px solid #dc2626; padding: 16px 20px; margin-bottom: 20px; border-radius: 4px;">
            <p style="margin: 0; font-size: 14px; font-weight: 600; color: #991b1b;">
                A viabilidade do terreno <strong>{{ $terrenoNome }}</strong> foi <strong>reprovada</strong>.
            </p>
        </div>
        <p style="margin: 0 0 12px; font-size: 15px; color: #52525b; line-height: 1.6;">
            Consulte o sistema para verificar os detalhes e os próximos passos.
        </p>
    @endif

    <p style="margin: 24px 0 0; font-size: 14px; color: #71717a; line-height: 1.5;">
        Acesse o sistema para mais informações.
    </p>
@endsection
