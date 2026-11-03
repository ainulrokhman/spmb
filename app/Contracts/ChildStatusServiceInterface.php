<?php

namespace App\Contracts;

use App\Models\ChildStatus;

interface ChildStatusServiceInterface
{
    public function createChildStatus(array $data): ChildStatus;

    public function updateChildStatus(ChildStatus $childStatus, array $data): ChildStatus;

    public function deleteChildStatus(ChildStatus $childStatus): bool;
}
