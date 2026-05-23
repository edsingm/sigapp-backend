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
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'amount_due' => $this->amount_due,
            'amount_paid' => $this->amount_paid,
            'amount_remaining' => $this->amount_remaining,
            'currency' => $this->currency,
            'hosted_invoice_url' => $this->hosted_invoice_url,
            'invoice_pdf' => $this->invoice_pdf,
            'created_at' => $this->created_at,
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            'lines' => $this->when(
                $request->boolean('include_lines') && isset($this->lines),
                fn () => $this->lines
            ),
        ];
    }
}
