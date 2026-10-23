<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'      => ['sometimes', 'string', 'max:100'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'status'      => ['sometimes', 'string', 'in:active,inactive'],
            'low_stock'   => ['sometimes', 'boolean'],
            'per_page'    => ['sometimes', 'integer', 'min:1', 'max:50'],
            'sort_by'     => ['sometimes', 'string', 'in:id,name,sku,price,stock,status,created_at'],
            'direction'   => ['sometimes', 'string', 'in:asc,desc,ASC,DESC'],
        ];
    }
}
