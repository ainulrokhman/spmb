<?php

namespace App\Contracts;

use App\Models\SchoolUnit;
use App\Models\SelectionRule;

interface SelectionRuleServiceInterface
{
    /**
     * Create a rule for the unit, building its kind-specific
     * config from the raw form inputs.
     */
    public function createRule(SchoolUnit $unit, array $data): SelectionRule;

    /**
     * Update a rule, rebuilding its config from form inputs.
     */
    public function updateRule(SelectionRule $rule, array $data): SelectionRule;

    /**
     * Delete a rule.
     */
    public function deleteRule(SelectionRule $rule): bool;
}
