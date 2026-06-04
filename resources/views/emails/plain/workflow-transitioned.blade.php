Ola!

O terreno {{ $terrenoNome }} mudou de etapa.

Etapa anterior: {{ $previousStage ?: '—' }}
Nova etapa: {{ $newLabel }}
@if ($reasonNotes)
Observacao: "{{ $reasonNotes }}"
@endif

Acesse o sistema para mais detalhes.

---
Atenciosamente,
Equipe SIG.APP
