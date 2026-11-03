<?php

namespace App\Http\Controllers\Admin\Concerns;

/**
 * Shared mechanism for list filters that survive a round trip:
 * list pages carry their filters as query parameters, forms and
 * row actions pass them along as return_<key> inputs, and after
 * storing/updating/deleting the user is redirected back to the
 * index WITH those filters instead of a bare, unfiltered list.
 *
 * Only whitelisted keys with positive-integer values are ever
 * carried, so a crafted return value cannot inject arbitrary
 * query parameters into the redirect.
 */
trait ReturnsToFilteredIndex
{
    /**
     * Build the index URL including the submitted return filters.
     *
     * @param  array<int, string>  $keys  Filter names, e.g. ['school_unit_id']
     */
    protected function filteredIndexUrl(string $routeName, array $keys): string
    {
        $query = [];

        foreach ($keys as $key) {
            $value = request()->input('return_'.$key);

            if (is_numeric($value) && (int) $value > 0) {
                $query[$key] = (int) $value;
            }
        }

        $url = route($routeName);

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }
}
