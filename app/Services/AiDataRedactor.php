<?php

namespace App\Services;

class AiDataRedactor
{
    /**
     * Patterns de dados sensíveis para redação antes de envio ao LLM.
     */
    protected array $patterns = [
        'cpf' => '/\b(\d{3})\.(\d{3})\.(\d{3})-(\d{2})\b/',
        'cnpj' => '/\b(\d{2})\.(\d{3})\.(\d{3})\/(\d{4})-(\d{2})\b/',
        'email' => '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/',
        'telefone' => '/\b(\(?\d{2}\)?[\s-]?)?9?\d{4}[\s-]?\d{4}\b/',
        'telefone_int' => '/\b\+?\d{1,3}[\s.-]?\(?\d{2}\)?[\s.-]?\d{4,5}[\s.-]?\d{4}\b/',
    ];

    /**
     * Redacta dados sensíveis de um texto (prompt ou conteúdo).
     */
    public function redactText(string $content): string
    {
        $content = preg_replace($this->patterns['cpf'], '***.***.***-**', $content);
        $content = preg_replace($this->patterns['cnpj'], '**.***.***/****-**', $content);
        $content = preg_replace($this->patterns['email'], '[email redacted]', $content);
        $content = preg_replace($this->patterns['telefone'], '[telefone redacted]', $content);
        $content = preg_replace($this->patterns['telefone_int'], '[telefone redacted]', $content);

        return $content;
    }

    /**
     * Redacta campos sensíveis de um payload de tool.
     */
    public function redactPayload(array $data): array
    {
        $sensitiveFields = [
            'cpf',
            'cnpj',
            'email',
            'telefone',
            'phone',
            'whatsapp',
            'password',
            'senha',
            'rg',
            'passport',
            'passport_number',
        ];

        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $sensitiveFields, true)) {
                $data[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->redactPayload($value);
            }

            if (is_string($value)) {
                $redacted = $this->redactText($value);
                if ($redacted !== $value) {
                    $data[$key] = $redacted;
                }
            }
        }

        return $data;
    }

    /**
     * Redacta conteúdo de uma mensagem antes de enviar ao LLM.
     * Preserva a estrutura sem expor dados pessoais.
     */
    public function redactPrompt(string $prompt): string
    {
        return $this->redactText($prompt);
    }
}
