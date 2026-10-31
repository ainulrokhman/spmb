<?php

namespace App\Http\Requests\FeeAmount;

use App\Http\Requests\FeeAmount\Concerns\ValidatesFeeAmount;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFeeAmountRequest extends FormRequest
{
    use ValidatesFeeAmount;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
