<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Base das requisições de listagem (GET index).
 *
 * Valida os filtros da query string, a paginação (per_page, máx. 100) e a
 * ordenação, que só aceita colunas declaradas em sortable() para impedir
 * ordenação por colunas arbitrárias. Formato: ?sort=campo ou ?sort=-campo (desc).
 * Os filtros validados são aplicados pelo scope filter() de cada model.
 */
abstract class ListRequest extends FormRequest
{
    abstract protected function filterRules(): array;

    /**
     * @return list<string>
     */
    abstract protected function sortable(): array;

    abstract protected function defaultSort(): string;

    public function rules(): array
    {
        $sortOptions = collect($this->sortable())->flatMap(fn (string $column) => [$column, "-{$column}"])->all();

        return [
            ...$this->filterRules(),
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'sort' => ['sometimes', 'string', Rule::in($sortOptions)],
        ];
    }

    public function filters(): array
    {
        return collect($this->validated())->except(['per_page', 'sort', 'page'])->all();
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', 15);
    }

    /**
     * @return array{string, string}
     */
    public function sort(): array
    {
        $sort = $this->validated('sort', $this->defaultSort());

        return str_starts_with($sort, '-') ? [substr($sort, 1), 'desc'] : [$sort, 'asc'];
    }
}
