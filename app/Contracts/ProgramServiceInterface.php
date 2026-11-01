<?php

namespace App\Contracts;

use App\Models\Program;

interface ProgramServiceInterface
{
    public function createProgram(array $data): Program;

    public function updateProgram(Program $program, array $data): Program;

    public function deleteProgram(Program $program): bool;
}
