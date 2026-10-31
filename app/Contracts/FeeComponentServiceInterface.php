<?php

namespace App\Contracts;

use App\Models\FeeComponent;

interface FeeComponentServiceInterface
{
    public function createFeeComponent(array $data): FeeComponent;

    public function updateFeeComponent(FeeComponent $feeComponent, array $data): FeeComponent;

    public function deleteFeeComponent(FeeComponent $feeComponent): bool;
}
