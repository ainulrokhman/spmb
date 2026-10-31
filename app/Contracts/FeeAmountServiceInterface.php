<?php

namespace App\Contracts;

use App\Models\FeeAmount;

interface FeeAmountServiceInterface
{
    public function createFeeAmount(array $data): FeeAmount;

    public function updateFeeAmount(FeeAmount $feeAmount, array $data): FeeAmount;

    public function deleteFeeAmount(FeeAmount $feeAmount): bool;
}
