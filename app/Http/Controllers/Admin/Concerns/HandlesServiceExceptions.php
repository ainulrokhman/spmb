<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;
use LogicException;

/**
 * Runs an admin service call and converts its domain exceptions into
 * the error redirects every admin CRUD controller shares:
 *
 * - LogicException (protected resource / forbidden operation) goes
 *   back to the resource index with the error message.
 * - InvalidArgumentException (valid input, rejected value) goes back
 *   to the form with the error message.
 */
trait HandlesServiceExceptions
{
    /**
     * Run a service action and return an error redirect when the
     * service rejects the operation. Returns null on success so the
     * caller can continue with its own success redirect.
     */
    protected function runServiceAction(callable $action, string $indexRoute): ?RedirectResponse
    {
        try {
            $action();
        } catch (LogicException $e) {
            return redirect()->route($indexRoute)->with('error', $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return null;
    }
}
