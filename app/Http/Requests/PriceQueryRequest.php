<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\FailsAsJson;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Parâmetros das consultas de preço (busca, histórico, comparativos e unidades). Todos opcionais: consulta sem o
 * campo principal devolve lista vazia, como sempre; aqui só se limita tamanho.
 */
class PriceQueryRequest extends FormRequest
{
    use FailsAsJson;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'product' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:50'],
        ];
    }

    /** Termo de busca sem curingas de LIKE (`%`, `_`, `\`), que o usuário digitaria por engano ou de propósito. */
    public function searchTerm(): string
    {
        return trim((string) preg_replace('/[%_\\\\]/', ' ', (string) $this->input('q')));
    }

    public function text(string $key): string
    {
        return trim((string) $this->input($key));
    }

    public function unit(): ?string
    {
        return $this->filled('unit') ? trim((string) $this->input('unit')) : null;
    }
}
