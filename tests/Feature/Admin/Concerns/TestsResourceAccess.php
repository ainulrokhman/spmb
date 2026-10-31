<?php

namespace Tests\Feature\Admin\Concerns;

/**
 * Shared access-control assertions for the admin resource index pages.
 *
 * The using test class must also use the SeedsDemoUsers trait and
 * identify its resource through the two abstract methods below.
 */
trait TestsResourceAccess
{
    /**
     * The route name of the resource index under test.
     */
    abstract protected function indexRouteName(): string;

    /**
     * The view rendered by the resource index under test.
     */
    abstract protected function indexViewName(): string;

    /**
     * Assert a guest asking for the index is sent to the login page.
     */
    protected function assertGuestIsRedirectedToLogin(): void
    {
        $response = $this->get(route($this->indexRouteName()));

        $response->assertRedirect(route('login'));
    }

    /**
     * Assert a user without the list permission gets a 403.
     */
    protected function assertUserWithoutPermissionCannotAccessList(): void
    {
        $response = $this->actingAs($this->regularUser())
            ->get(route($this->indexRouteName()));

        $response->assertForbidden();
    }

    /**
     * Assert an admin can open the index page.
     */
    protected function assertAdminCanViewList(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route($this->indexRouteName()));

        $response->assertStatus(200);
        $response->assertViewIs($this->indexViewName());
    }
}
