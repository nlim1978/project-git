<?php

use App\Database\Seeds\AdminReferenceSeeder;
use App\Services\AuthorizationService;
use App\Services\ClientTrackingService;
use App\Services\DocumentArchiveService;
use App\Services\DocumentReceivingService;
use App\Services\DocumentRoutingService;
use App\Services\OrganizationScopeService;
use App\Services\RoutingDestinationService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Regression coverage for the authorization boundaries that protect document
 * visibility, engagement state, and routing mutations.
 *
 * These are intentionally SQL Server integration tests. The production routing
 * path relies on SQL Server locks and triggers, so SQLite would not verify the
 * behavior that matters here.
 *
 * @internal
 */
final class AuthorizationRoutingRegressionTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';
    protected $migrateOnce = true;
    protected $refresh = true;
    protected $seed = AdminReferenceSeeder::class;
    protected $seedOnce = true;

    private DocumentRoutingService $routing;
    private int $idSequence = 1;

    public function testSectionHeadCanRouteButCannotReceiveIncomingDocuments(): void
    {
        $head = $this->user('head-boundary', 'REC', 'Section Head');
        $authorization = new AuthorizationService($this->db);

        $this->assertTrue($authorization->hasPermission($head, 'Document Routing', 'Routing', 'ROUTE'));
        $this->assertFalse($authorization->hasPermission($head, 'Receiving', 'Receiving', 'VIEW'));
        $this->assertFalse($authorization->hasPermission($head, 'Receiving', 'Receiving', 'CREATE'));
        $this->assertFalse($authorization->hasPermission($head, 'Receiving', 'Receiving', 'UPDATE'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->db->DBDriver !== 'SQLSRV') {
            $this->fail('Authorization/routing integration tests require the SQLSRV test database. See tests/README.md.');
        }

        // Keep the seeded reference data, but roll every per-test fixture and
        // routing mutation back so tests remain independent.
        $this->db->transBegin();
        $this->routing = new DocumentRoutingService();
    }

    protected function tearDown(): void
    {
        $this->db->transRollback();
        parent::tearDown();
    }

    public function testAssignedUserCanReadEngagementState(): void
    {
        $assigned = $this->user('assigned', 'REC', 'Personnel');
        $receiver = $this->user('receiver', 'REC', 'Receiving Personnel');
        $documentId = $this->document('REC', $assigned, $receiver);

        $state = $this->routing->engagementState($documentId, $assigned);

        $this->assertNotNull($state);
        $this->assertFalse($state['active']);
    }

    public function testSameOfficeUnrelatedUserCannotReadEngagementState(): void
    {
        $assigned = $this->user('assigned', 'REC', 'Personnel');
        $receiver = $this->user('receiver', 'REC', 'Receiving Personnel');
        $unrelated = $this->user('unrelated', 'HR', 'Personnel');
        $documentId = $this->document('REC', $assigned, $receiver);

        $this->assertNull($this->routing->engagementState($documentId, $unrelated));
    }

    public function testSameSectionPeerCannotReadPersonallyAssignedDocumentEngagement(): void
    {
        $assigned = $this->user('assigned', 'REC', 'Personnel');
        $receiver = $this->user('receiver', 'REC', 'Receiving Personnel');
        $peer = $this->user('peer', 'REC', 'Personnel');
        $documentId = $this->document('REC', $assigned, $receiver);

        $this->assertNull($this->routing->engagementState($documentId, $peer));
    }

    public function testCrossOfficeUserCannotReadEngagementState(): void
    {
        $assigned = $this->user('assigned', 'REC', 'Personnel');
        $receiver = $this->user('receiver', 'REC', 'Receiving Personnel');
        $otherOffice = $this->user('finance', 'ACC', 'Personnel');
        $documentId = $this->document('REC', $assigned, $receiver);

        $this->assertNull($this->routing->engagementState($documentId, $otherOffice));
    }

    public function testCurrentSectionHeadAndSameOfficeMonitorCanReadEngagementState(): void
    {
        $assigned = $this->user('assigned', 'REC', 'Personnel');
        $receiver = $this->user('receiver', 'REC', 'Receiving Personnel');
        $head = $this->user('head', 'REC', 'Section Head');
        $monitor = $this->user('monitor', 'HR', 'Monitoring Officer');
        $documentId = $this->document('REC', $assigned, $receiver);

        $this->assertNotNull($this->routing->engagementState($documentId, $head));
        $this->assertNotNull($this->routing->engagementState($documentId, $monitor));
    }

    public function testDocumentDataScopeDistinguishesGlobalOfficeAndRestrictedSections(): void
    {
        $personnel = $this->user('personnel', 'HR', 'Personnel');
        $head = $this->user('head', 'REC', 'Section Head');
        $adminId = $this->referenceId('users', 'username', 'admin', 'user_id');
        $officeId = $this->referenceId('offices', 'office_code', 'OAS', 'office_id');
        $recordsSectionId = $this->sectionId('REC');
        $scope = new OrganizationScopeService($this->db);

        $officeScope = $scope->documentDataScope($personnel);
        $this->assertFalse($officeScope->isGlobal());
        $this->assertSame(strtolower($officeId), strtolower((string) $officeScope->officeId()));
        $this->assertNull($officeScope->sectionIds());

        $headScope = $scope->documentDataScope($head);
        $this->assertTrue($headScope->restrictsSections());
        $this->assertContains(strtolower($recordsSectionId), array_map('strtolower', $headScope->sectionIds() ?? []));
        $this->assertTrue($scope->canManageDocumentsInSection($head, $recordsSectionId));
        $this->assertFalse($scope->canManageDocumentsInSection($head, $this->sectionId('HR')));

        $globalScope = $scope->documentDataScope($adminId);
        $this->assertTrue($globalScope->isGlobal());
        $this->assertNull($globalScope->officeId());
        $this->assertNull($globalScope->sectionIds());
    }

    public function testExplicitSectionHeadUsesSamePolicyAndDataScopeAsRoleBasedHead(): void
    {
        $explicitHead = $this->user('explicit-head', 'REC', 'Personnel');
        $assigned = $this->user('assigned', 'REC', 'Personnel');
        $receiver = $this->user('receiver', 'REC', 'Receiving Personnel');
        $recordsSectionId = $this->sectionId('REC');
        $this->db->table('sections')->where('section_id', $recordsSectionId)
            ->update(['head_user_id' => $explicitHead]);
        $documentId = $this->document('REC', $assigned, $receiver);

        $scope = (new OrganizationScopeService($this->db))->documentDataScope($explicitHead);

        $this->assertSame([strtolower($recordsSectionId)], array_map('strtolower', $scope->sectionIds() ?? []));
        $this->assertNotNull($this->routing->engagementState($documentId, $explicitHead));
        $this->assertFalse((new OrganizationScopeService($this->db))->canManageDocumentsInSection($explicitHead, $this->sectionId('HR')));
    }

    public function testQueueMemberCanSeeQueueDocumentButOnlySectionHeadCanConfirmIt(): void
    {
        $receiver = $this->user('receiver', 'REC', 'Receiving Personnel');
        $member = $this->user('member', 'REC', 'Personnel');
        $head = $this->user('head', 'REC', 'Section Head');
        $documentId = $this->document('REC', null, $receiver);

        $this->assertNotNull($this->routing->engagementState($documentId, $member));

        try {
            $this->routing->confirmEngagement($documentId, $member);
            $this->fail('A normal section member must not confirm an unassigned section queue item.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('current Section Head', $e->getMessage());
        }

        $state = $this->routing->confirmEngagement($documentId, $head);
        $this->assertTrue($state['active']);
        $this->assertTrue($state['owned_by_actor']);
    }

    public function testUnauthorizedHeartbeatDoesNotExposeAnotherUsersEngagement(): void
    {
        $assigned = $this->user('assigned', 'REC', 'Personnel');
        $receiver = $this->user('receiver', 'REC', 'Receiving Personnel');
        $unrelated = $this->user('unrelated', 'HR', 'Personnel');
        $documentId = $this->document('REC', $assigned, $receiver);
        $this->routing->confirmEngagement($documentId, $assigned);

        $this->assertSame([], $this->routing->heartbeatEngagement($documentId, $unrelated));
        $this->assertNull($this->routing->engagementState($documentId, $unrelated));
    }

    public function testAssignedPersonnelCanRouteAndRoutingIsAudited(): void
    {
        $assigned = $this->user('assigned', 'REC', 'Personnel');
        $receiver = $this->user('receiver', 'REC', 'Receiving Personnel');
        $destination = $this->user('destination', 'HR', 'Personnel');
        $documentId = $this->document('REC', $assigned, $receiver);
        $destinationSectionId = $this->sectionId('HR');

        $this->routing->route($documentId, [
            'document_version' => $this->documentVersion($documentId),
            'action_id' => $this->actionId('Processed'),
            'destination_section_id' => $destinationSectionId,
            'destination_user_id' => $destination,
            'remarks' => '',
        ], $assigned, '127.0.0.1', 'PHPUnit');

        $document = $this->db->table('documents')
            ->select('current_section_id, current_responsible_user_id')
            ->where('document_id', $documentId)->get()->getRowArray();

        $this->assertSame(strtolower($destinationSectionId), strtolower((string) $document['current_section_id']));
        $this->assertSame(strtolower($destination), strtolower((string) $document['current_responsible_user_id']));
        $this->assertSame(1, $this->db->table('routing_history')->where('document_id', $documentId)->countAllResults());
        $this->assertSame(1, $this->db->table('audit_logs')
            ->where('document_id', $documentId)->where('action_name', 'ROUTE')->countAllResults());
    }

    public function testRoutingRequiresAnExplicitActionChoice(): void
    {
        $assigned = $this->user('assigned', 'REC', 'Personnel');
        $receiver = $this->user('receiver', 'REC', 'Receiving Personnel');
        $documentId = $this->document('REC', $assigned, $receiver);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Select the action taken');

        $this->routing->route($documentId, [
            'document_version' => $this->documentVersion($documentId),
            'action_id' => '',
            'destination_section_id' => $this->sectionId('REC'),
            'destination_user_id' => $assigned,
            'remarks' => '',
        ], $assigned);
    }

    public function testUserCanExplicitlyChooseRoutingOnly(): void
    {
        $assigned = $this->user('assigned', 'REC', 'Personnel');
        $receiver = $this->user('receiver', 'REC', 'Receiving Personnel');
        $destination = $this->user('destination', 'HR', 'Personnel');
        $documentId = $this->document('REC', $assigned, $receiver);

        $this->routing->route($documentId, [
            'document_version' => $this->documentVersion($documentId),
            'action_id' => 'route_only',
            'destination_section_id' => $this->sectionId('HR'),
            'destination_user_id' => $destination,
            'remarks' => '',
        ], $assigned);

        $route = $this->db->table('routing_history')->select('action_id')
            ->where('document_id', $documentId)->get()->getRowArray();
        $this->assertNotNull($route);
        $this->assertNull($route['action_id']);
    }

    public function testStaleRoutingSubmissionCannotCreateASecondMovement(): void
    {
        $assigned = $this->user('assigned', 'REC', 'Personnel');
        $receiver = $this->user('receiver', 'REC', 'Receiving Personnel');
        $documentId = $this->document('REC', $assigned, $receiver);
        $version = $this->documentVersion($documentId);
        $input = [
            'document_version' => $version,
            'action_id' => $this->actionId('Processed'),
            'destination_section_id' => $this->sectionId('REC'),
            'destination_user_id' => $assigned,
            'remarks' => '',
        ];

        $this->routing->route($documentId, $input, $assigned);

        try {
            $this->routing->route($documentId, $input, $assigned);
            $this->fail('A stale routing submission must not append a second movement.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('changed after you opened it', $e->getMessage());
        }
        $this->assertSame(1, $this->db->table('routing_history')->where('document_id', $documentId)->countAllResults());
    }

    public function testSameSectionPeerCannotRoutePersonallyAssignedDocument(): void
    {
        $assigned = $this->user('assigned', 'REC', 'Personnel');
        $receiver = $this->user('receiver', 'REC', 'Receiving Personnel');
        $peer = $this->user('peer', 'REC', 'Personnel');
        $destination = $this->user('destination', 'HR', 'Personnel');
        $documentId = $this->document('REC', $assigned, $receiver);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not authorized to route');

        $this->routing->route($documentId, [
            'action_id' => $this->actionId('Processed'),
            'destination_section_id' => $this->sectionId('HR'),
            'destination_user_id' => $destination,
            'remarks' => '',
        ], $peer);
    }

    public function testAssignmentAloneDoesNotGrantRoutingWithoutRoutePermission(): void
    {
        $monitor = $this->user('monitor', 'REC', 'Monitoring Officer');
        $receiver = $this->user('receiver', 'REC', 'Receiving Personnel');
        $documentId = $this->document('REC', $monitor, $receiver);

        $document = $this->routing->document($documentId, $monitor);

        $this->assertNotNull($document);
        $this->assertFalse($document['can_route_from_assignment']);
    }

    public function testArchiveReturnsFiledDocumentsAndExcludesActiveDocuments(): void
    {
        $assigned = $this->user('assigned', 'REC', 'Personnel');
        $receiver = $this->user('receiver', 'REC', 'Receiving Personnel');
        $monitor = $this->user('monitor', 'REC', 'Monitoring Officer');
        $filedDocumentId = $this->document('REC', $assigned, $receiver);
        $activeDocumentId = $this->document('REC', $assigned, $receiver);

        $filedAction = $this->db->table('routing_actions')->select('action_id, resulting_status_id')
            ->where('action_name', 'Filed')->get()->getRowArray();
        $this->assertNotNull($filedAction);
        $this->db->table('routing_history')->insert([
            'routing_id' => $this->uuid('4'),
            'document_id' => $filedDocumentId,
            'from_section_id' => $this->sectionId('REC'),
            'from_user_id' => $assigned,
            'destination_section_id' => $this->sectionId('REC'),
            'destination_user_id' => $assigned,
            'action_id' => $filedAction['action_id'],
            'resulting_status_id' => $filedAction['resulting_status_id'],
            'remarks' => null,
            'routed_by' => $assigned,
            'is_reassigned' => 0,
        ]);
        $this->db->table('documents')->where('document_id', $filedDocumentId)->update([
            'status_id' => $filedAction['resulting_status_id'],
        ]);

        $archive = new DocumentArchiveService($this->db);
        $rows = $archive->search($archive->normalizeFilters([]), $monitor);
        $ids = array_map('strtolower', array_column($rows, 'document_id'));
        $filedRows = array_values(array_filter($rows, static fn (array $row): bool => strtolower((string) $row['document_id']) === strtolower($filedDocumentId)));

        $this->assertContains(strtolower($filedDocumentId), $ids);
        $this->assertNotContains(strtolower($activeDocumentId), $ids);
        $this->assertCount(1, $filedRows);
        $this->assertSame('Filed', $filedRows[0]['archive_state']);
    }

    public function testCrossOfficeUserCannotRouteDocument(): void
    {
        $assigned = $this->user('assigned', 'REC', 'Personnel');
        $receiver = $this->user('receiver', 'REC', 'Receiving Personnel');
        $otherOffice = $this->user('finance', 'ACC', 'Personnel');
        $documentId = $this->document('REC', $assigned, $receiver);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('outside your office scope');

        $this->routing->route($documentId, [
            'action_id' => $this->actionId('Processed'),
            'destination_section_id' => $this->sectionId('ACC'),
            'destination_user_id' => $otherOffice,
            'remarks' => '',
        ], $otherOffice);
    }

    public function testRoutingDestinationResolverFeedsTheSameOptionsItValidates(): void
    {
        $actor = $this->user('router', 'REC', 'Personnel');
        $hrUser = $this->user('hr-destination', 'HR', 'Personnel');
        $resolver = new RoutingDestinationService($this->db);
        $options = $resolver->options($actor);
        $sectionIds = array_map('strtolower', array_column($options['sections'], 'section_id'));
        $hrSectionId = $this->sectionId('HR');

        $this->assertContains(strtolower($this->sectionId('REC')), $sectionIds);
        $this->assertContains(strtolower($hrSectionId), $sectionIds);
        $this->assertNotContains(strtolower($this->sectionId('ACC')), $sectionIds);

        $resolver->assertAllowed($actor, $hrSectionId, $hrUser);
        $matchingUsers = array_filter($options['section_users'], static fn (array $row): bool =>
            strtolower((string) $row['section_id']) === strtolower($hrSectionId)
            && strtolower((string) $row['user_id']) === strtolower($hrUser));
        $this->assertCount(1, $matchingUsers);
    }

    public function testSectionHeadRoutingScopeDoesNotExpandDocumentVisibilityScope(): void
    {
        $head = $this->user('head', 'REC', 'Section Head');
        $scope = (new OrganizationScopeService($this->db))->documentDataScope($head);
        $options = (new RoutingDestinationService($this->db))->options($head);
        $routingSectionIds = array_map('strtolower', array_column($options['sections'], 'section_id'));

        $this->assertSame([strtolower($this->sectionId('REC'))], array_map('strtolower', $scope->sectionIds() ?? []));
        $this->assertContains(strtolower($this->sectionId('HR')), $routingSectionIds);
        $this->assertNotContains(strtolower($this->sectionId('ACC')), $routingSectionIds);
    }

    public function testActiveWorkLockBlocksSectionHeadReassignment(): void
    {
        $assigned = $this->user('assigned', 'REC', 'Personnel');
        $receiver = $this->user('receiver', 'REC', 'Receiving Personnel');
        $head = $this->user('head', 'REC', 'Section Head');
        $peer = $this->user('peer', 'REC', 'Personnel');
        $documentId = $this->document('REC', $assigned, $receiver);
        $this->routing->confirmEngagement($documentId, $assigned);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('currently being handled');

        $this->routing->reassign($documentId, [
            'destination_section_id' => $this->sectionId('REC'),
            'destination_user_id' => $peer,
        ], $head);
    }

    public function testReceivingFacadeRegistersAndUpdatesWithAuditTrail(): void
    {
        $receiver = $this->user('receiver', 'REC', 'Receiving Personnel');
        $receiving = new DocumentReceivingService();
        $input = [
            'document_type_id' => $this->referenceId('document_types', 'type_code', 'MEM', 'document_type_id'),
            'initial_section_id' => $this->sectionId('REC'),
            'initial_responsible_user_id' => $receiver,
            'subject' => 'Receiving command regression',
            'description' => 'Registration through the receiving facade',
            'sender_name' => 'Regression Sender',
            'sender_organization' => 'Test Suite',
            'sender_email' => 'sender@example.test',
            'sender_contact_number' => '',
            'remarks' => '',
            'send_email_notification' => '0',
        ];

        $documentId = $receiving->register($input, [], $receiver, '127.0.0.1', 'PHPUnit');
        $this->assertSame(1, $this->db->table('audit_logs')
            ->where('document_id', $documentId)->where('action_name', 'CREATE')->countAllResults());

        $input['document_version'] = $this->documentVersion($documentId);
        $input['subject'] = 'Corrected receiving command regression';
        $receiving->updateDocument($documentId, $input, $receiver, '127.0.0.1', 'PHPUnit');

        $row = $this->db->table('documents')->select('subject')->where('document_id', $documentId)->get()->getRowArray();
        $this->assertSame('Corrected receiving command regression', (string) $row['subject']);
        $this->assertSame(1, $this->db->table('audit_logs')
            ->where('document_id', $documentId)->where('action_name', 'UPDATE')->countAllResults());

        $input['subject'] = 'Stale overwrite attempt';
        try {
            $receiving->updateDocument($documentId, $input, $receiver, '127.0.0.1', 'PHPUnit');
            $this->fail('A stale receiving form must not overwrite a newer correction.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('changed after you opened it', $e->getMessage());
        }
        $row = $this->db->table('documents')->select('subject')->where('document_id', $documentId)->get()->getRowArray();
        $this->assertSame('Corrected receiving command regression', (string) $row['subject']);
    }

    public function testDatabaseEnforcesOneUnendedEngagementPerDocument(): void
    {
        $row = $this->db->query(
            "SELECT COUNT(*) AS index_count FROM sys.indexes WHERE [name] = N'UX_document_engagements_one_active' AND [object_id] = OBJECT_ID(N'dbo.document_engagements')"
        )->getRowArray();

        $this->assertSame(1, (int) ($row['index_count'] ?? 0));
    }

    public function testReceivingFacadeKeepsSectionHeadRegistrationInsideManagedSections(): void
    {
        $head = $this->user('head', 'REC', 'Section Head');
        $receiving = new DocumentReceivingService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('outside your office scope');

        $receiving->register([
            'document_type_id' => $this->referenceId('document_types', 'type_code', 'MEM', 'document_type_id'),
            'initial_section_id' => $this->sectionId('HR'),
            'initial_responsible_user_id' => '',
            'subject' => 'Out-of-scope receiving regression',
            'description' => 'Must remain blocked after command extraction',
            'sender_name' => 'Regression Sender',
            'sender_organization' => '',
            'sender_email' => 'sender@example.test',
            'sender_contact_number' => '',
            'remarks' => '',
            'send_email_notification' => '0',
        ], [], $head);
    }

    public function testClientTrackingProjectionNeverReturnsInternalAuthorizationData(): void
    {
        $assigned = $this->user('assigned', 'REC', 'Personnel');
        $receiver = $this->user('receiver', 'REC', 'Receiving Personnel');
        $documentId = $this->document('REC', $assigned, $receiver);
        $tokenRow = $this->db->table('documents')->select('client_tracking_token')
            ->where('document_id', $documentId)->get()->getRowArray();
        $this->assertNotNull($tokenRow);
        $token = (string) $tokenRow['client_tracking_token'];

        $status = (new ClientTrackingService($this->db))->status($token);

        $this->assertNotNull($status);
        $this->assertSame(
            ['reference', 'document_type', 'subject', 'date_received', 'status', 'current_section', 'last_activity', 'timeline'],
            array_keys($status)
        );
        foreach (['document_id', 'sender_name', 'sender_email', 'remarks', 'current_responsible_user_id', 'client_tracking_token'] as $internalKey) {
            $this->assertArrayNotHasKey($internalKey, $status);
        }
    }

    private function user(string $label, string $sectionCode, string $roleName): string
    {
        $id = $this->uuid();
        $safe = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $label) ?? 'user');
        $unique = $this->idSequence;

        $this->db->table('users')->insert([
            'user_id' => $id,
            'employee_id' => 'T-' . $unique . '-' . strtoupper(substr($safe, 0, 8)),
            'username' => 'test-' . $safe . '-' . $unique,
            // Authentication is outside this fixture's scope; avoid expensive
            // password hashing for users that will never log in.
            'password_hash' => 'not-used-by-regression-tests',
            'first_name' => ucfirst($safe),
            'middle_name' => null,
            'last_name' => 'Regression',
            'email' => 'test-' . $safe . '-' . $unique . '@example.test',
            'contact_number' => null,
            'account_status' => 'Active',
            'telegram_notification_enabled' => 0,
        ]);
        $this->db->table('user_sections')->insert([
            'user_id' => $id,
            'section_id' => $this->sectionId($sectionCode),
            'is_primary' => 1,
        ]);
        $this->db->table('user_roles')->insert([
            'user_id' => $id,
            'role_id' => $this->roleId($roleName),
        ]);

        return $id;
    }

    private function document(string $sectionCode, ?string $responsibleUserId, string $receiverId): string
    {
        $id = $this->uuid('2');
        $sectionId = $this->sectionId($sectionCode);
        $sequence = $this->idSequence;

        $this->db->table('documents')->insert([
            'document_id' => $id,
            'receiving_number' => 'TEST-R-' . $sequence,
            'document_control_number' => 'TEST-D-' . $sequence,
            'qr_token' => str_pad(dechex($sequence), 64, '0', STR_PAD_LEFT),
            'client_tracking_token' => str_pad(dechex($sequence), 32, '0', STR_PAD_LEFT),
            'client_tracking_reference' => sprintf('TRK-0126-%04d', $sequence),
            'document_type_id' => $this->referenceId('document_types', 'type_code', 'MEM', 'document_type_id'),
            'subject' => 'Authorization regression document ' . $sequence,
            'description' => 'Test-only fixture',
            'sender_name' => 'Regression Sender',
            'sender_organization' => 'Test Suite',
            'sender_email' => 'sender@example.test',
            'sender_contact_number' => null,
            'receiving_personnel_id' => $receiverId,
            'initial_section_id' => $sectionId,
            'initial_responsible_user_id' => $responsibleUserId,
            'current_section_id' => $sectionId,
            'current_responsible_user_id' => $responsibleUserId,
            'status_id' => $this->referenceId('document_statuses', 'status_code', 'RECEIVED', 'status_id'),
            'remarks' => 'Internal test remark that public tracking must never return.',
            'created_by' => $receiverId,
        ]);

        return $id;
    }

    private function sectionId(string $code): string
    {
        return $this->referenceId('sections', 'section_code', $code, 'section_id');
    }

    private function roleId(string $name): string
    {
        return $this->referenceId('roles', 'role_name', $name, 'role_id');
    }

    private function actionId(string $name): string
    {
        return $this->referenceId('routing_actions', 'action_name', $name, 'action_id');
    }

    private function documentVersion(string $documentId): string
    {
        $row = $this->db->table('documents')->select('updated_at')->where('document_id', $documentId)->get()->getRowArray();
        $this->assertNotNull($row, 'Missing document version fixture.');
        return (string) $row['updated_at'];
    }

    private function referenceId(string $table, string $lookupColumn, string $lookupValue, string $idColumn): string
    {
        $row = $this->db->table($table)->select($idColumn)
            ->where($lookupColumn, $lookupValue)->get()->getRowArray();
        $this->assertNotNull($row, "Missing fixture reference {$table}.{$lookupColumn}={$lookupValue}");
        return (string) $row[$idColumn];
    }

    private function uuid(string $prefix = '1'): string
    {
        $sequence = $this->idSequence++;
        return $prefix . '0000000-0000-4000-8000-' . str_pad((string) $sequence, 12, '0', STR_PAD_LEFT);
    }
}
