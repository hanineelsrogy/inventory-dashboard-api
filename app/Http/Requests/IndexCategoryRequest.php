<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'sort_by' => ['sometimes', 'string', 'in:id,name,slug,is_active,created_at,products_count'],
            'direction' => ['sometimes', 'string', 'in:asc,desc,ASC,DESC'],
        ];
    }
}
