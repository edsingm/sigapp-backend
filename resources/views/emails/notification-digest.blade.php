@extends('emails.layouts.base')

@section('title', 'Resumo de notificações - SIG.APP')

@section('content')
    <h2 style="margin: 0 0 16px; font-size: 20px; font-weight: 700; color: #18181b; letter-spacing: -0.01em;">Olá!</h2>

    <p style="margin: 0 0 20px; font-size: 15px; color: #52525b; line-height: 1.6;">
        Você tem <strong>{{ $total }}</strong> {{ $total === 1 ? 'notificação' : 'notificações' }} no seu resumo {{ $periodo }}.
    </p>

    @foreach ($items as $item)
        <div style="border-left: 4px solid #2563eb; padding: 14px 18px; margin-bottom: 14px; background-color: #f8fafc; border-radius: 4px;">
            <p style="margin: 0 0 4px; font-size: 15px; font-weight: 600; color: #18181b;">{{ $item['title'] }}</p>
            <p style="margin: 0; font-size: 14px; color: #52525b; line-height: 1.5;">{{ $item['body'] }}</p>
        </div>
    @endforeach

    <p style="margin: 24px 0 0; font-size: 14px; color: #71717a; line-height: 1.5;">
        Acesse o sistema para ver os detalhes.
    </p>
@endsection
