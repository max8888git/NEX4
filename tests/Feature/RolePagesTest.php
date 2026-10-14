<?php

namespace Tests\Feature;

use Tests\TestCase;

class RolePagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_staff_roles_share_one_view_with_their_own_role_text(): void
    {
        $this->get('/admin/home-dashboard.html')
            ->assertOk()
            ->assertSee('<span class="chip">Records Officer</span>', false)
            ->assertDontSee('University Registrar</span>', false);

        $this->get('/super-admin/home-dashboard.html')
            ->assertOk()
            ->assertSee('<span class="chip">University Registrar</span>', false);
    }

    public function test_viewer_roles_share_one_view(): void
    {
        foreach (['viewing', 'viewing-with-approval', 'viewing-program-head'] as $role) {
            $this->get("/{$role}/dashboard.html")->assertOk();
        }
    }

    public function test_role_specific_pages_still_win_over_shared_views(): void
    {
        // VPAA has its own viewer page; the other viewer roles get a redirect stub.
        $this->get('/viewing-with-approval/viewer.html')->assertOk()->assertDontSee('Redirecting');
        $this->get('/viewing/viewer.html')->assertOk()->assertSee('Redirecting');
    }

    public function test_sidebar_shows_the_roles_links_and_highlights_the_current_section(): void
    {
        $this->get('/admin/data-management/facilities.html')
            ->assertOk()
            ->assertSee('id="app-nav"', false)
            ->assertSee('Transaction log')
            ->assertSee('aria-current="page"', false)
            ->assertSeeInOrder(['is-active', 'Data management'], false);

        // viewer roles get their own, shorter menu; VPAA approves instead of requesting
        $this->get('/viewing/dashboard.html')->assertOk()->assertDontSee('Transaction log');
        $this->get('/viewing-with-approval/dashboard.html')->assertOk()->assertSee('Approval')->assertSee('Room schedule');
    }

    public function test_every_role_has_a_my_account_page_and_only_super_admin_edits_it(): void
    {
        foreach (['admin', 'super-admin', 'viewing', 'viewing-with-approval', 'viewing-program-head'] as $role) {
            $this->get("/{$role}/account.html")->assertOk()->assertSee('My account')->assertSee('data-profile-menu', false);
        }
        $this->get('/super-admin/account.html')->assertSee('id="maDetailsForm"', false);
        $this->get('/viewing/account.html')->assertDontSee('id="maDetailsForm"', false)->assertSee('Ask your Records Officer or the University Registrar');
    }

    public function test_rooms_available_is_one_page_and_old_filter_links_still_work(): void
    {
        $this->get('/super-admin/rooms-available.html')->assertOk()->assertSee('id="rfPage"', false)->assertDontSee('Request allocation');
        $this->get('/admin/rooms-available-filters.html')->assertOk()->assertSee("location.replace('rooms-available.html'", false);
        $this->getJson('/api/room-availability.php')->assertStatus(401);
    }

    public function test_create_schedule_has_the_colleges_panel_for_both_staff_roles(): void
    {
        $this->get('/super-admin/create-schedule.html')->assertOk()
            ->assertSee('id="schedColleges"', false)
            ->assertSee('data-actor="super_admin"', false)
            ->assertSee('For approval')
            ->assertSee('Generate settings')
            ->assertSee('id="schedFind"', false)
            ->assertSee('Needs attention');
        $this->get('/admin/create-schedule.html')->assertOk()
            ->assertSee('id="schedColleges"', false)
            ->assertSee('data-actor="admin"', false)
            ->assertSee('Sent for approval');
    }

    public function test_only_the_vpaa_gets_the_colleges_panel_on_reports(): void
    {
        $this->get('/viewing-with-approval/final-report.html')->assertOk()
            ->assertSee('id="schedColleges"', false)
            ->assertSee('For your approval')
            ->assertSee('id="schedFind"', false);
        foreach (['viewing', 'viewing-program-head'] as $role) {
            $this->get("/{$role}/final-report.html")->assertOk()->assertDontSee('id="schedColleges"', false);
        }
    }

    public function test_curriculum_pages_for_the_colleges_and_the_registrars(): void
    {
        foreach (['viewing', 'viewing-program-head'] as $role) {
            $this->get("/{$role}/curriculum-builder.html")->assertOk()->assertSee('id="cbRoot"', false)->assertSee('curriculum-builder', false);
            $this->get("/{$role}/dashboard.html")->assertOk()->assertSee('Curriculum')
                ->assertSee('id="ctRoot"', false)->assertDontSee('class="dept-cards-section', false);
            $this->get("/{$role}/curriculum-builder.html")->assertSee('id="cbTemplate"', false);
        }
        $this->get('/viewing-program-head/dashboard.html')->assertSee('href="curriculum-builder.html" aria-label="New curriculum">+ New</a>', false);
        $this->get('/viewing/dashboard.html')->assertSee('+ Request');
        // the VPAA's dashboard keeps its approval card instead
        $this->get('/viewing-with-approval/dashboard.html')->assertOk()->assertDontSee('id="ctRoot"', false);
        foreach (['admin', 'super-admin'] as $role) {
            $this->get("/{$role}/curriculums/review.html")->assertOk()->assertSee('id="crRoot"', false)->assertSee('From the colleges');
            // one Curriculums page: read-only list + viewer; the old filter page and Data management → Curriculums redirect to it
            $this->get("/{$role}/curriculums/curriculums.html")->assertOk()->assertSee('id="clRoot"', false)->assertSee('href="review.html"', false);
            $this->get("/{$role}/curriculums/curriculum-filters.html")->assertOk()->assertSee("location.replace('curriculums.html')", false);
            $this->get("/{$role}/data-management/add-curriculum.html")->assertOk()->assertSee("location.replace('../curriculums/curriculums.html')", false);
            $this->get("/{$role}/data-management/sections.html")->assertOk()->assertDontSee('add-curriculum.html', false);
        }
    }

    public function test_pages_a_role_never_had_are_still_404(): void
    {
        $this->get('/viewing/viewer-rooms.html')->assertNotFound();
        $this->get('/viewing/home-dashboard.html')->assertNotFound();
        $this->get('/nobody/home-dashboard.html')->assertNotFound();
        $this->get('/admin')->assertNotFound();
    }
}
