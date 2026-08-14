<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/** @internal */
final class PublicPortalNavigationTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        session()->destroy();
    }

    public function testPublicHomeLeadsToClientTrackingPortal(): void
    {
        $this->get('/')->assertRedirectTo('/track');
    }

    public function testTrackingPortalIsStandaloneAndOffersStaffSignIn(): void
    {
        $result = $this->get('/track');

        $result->assertOK();
        $result->assertSee('Client Tracking Portal');
        $result->assertSee('Staff sign in');
        $result->assertDontSee('Primary navigation');
        $result->assertDontSee('Sign out');
    }

    public function testLoginOffersReturnToClientTracking(): void
    {
        $result = $this->get('/login');

        $result->assertOK();
        $result->assertSee('Track a document instead');
    }
}
