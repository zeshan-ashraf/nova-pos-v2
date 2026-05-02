<?php

namespace App\Http\Requests\ShopExpense;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShopExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('shop_expense.create');
    }

    protected function prepareForValidation(): void
    {
        $user = $this->user();
        abort_if(!$user || !$user->shop_id, 403);

        $this->merge(['shop_id' => (int) $user->shop_id]);

        if ($this->input('payment_type') === 'cash') {
            $this->merge(['bank_id' => null]);
        }

        if ($this->input('expense_id') === '' || $this->input('expense_id') === null) {
            $this->merge(['expense_id' => null]);
        }
    }

    public function rules(): array
    {
        $user = $this->user();
        $shopId = (int) $user->shop_id;

        return [
            'expense_id' => [
                'nullable',
                Rule::exists('expenses', 'id')->where(function ($query) use ($shopId) {
                    $query->where('shop_id', $shopId);
                }),
            ],
            'expense_date' => ['required', 'date'],
            'shop_id' => ['required', 'integer', 'exists:shops,id', Rule::in([$shopId])],
            'amount' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'payment_type' => ['required', 'in:cash,bank'],
            'bank_id' => [
                'nullable',
                'required_if:payment_type,bank',
                'exists:banks,id',
            ],
        ];
    }
}
