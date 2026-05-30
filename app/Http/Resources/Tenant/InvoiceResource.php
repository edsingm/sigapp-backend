<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lines = $this->value('lines');

        return [
            'id' => $this->value('id'),
            'number' => $this->value('number'),
            'status' => $this->value('status'),
            'amount_due' => $this->value('amount_due'),
            'amount_paid' => $this->value('amount_paid'),
            'amount_remaining' => $this->value('amount_remaining'),
            'currency' => $this->value('currency'),
            'hosted_invoice_url' => $this->value('hosted_invoice_url'),
            'invoice_pdf' => $this->value('invoice_pdf'),
            'created_at' => $this->value('created_at'),
            'period_start' => $this->value('period_start'),
            'period_end' => $this->value('period_end'),
            'lines' => $this->when(
                $request->boolean('include_lines') && is_array($lines),
                fn () => $lines
            ),
        ];
    }

    private function value(string $key): mixed
    {
        return is_array($this->resource) ? ($this->resource[$key] ?? null) : data_get($this->resource, $key);
    }
}
