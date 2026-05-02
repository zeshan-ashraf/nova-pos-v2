<?php

namespace App\Http\Requests\ShopExpense;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShopExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('shop_expense.edit');
    }

    protected function prepareForValidation(): void
    {
        $user = $this->user();
        $shopExpense = $this->route('shop_expense');
        $shopId = $user->shop_id
            ? (int) $user->shop_id
            : (int) $shopExpense->shop_id;

        abort_if(!$shopId, 403);

        if ($user->shop_id) {
            abort_unless((int) $shopExpense->shop_id === (int) $user->shop_id, 403);
        }

        $this->merge(['shop_id' => $shopId]);

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
        $shopExpense = $this->route('shop_expense');
        $shopId = $user->shop_id
            ? (int) $user->shop_id
            : (int) $shopExpense->shop_id;

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
