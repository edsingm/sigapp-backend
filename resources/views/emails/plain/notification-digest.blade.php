SIG.APP — Resumo de notificações {{ $periodo }}

Você tem {{ $total }} {{ $total === 1 ? 'notificação' : 'notificações' }}:

@foreach ($items as $item)
- {{ $item['title'] }}: {{ $item['body'] }}
@endforeach

Acesse o sistema para ver os detalhes.
