<?php

namespace App\Services;

use App\Contracts\ProgramServiceInterface;
use App\Models\Program;

class ProgramService implements ProgramServiceInterface
{
    /**
     * Store a newly created program.
     */
    public function createProgram(array $data): Program
    {
        return Program::create($this->programAttributes($data));
    }

    /**
     * Update the specified program.
     */
    public function updateProgram(Program $program, array $data): Program
    {
        $program->update($this->programAttributes($data));

        return $program;
    }

    /**
     * Delete the specified program.
     */
    public function deleteProgram(Program $program): bool
    {
        return (bool) $program->delete();
    }

    /**
     * Map validated form data to model attributes.
     */
    private function programAttributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => ! empty($data['is_active']),
        ];
    }
}
