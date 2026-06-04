@extends('emails.layouts.base')

@section('title', 'Etapa do terreno atualizada - SIG.APP')

@section('content')
    <h2 style="margin: 0 0 16px; font-size: 20px; font-weight: 700; color: #18181b; letter-spacing: -0.01em;">Olá!</h2>

    <p style="margin: 0 0 12px; font-size: 15px; color: #52525b; line-height: 1.6;">
        O terreno <strong>{{ $terrenoNome }}</strong> mudou de etapa.
    </p>

    <div style="background-color: #f4f4f5; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 4px 8px; font-size: 13px; color: #71717a; width: 40%;">Etapa anterior</td>
                <td style="padding: 4px 8px; font-size: 14px; color: #18181b; font-weight: 600;">
                    {{ $previousStage ?: '—' }}
                </td>
            </tr>
            <tr>
                <td style="padding: 4px 8px; font-size: 13px; color: #71717a;">Nova etapa</td>
                <td style="padding: 4px 8px; font-size: 14px; color: #2563eb; font-weight: 600;">
                    {{ $newLabel }}
                </td>
            </tr>
        </table>
    </div>

    @if ($reasonNotes)
        <p style="margin: 0 0 12px; font-size: 14px; color: #52525b; line-height: 1.6; font-style: italic;">
            "{{ $reasonNotes }}"
        </p>
    @endif

    <p style="margin: 24px 0 0; font-size: 14px; color: #71717a; line-height: 1.5;">
        Acesse o sistema para mais detalhes.
    </p>
@endsection
