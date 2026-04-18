<?php

namespace MediaWiki\Extension\SemanticACL\Tests\Integration;

use MediaWiki\Content\WikitextContent;
use MediaWiki\Context\RequestContext;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\SemanticACL\SemanticACL;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use SMW\MediaWiki\Deferred\CallableUpdate as SMWCallableUpdate;
use SMW\MediaWiki\Hooks as SMWHooks;
use SMW\PropertyRegistry;
use SMW\Query\Language\NamespaceDescription;
use SMW\Services\ServicesFactory as SMWServicesFactory;
use SMW\StoreFactory;
use SMWQuery;

/**
 * Subclass of SemanticACL used in integration tests.
 * Bypasses the CLI mode check in hasPermission(), which would otherwise
 * make all permission checks return true when running under PHPUnit
 * (MW_ENTRY_POINT is always 'cli' in that context).
 */
class TestableSemanticACL extends SemanticACL {
	protected static bool $ignoreCliMode = true;
}

/**
 * Integration tests for the SemanticACL extension.
 * Requires SemanticMediaWiki to be installed.
 *
 * Pages are created with real SMW property annotations (e.g. [[Visible to::users]])
 * and SMW indexes them through its normal annotation path, giving tests the same code path
 * as production.
 *
 * Note on SMW indexing in tests: after editPage(), createPage() uses the same two-step
 * flush as SMW's own PageCreator: CallableUpdate::releasePendingUpdates() moves SMW's
 * queued DataUpdater jobs into MediaWiki's DeferredUpdates queue, then doUpdates() runs
 * the full chain (LinksUpdate → LinksUpdateComplete → DataUpdater → SQLStoreUpdater).
 *
 * @group semantic-mediawiki
 * @group Database
 * @group medium
 *
 * @covers \MediaWiki\Extension\SemanticACL\SemanticACL
 */
class SemanticACLIntegrationTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		// Ensure NS_MAIN, NS_TEMPLATE, and NS_FILE are SMW-enabled so hasPermission() checks them.
		// These must be set BEFORE clearing ServicesFactory, so the new singleton picks them up.
		$GLOBALS['smwgNamespacesWithSemanticLinks'] = [
			NS_MAIN => true, NS_TEMPLATE => true, NS_FILE => true,
		];

		// Enable cascading ACL so the ___CASCADE_PERMISSIONS property is
		// registered and cascade tests can function.
		$GLOBALS['wgSemanticACLEnableCascadingACL'] = true;

		// Disable deferred SMW updates so property indexing is synchronous
		// during editPage() calls, making test state predictable.
		$GLOBALS['smwgEnabledDeferredUpdate'] = false;

		// Disable parser cache so every editPage() call re-parses fresh content.
		// A stale ParserOutput retrieved from cache would not have smwdata set,
		// causing LinksUpdateComplete to see empty SemanticData and skip the store write.
		$GLOBALS['wgParserCacheType'] = CACHE_NONE;

		// Clear the ServicesFactory singleton so it re-reads the updated globals above
		// (especially smwgNamespacesWithSemanticLinks) when next accessed. Without this,
		// the stale Settings singleton would report NS_MAIN as not semantic-enabled, causing
		// DataUpdater::processSemantics to be false and skipping the store write entirely.
		SMWServicesFactory::clear();

		// Re-initialise SMW's property registry so SemanticACL's properties
		// (___VISIBLE, ___EDITABLE, etc.) are registered before pages are created.
		PropertyRegistry::clear();
		StoreFactory::clear();

		// Re-register SMW's programmatic hooks into the new HookContainer created by
		// overrideMwServices() inside parent::setUp(). SMW registers LinksUpdateComplete
		// and other handlers at boot time via $hookContainer->register(), which stores
		// them in the container's extraHandlers array. The fresh container built for
		// each test only inherits hooks declared in $wgHooks and extension.json 'Hooks',
		// so SMW's LinksUpdateComplete handler would be absent — meaning no data would
		// be written to the store when createPage() flushes deferred updates.
		( new SMWHooks() )->register();

		// Discard any SMW updates left over from a previous test.  SMW queues
		// DataUpdater jobs in a static CallableUpdate::$pendingUpdates array when
		// smwgEnabledDeferredUpdate is false but the update hasn't been released yet.
		// Leaving stale entries would bleed into the next test's flush.
		SMWCallableUpdate::clearPendingUpdates();
		DeferredUpdates::clearPendingUpdates();

		// Clear the permission cache before each test to prevent results
		// from one test leaking into the next via the static cache.
		TestableSemanticACL::resetPermissionCache();
	}

	// --- Helpers ---

	/**
	 * Create a wiki page and flush SMW deferred updates so annotations are queryable.
	 *
	 * Uses the same two-step flush as SMW's own test PageCreator:
	 *   1. CallableUpdate::releasePendingUpdates() — moves SMW writes queued during the
	 *      page save from the static pending list into MediaWiki's DeferredUpdates queue.
	 *   2. DeferredUpdates::doUpdates() — processes the full deferred chain including
	 *      LinksUpdate → LinksUpdateComplete → DataUpdater → SQLStoreUpdater.
	 */
	private function createPage( Title $title, string $wikitext ): void {
		$status = $this->editPage( $title, $wikitext );
		if ( !$status->isOK() ) {
			$this->fail( 'Could not create test page: ' . $status->getMessage()->text() );
		}
		SMWCallableUpdate::releasePendingUpdates();
		DeferredUpdates::doUpdates();
	}

	private function getAnonUser() {
		return $this->getServiceContainer()->getUserFactory()->newAnonymous();
	}

	// --- "Visible to" tests ---

	/**
	 * A page explicitly set to 'public' must be readable by anonymous users.
	 * Verifies that the 'public' specifier does not accidentally block anyone.
	 */
	public function testPublicPageIsAccessibleToAnonymous(): void {
		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $title, '[[Visible to::public]] This page is public.' );

		$result = true;
		TestableSemanticACL::onGetUserPermissionsErrors( $title, $this->getAnonUser(), 'read', $result );

		$this->assertTrue( $result );
	}

	/**
	 * A page set to 'users' must block anonymous access via the permission hook.
	 * Both the hook return value and the $result reference should be false to
	 * signal MediaWiki to deny the request.
	 */
	public function testUsersOnlyPageDeniedToAnonymous(): void {
		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $title, '[[Visible to::users]] Members only.' );

		$result = true;
		$hookResult = TestableSemanticACL::onGetUserPermissionsErrors(
			$title, $this->getAnonUser(), 'read', $result
		);

		$this->assertFalse( $hookResult );
		$this->assertFalse( $result );
	}

	/**
	 * The other side of 'users' visibility: a logged-in user must be allowed
	 * through. Verifies the hook returns true and leaves $result unchanged.
	 */
	public function testUsersOnlyPageAllowedForRegisteredUser(): void {
		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $title, '[[Visible to::users]] Members only.' );

		$user = $this->getMutableTestUser()->getUser();
		$result = true;
		$hookResult = TestableSemanticACL::onGetUserPermissionsErrors( $title, $user, 'read', $result );

		$this->assertTrue( $hookResult );
		$this->assertTrue( $result );
	}

	// --- Talk page tests ---

	/**
	 * A talk page inherits the permissions of its subject page.  If the
	 * subject page is restricted to registered users, the talk page must
	 * also be denied to anonymous visitors.
	 */
	public function testTalkPageDeniedWhenSubjectPageRestricted(): void {
		$subjectTitle = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $subjectTitle, '[[Visible to::users]] Members only.' );

		$talkTitle = $subjectTitle->getTalkPageIfDefined();
		$this->assertNotNull( $talkTitle, 'Subject page should have a talk page' );

		// The talk page does not need to exist — hasPermission() redirects
		// to the subject page's ACL regardless.
		$result = true;
		TestableSemanticACL::onGetUserPermissionsErrors(
			$talkTitle, $this->getAnonUser(), 'read', $result
		);

		$this->assertFalse( $result, 'Talk page should be denied when subject page is restricted' );
	}

	/**
	 * A registered user who can read the subject page must also be able
	 * to read its talk page.
	 */
	public function testTalkPageAllowedWhenSubjectPageAllowed(): void {
		$subjectTitle = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $subjectTitle, '[[Visible to::users]] Members only.' );

		$talkTitle = $subjectTitle->getTalkPageIfDefined();
		$user = $this->getMutableTestUser()->getUser();

		$result = true;
		TestableSemanticACL::onGetUserPermissionsErrors(
			$talkTitle, $user, 'read', $result
		);

		$this->assertTrue( $result, 'Talk page should be allowed when subject page is allowed' );
	}

	// --- Flow topic tests ---

	/**
	 * Create a Flow workflow row in the database linking a topic UUID to
	 * a board page, and return the topic title in NS_TOPIC.
	 *
	 * @param Title $boardTitle The board page the topic belongs to
	 * @return Title The topic title (in NS_TOPIC)
	 */
	private function createFlowTopic( Title $boardTitle ): Title {
		$uuid = \Flow\Model\UUID::create();
		$wiki = \MediaWiki\WikiMap\WikiMap::getCurrentWikiId();

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'flow_workflow' )
			->row( [
				'workflow_id' => $uuid->getBinary(),
				'workflow_wiki' => $wiki,
				'workflow_namespace' => $boardTitle->getNamespace(),
				'workflow_page_id' => $boardTitle->getArticleID(),
				'workflow_title_text' => $boardTitle->getDBkey(),
				'workflow_name' => 'Topic',
				'workflow_last_update_timestamp' => wfTimestampNow(),
				'workflow_lock_state' => 0,
				'workflow_type' => 'topic',
			] )
			->caller( __METHOD__ )
			->execute();

		return Title::newFromText( $uuid->getAlphadecimal(), NS_TOPIC );
	}

	/**
	 * A Flow topic must inherit read restrictions from its board page.
	 * If the board is restricted to registered users, a topic on that
	 * board must also be denied to anonymous visitors.
	 */
	public function testFlowTopicDeniedWhenBoardRestricted(): void {
		if ( !defined( 'NS_TOPIC' ) ) {
			$this->markTestSkipped( 'Flow extension is not installed' );
		}

		$boardTitle = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $boardTitle, '[[Visible to::users]] Restricted board.' );

		$topicTitle = $this->createFlowTopic( $boardTitle );

		$result = true;
		TestableSemanticACL::onGetUserPermissionsErrors(
			$topicTitle, $this->getAnonUser(), 'read', $result
		);

		$this->assertFalse( $result, 'Flow topic should be denied when board page is restricted' );
	}

	/**
	 * A registered user who can read the board must also be able to
	 * read its topics.
	 */
	public function testFlowTopicAllowedWhenBoardAllowed(): void {
		if ( !defined( 'NS_TOPIC' ) ) {
			$this->markTestSkipped( 'Flow extension is not installed' );
		}

		$boardTitle = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $boardTitle, '[[Visible to::users]] Restricted board.' );

		$topicTitle = $this->createFlowTopic( $boardTitle );

		$user = $this->getMutableTestUser()->getUser();
		$result = true;
		TestableSemanticACL::onGetUserPermissionsErrors(
			$topicTitle, $user, 'read', $result
		);

		$this->assertTrue( $result, 'Flow topic should be allowed when board page is allowed' );
	}

	/**
	 * A Flow topic must inherit edit restrictions from its board page.
	 * If the board is editable only by sysops, editing a topic must be
	 * denied to a regular user.
	 */
	public function testFlowTopicEditDeniedWhenBoardEditRestricted(): void {
		if ( !defined( 'NS_TOPIC' ) ) {
			$this->markTestSkipped( 'Flow extension is not installed' );
		}

		$boardTitle = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $boardTitle,
			'[[Visible to::public]][[Editable by::whitelist]][[Editable by group::sysop]]'
		);

		$topicTitle = $this->createFlowTopic( $boardTitle );

		$user = $this->getMutableTestUser()->getUser(); // not a sysop
		$result = true;
		TestableSemanticACL::onGetUserPermissionsErrors(
			$topicTitle, $user, 'edit', $result
		);

		$this->assertFalse( $result, 'Flow topic should not be editable when board is sysop-only' );
	}

	// --- Whitelist tests ---

	/**
	 * A page set to 'whitelist' with group 'editors' must deny access to a
	 * regular logged-in user who is not a member of that group.
	 * Uses 'editors' rather than 'sysop' to avoid conflating group membership
	 * with the sacl-exempt right that sysops carry.
	 */
	public function testWhitelistGroupDeniedForNonMember(): void {
		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $title, '[[Visible to::whitelist]][[Visible to group::editors]]' );

		$user = $this->getMutableTestUser()->getUser(); // plain user, not in 'editors'
		$result = true;
		$hookResult = TestableSemanticACL::onGetUserPermissionsErrors( $title, $user, 'read', $result );

		$this->assertFalse( $hookResult );
		$this->assertFalse( $result );
	}

	/**
	 * A page set to 'whitelist' with group 'editors' must grant access to a
	 * user who is a member of that group.
	 * Uses 'editors' rather than 'sysop' so the access is granted purely through
	 * group membership in the whitelist, not through the sacl-exempt bypass.
	 */
	public function testWhitelistGroupAllowedForMember(): void {
		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $title, '[[Visible to::whitelist]][[Visible to group::editors]]' );

		$user = $this->getMutableTestUser( [ 'editors' ] )->getUser();
		$result = true;
		$hookResult = TestableSemanticACL::onGetUserPermissionsErrors( $title, $user, 'read', $result );

		$this->assertTrue( $hookResult );
		$this->assertTrue( $result );
	}

	/**
	 * A page set to 'whitelist' with a specific user listed must grant access
	 * to exactly that user. Tests the per-user whitelist path (___VISIBLE_WL_USER).
	 * The annotation must use the "User:" namespace prefix so SemanticACL's
	 * Title::newFromDBkey() comparison resolves to NS_USER and matches getUserPage().
	 */
	public function testWhitelistUserAllowedForNamedUser(): void {
		$user = $this->getMutableTestUser()->getUser();
		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $title,
			'[[Visible to::whitelist]][[Visible to user::User:' . $user->getName() . ']]'
		);

		$result = true;
		$hookResult = TestableSemanticACL::onGetUserPermissionsErrors( $title, $user, 'read', $result );

		$this->assertTrue( $hookResult );
		$this->assertTrue( $result );
	}

	/**
	 * A page set to 'whitelist' must deny access to a user who is logged in
	 * but not on the whitelist. Verifies the whitelist does not grant access
	 * to all authenticated users.
	 */
	public function testWhitelistUserDeniedForOtherUser(): void {
		$whitelistedUser = $this->getMutableTestUser()->getUser();
		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $title,
			'[[Visible to::whitelist]][[Visible to user::User:' . $whitelistedUser->getName() . ']]'
		);

		$otherUser = $this->getMutableTestUser()->getUser();
		$result = true;
		$hookResult = TestableSemanticACL::onGetUserPermissionsErrors( $title, $otherUser, 'read', $result );

		$this->assertFalse( $hookResult );
		$this->assertFalse( $result );
	}

	// --- Edit-by-user whitelist tests ---

	/**
	 * A page editable only by a specific user must deny editing to other users.
	 */
	public function testEditByUserDeniedForOtherUser(): void {
		$allowedUser = $this->getMutableTestUser()->getUser();
		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $title,
			'[[Editable by::whitelist]][[Editable by user::User:' . $allowedUser->getName() . ']]'
		);

		$otherUser = $this->getMutableTestUser()->getUser();
		$result = true;
		TestableSemanticACL::onGetUserPermissionsErrors( $title, $otherUser, 'edit', $result );

		$this->assertFalse( $result, 'Other user should not be able to edit a user-whitelisted page' );
	}

	/**
	 * The whitelisted user must be allowed to edit.
	 */
	public function testEditByUserAllowedForWhitelistedUser(): void {
		$allowedUser = $this->getMutableTestUser()->getUser();
		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $title,
			'[[Editable by::whitelist]][[Editable by user::User:' . $allowedUser->getName() . ']]'
		);

		$result = true;
		TestableSemanticACL::onGetUserPermissionsErrors( $title, $allowedUser, 'edit', $result );

		$this->assertTrue( $result, 'Whitelisted user should be able to edit' );
	}

	// --- Cascading ACL tests ---

	/**
	 * A parent page with cascading permissions defines visibility, editability,
	 * and private link access.  A subpage without its own ACL annotations must
	 * inherit the parent's restrictions.  The subpage transcludes a template
	 * restricted to a different group.
	 *
	 * Scenario:
	 *   Parent page: visible to users + key, editable by 'editors' group,
	 *     cascade enabled.
	 *   Subpage: no own ACL → inherits parent's restrictions, transcludes
	 *     a template restricted to 'reviewers'.
	 *
	 * Checks:
	 *   1. Subpage denied to anonymous (inherits 'users' visibility)
	 *   2. Subpage readable by registered user (inherits 'users' visibility)
	 *   3. Subpage not editable by regular user (inherits 'editors' editability)
	 *   4. Subpage editable by editor
	 *   5. Template on subpage denied to non-reviewer via rendering pipeline
	 */
	public function testCascadingPermissionsOnSubpage(): void {
		$key = 'cascade_secret_key';

		// Template restricted to 'reviewers' via its own ACL.
		$tplTitle = Title::newFromText( 'SemanticACLTest_CascadeTpl', NS_TEMPLATE );
		$this->createPage( $tplTitle,
			'<noinclude>[[Visible to::whitelist]][[Visible to group::reviewers]]</noinclude>'
			. ' Reviewers-only template content.'
		);

		// Parent page with cascading permissions.
		$parentTitle = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $parentTitle,
			'[[Visible to::users]][[Visible to::key]] '
			. '[[Editable by::whitelist]][[Editable by group::editors]] '
			. '[[Cascade permissions to subpages::true]] '
			. '{{#SEMANTICACL_PRIVATE_LINK:' . $key . '}} '
		);

		// Subpage with no own ACL — inherits from parent, transcludes template.
		$subTitle = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ . '/Sub' );
		$this->createPage( $subTitle,
			'Subpage content. {{SemanticACLTest_CascadeTpl}}'
		);

		$anonUser = $this->getAnonUser();
		$regularUser = $this->getMutableTestUser()->getUser();
		$editorUser = $this->getMutableTestUser( [ 'editors' ] )->getUser();

		// 1. Subpage denied to anonymous.
		$result = true;
		TestableSemanticACL::onGetUserPermissionsErrors(
			$subTitle, $anonUser, 'read', $result
		);
		$this->assertFalse( $result, 'Subpage should be denied to anonymous (cascading)' );

		// 2. Subpage readable by registered user.
		TestableSemanticACL::resetPermissionCache();
		$result = true;
		TestableSemanticACL::onGetUserPermissionsErrors(
			$subTitle, $regularUser, 'read', $result
		);
		$this->assertTrue( $result, 'Subpage should be readable by registered user (cascading)' );

		// 3. Subpage not editable by regular user (editors-only).
		TestableSemanticACL::resetPermissionCache();
		$result = true;
		TestableSemanticACL::onGetUserPermissionsErrors(
			$subTitle, $regularUser, 'edit', $result
		);
		$this->assertFalse( $result, 'Subpage should not be editable by non-editor (cascading)' );

		// 4. Subpage editable by editor.
		TestableSemanticACL::resetPermissionCache();
		$result = true;
		TestableSemanticACL::onGetUserPermissionsErrors(
			$subTitle, $editorUser, 'edit', $result
		);
		$this->assertTrue( $result, 'Subpage should be editable by editor (cascading)' );

		// 5. Template on subpage denied to non-reviewer via rendering pipeline.
		$this->enableBaseClassAcl();
		RequestContext::getMain()->setUser( $regularUser );
		RequestContext::getMain()->setRequest( new FauxRequest() );

		try {
			$parser = $this->getServiceContainer()->getParserFactory()->create();
			$parserOptions = \ParserOptions::newFromUser( $regularUser );
			$parserOutput = $parser->parse(
				'{{SemanticACLTest_CascadeTpl}}',
				$subTitle,
				$parserOptions
			);
			$html = $parserOutput->getText();

			$this->assertStringNotContainsString(
				'Reviewers-only template content',
				$html,
				'Template restricted to reviewers must not be visible to a regular user'
			);
			$this->assertStringContainsString(
				'cdx-message--error',
				$html,
				'An error box should replace the reviewers-only template'
			);
		} finally {
			$this->disableBaseClassAcl();
		}
	}

	/**
	 * Private link keys cascade to subpages.  When a parent page defines a
	 * private link key and has cascading permissions enabled, an anonymous
	 * user with the correct key must be able to read the subpage — the
	 * cascading code calls hasPermission() on the parent, which reads the
	 * parent's key and matches it against the request parameter.
	 */
	public function testCascadingPrivateLinkGrantsAccessOnSubpage(): void {
		$key = 'cascade_secret_key';

		$parentTitle = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $parentTitle,
			'[[Visible to::users]][[Visible to::key]] '
			. '[[Cascade permissions to subpages::true]] '
			. '{{#SEMANTICACL_PRIVATE_LINK:' . $key . '}} '
		);

		$subTitle = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ . '/Sub' );
		$this->createPage( $subTitle, 'Subpage content.' );

		$anonUser = $this->getAnonUser();
		$request = new FauxRequest( [ 'semanticacl-key' => $key ] );
		RequestContext::getMain()->setRequest( $request );
		RequestContext::getMain()->setUser( $anonUser );

		TestableSemanticACL::resetPermissionCache();
		$result = true;
		TestableSemanticACL::onGetUserPermissionsErrors(
			$subTitle, $anonUser, 'read', $result
		);
		$this->assertTrue( $result,
			'Subpage should be readable by anonymous with parent\'s private link key (cascading)'
		);
	}

	// --- sacl-exempt right ---

	/**
	 * A user with the 'sacl-exempt' right must bypass all ACL checks regardless
	 * of the page's visibility setting. Sysops have this right per extension.json.
	 * Verifies the exemption mechanism is checked before property lookups.
	 */
	public function testSaclExemptUserBypassesACL(): void {
		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $title, '[[Visible to::users]] Members only.' );

		$user = $this->getMutableTestUser( [ 'sysop' ] )->getUser();
		$result = true;
		$hookResult = TestableSemanticACL::onGetUserPermissionsErrors( $title, $user, 'read', $result );

		$this->assertTrue( $hookResult );
		$this->assertTrue( $result );
	}

	// --- Edit restriction tests ---

	/**
	 * The 'Editable by' property must restrict write access independently of
	 * read access. An anonymous user trying to edit a page with 'Editable by =
	 * users' must be denied, even if the page has no read restriction.
	 */
	public function testEditRestrictedPageDeniedForAnonymous(): void {
		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $title, '[[Editable by::users]] Anyone can read, members can edit.' );

		$result = true;
		$hookResult = TestableSemanticACL::onGetUserPermissionsErrors(
			$title, $this->getAnonUser(), 'edit', $result
		);

		$this->assertFalse( $hookResult );
		$this->assertFalse( $result );
	}

	/**
	 * A page restricted with 'Visible to' but no explicit 'Editable by'
	 * must also deny editing to users who cannot read it.  If the page is
	 * not visible, it should not be editable either.
	 *
	 * TODO: Fix in SemanticACL::hasPermission() — when no ___EDITABLE
	 * properties exist, it should fall back to checking ___VISIBLE so
	 * that a visibility restriction implies an edit restriction.
	 *
	 * @group Broken
	 */
	public function testVisibilityRestrictionImpliesEditRestriction(): void {
		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $title, '[[Visible to::users]] Members only, no explicit edit restriction.' );

		$result = true;
		TestableSemanticACL::onGetUserPermissionsErrors(
			$title, $this->getAnonUser(), 'edit', $result
		);

		$this->assertFalse( $result,
			'Anonymous user should not be able to edit a page they cannot read'
		);
	}

	/**
	 * A page can have different visibility and editability settings. This test
	 * verifies that when a page is visible to all logged-in users but only
	 * editable by the 'editors' group:
	 * - any logged-in user can read it
	 * - a non-editor logged-in user cannot edit it
	 * - a user in the 'editors' group can both read and edit it
	 *
	 * The permission cache is reset between calls because it is keyed by
	 * title + action + user ID, and multiple users are checked against the same
	 * page within one test.
	 */
	public function testPageVisibleToUsersButEditableOnlyByGroup(): void {
		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $title,
			'[[Visible to::users]][[Editable by::whitelist]][[Editable by group::editors]]'
		);

		$regularUser = $this->getMutableTestUser()->getUser();
		$editorUser  = $this->getMutableTestUser( [ 'editors' ] )->getUser();

		// Regular user can read.
		TestableSemanticACL::resetPermissionCache();
		$result = true;
		TestableSemanticACL::onGetUserPermissionsErrors( $title, $regularUser, 'read', $result );
		$this->assertTrue( $result, 'Regular logged-in user should be able to read' );

		// Regular user cannot edit.
		TestableSemanticACL::resetPermissionCache();
		$result = true;
		$hookResult = TestableSemanticACL::onGetUserPermissionsErrors( $title, $regularUser, 'edit', $result );
		$this->assertFalse( $hookResult, 'Regular logged-in user should not be able to edit' );
		$this->assertFalse( $result );

		// Editor can read.
		TestableSemanticACL::resetPermissionCache();
		$result = true;
		TestableSemanticACL::onGetUserPermissionsErrors( $title, $editorUser, 'read', $result );
		$this->assertTrue( $result, 'Editor should be able to read' );

		// Editor can edit.
		TestableSemanticACL::resetPermissionCache();
		$result = true;
		$hookResult = TestableSemanticACL::onGetUserPermissionsErrors( $title, $editorUser, 'edit', $result );
		$this->assertTrue( $hookResult, 'Editor should be able to edit' );
		$this->assertTrue( $result );
	}

	// --- Bad image (inline rendering) tests ---

	/**
	 * A file with 'Visible to = users' must be flagged as a "bad image" for
	 * anonymous users, preventing it from being rendered inline in wiki pages
	 * and galleries.
	 */
	public function testBadImageDeniedToAnonymous(): void {
		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ . '.png', NS_FILE );
		$this->createPage( $title, '[[Visible to::users]] Restricted image.' );

		RequestContext::getMain()->setUser( $this->getAnonUser() );

		$bad = false;
		$hookResult = TestableSemanticACL::onBadImage( $title->getDBkey(), $bad );

		$this->assertTrue( $bad, 'Image should be marked as bad for anonymous user' );
		$this->assertFalse( $hookResult );
	}

	/**
	 * The same restricted file must NOT be flagged as "bad" for a logged-in
	 * user who satisfies the 'users' visibility requirement.
	 */
	public function testBadImageAllowedForRegisteredUser(): void {
		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ . '.png', NS_FILE );
		$this->createPage( $title, '[[Visible to::users]] Restricted image.' );

		$user = $this->getMutableTestUser()->getUser();
		RequestContext::getMain()->setUser( $user );

		$bad = false;
		$hookResult = TestableSemanticACL::onBadImage( $title->getDBkey(), $bad );

		$this->assertFalse( $bad, 'Image should not be marked as bad for registered user' );
		$this->assertTrue( $hookResult );
	}

	/**
	 * A file page with ACL restrictions must also be denied via the
	 * permission hook (onGetUserPermissionsErrors), not just via onBadImage.
	 * This protects against direct access to the file description page.
	 */
	public function testFilePageDeniedToAnonymous(): void {
		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ . '.png', NS_FILE );
		$this->createPage( $title, '[[Visible to::users]] Members only.' );

		$result = true;
		$hookResult = TestableSemanticACL::onGetUserPermissionsErrors(
			$title, $this->getAnonUser(), 'read', $result
		);

		$this->assertFalse( $hookResult );
		$this->assertFalse( $result );
	}

	// --- File category requirement tests ---

	/**
	 * When $wgSemanticACLPublicImagesCategory is configured, a file NOT in
	 * that category must be flagged as "bad" — even for logged-in users —
	 * unless the user has the 'view-non-categorized-media' right.
	 * This is a separate access-control mechanism from SMW properties:
	 * the file is blocked before any [[Visible to]] check runs.
	 */
	public function testUncategorizedImageDeniedWhenCategoryRequired(): void {
		$GLOBALS['wgSemanticACLPublicImagesCategory'] = 'Public images';

		// Revoke the right that bypasses the category check; it may be granted
		// to default groups in LocalSettings.php.
		$this->setGroupPermissions( [
			'*' => [ 'view-non-categorized-media' => false ],
			'user' => [ 'view-non-categorized-media' => false ],
			'autoconfirmed' => [ 'view-non-categorized-media' => false ],
		] );

		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ . '.png', NS_FILE );
		$this->createPage( $title, 'An uncategorized image.' );

		$user = $this->getMutableTestUser()->getUser();
		RequestContext::getMain()->setUser( $user );

		$bad = false;
		$hookResult = TestableSemanticACL::onBadImage( $title->getDBkey(), $bad );

		$this->assertTrue( $bad, 'Uncategorized image should be marked as bad' );
		$this->assertFalse( $hookResult );
	}

	/**
	 * A file that IS in the required public images category must pass the
	 * onBadImage check (assuming no other ACL restriction applies).
	 */
	public function testCategorizedImageAllowedWhenCategoryRequired(): void {
		$GLOBALS['wgSemanticACLPublicImagesCategory'] = 'Public images';

		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ . '.png', NS_FILE );
		$this->createPage( $title, '[[Category:Public images]] A properly categorized image.' );

		$user = $this->getMutableTestUser()->getUser();
		RequestContext::getMain()->setUser( $user );

		$bad = false;
		$hookResult = TestableSemanticACL::onBadImage( $title->getDBkey(), $bad );

		$this->assertFalse( $bad, 'Categorized image should not be marked as bad' );
		$this->assertTrue( $hookResult );
	}

	// --- Template render denial tests ---

	/**
	 * When an anonymous user tries to transclude a template they cannot access,
	 * the hook must replace the template's revision record with a WikitextContent
	 * containing an mw-message-box error that includes a Special:UserLogin link.
	 * This verifies the fix for T304760 (markup moved from i18n to PHP).
	 */
	public function testTemplateDeniedToAnonymousShowsLoginError(): void {
		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__, NS_TEMPLATE );
		$this->createPage( $title, '[[Visible to::users]] Secret template content.' );

		RequestContext::getMain()->setUser( $this->getAnonUser() );

		$contextTitle = null;
		$skip = false;
		$revRecord = null;

		TestableSemanticACL::onBeforeParserFetchTemplateRevisionRecord(
			$contextTitle, $title, $skip, $revRecord
		);

		$this->assertNotNull( $revRecord );
		$content = $revRecord->getContent( SlotRecord::MAIN );
		$this->assertInstanceOf( WikitextContent::class, $content );
		$wikitext = $content->getText();
		$this->assertStringContainsString( 'cdx-message--error', $wikitext );
		$this->assertStringContainsString( 'Special:UserLogin', $wikitext );
		$this->assertStringNotContainsString( 'Secret template content', $wikitext );
	}

	/**
	 * When a logged-in user (who still lacks access) tries to transclude a
	 * restricted template, the hook must return the "insufficient permissions"
	 * error box — which does not include a login link since the user is already
	 * authenticated.
	 */
	public function testTemplateDeniedToRegisteredShowsPermissionError(): void {
		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__, NS_TEMPLATE );
		$this->createPage( $title, '[[Visible to::whitelist]][[Visible to group::editors]] Editors only.' );

		$user = $this->getMutableTestUser()->getUser(); // logged in, but not an editor
		RequestContext::getMain()->setUser( $user );

		$contextTitle = null;
		$skip = false;
		$revRecord = null;

		TestableSemanticACL::onBeforeParserFetchTemplateRevisionRecord(
			$contextTitle, $title, $skip, $revRecord
		);

		$this->assertNotNull( $revRecord );
		$content = $revRecord->getContent( SlotRecord::MAIN );
		$this->assertInstanceOf( WikitextContent::class, $content );
		$wikitext = $content->getText();
		$this->assertStringContainsString( 'cdx-message--error', $wikitext );
		$this->assertStringNotContainsString( 'Special:UserLogin', $wikitext );
		$this->assertStringNotContainsString( 'Editors only', $wikitext );
	}

	// --- End-to-end rendering tests ---

	/**
	 * A public page that transcludes a restricted template and embeds a
	 * restricted image.  When rendered for an anonymous user the page itself
	 * must be accessible, but:
	 *   - the template content must be replaced by an error box (not leaked)
	 *   - the image must be suppressed via the bad-image mechanism
	 *
	 * This exercises the full parser pipeline rather than calling hooks directly.
	 */
	public function testPublicPageWithRestrictedInclusionAndImage(): void {
		// 1. Create a users-only template.  The ACL annotation is inside
		//    <noinclude> so it applies to the template page itself without
		//    propagating to pages that transclude it (standard SMW behaviour).
		$tplTitle = Title::newFromText( 'SemanticACLTest_RestrictedTpl', NS_TEMPLATE );
		$this->createPage( $tplTitle,
			'<noinclude>[[Visible to::users]]</noinclude> Top-secret template content.'
		);

		// 2. Create a sysop-only template.
		$sysopTplTitle = Title::newFromText( 'SemanticACLTest_SysopTpl', NS_TEMPLATE );
		$this->createPage( $sysopTplTitle,
			'<noinclude>[[Visible to::whitelist]][[Visible to group::sysop]]</noinclude>'
			. ' Sysop-only template content.'
		);

		// 3. Create a restricted file page.
		$fileTitle = Title::newFromText( 'SemanticACLTest_RestrictedImg.png', NS_FILE );
		$this->createPage( $fileTitle, '[[Visible to::users]] This image is restricted.' );

		// 4. Create a public page that transcludes templates, embeds the image
		//    inline, and also includes the image inside a <gallery>.
		$pageTitle = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $pageTitle,
			"[[Visible to::public]] "
			. "Here is the template: {{SemanticACLTest_RestrictedTpl}} "
			. "Sysop template: {{SemanticACLTest_SysopTpl}} "
			. "Inline image: [[File:SemanticACLTest_RestrictedImg.png]] "
			. "<gallery>\nSemanticACLTest_RestrictedImg.png|A restricted image\n</gallery>"
		);

		// 5. Set context to anonymous user.
		$anonUser = $this->getAnonUser();
		RequestContext::getMain()->setUser( $anonUser );

		// The page itself must be accessible.
		$result = true;
		TestableSemanticACL::onGetUserPermissionsErrors(
			$pageTitle, $anonUser, 'read', $result
		);
		$this->assertTrue( $result, 'Public page itself should be accessible to anonymous' );

		// The hooks in extension.json point to the base SemanticACL class, so
		// we must disable the CLI mode bypass for the full parser pipeline.
		$this->enableBaseClassAcl();

		try {
			// 6. Parse the public page as anonymous — exercises the full pipeline
			//    including BeforeParserFetchTemplateRevisionRecord.
			$parser = $this->getServiceContainer()->getParserFactory()->create();
			$parserOptions = \ParserOptions::newFromAnon();
			$parserOutput = $parser->parse(
				"{{SemanticACLTest_RestrictedTpl}} "
				. "{{SemanticACLTest_SysopTpl}} "
				. "[[File:SemanticACLTest_RestrictedImg.png]] "
				. "<gallery>\nSemanticACLTest_RestrictedImg.png|A restricted image\n</gallery>",
				$pageTitle,
				$parserOptions
			);
			$html = $parserOutput->getText();

			// Users-only template content must NOT leak.
			$this->assertStringNotContainsString(
				'Top-secret template content',
				$html,
				'Users-only template content must not appear for anonymous'
			);
			// Sysop-only template content must NOT leak.
			$this->assertStringNotContainsString(
				'Sysop-only template content',
				$html,
				'Sysop-only template content must not appear for anonymous'
			);
			// Error boxes should replace both templates.
			$this->assertStringContainsString(
				'cdx-message--error',
				$html,
				'Error boxes should replace restricted templates for anonymous'
			);

			// 7. The image must be marked as bad (covers inline and gallery use).
			$bad = false;
			SemanticACL::onBadImage( $fileTitle->getDBkey(), $bad );
			$this->assertTrue( $bad, 'Restricted image should be marked as bad for anonymous' );
		} finally {
			$this->disableBaseClassAcl();
		}
	}

	/**
	 * Same scenario as above but for a registered user (non-sysop): the
	 * users-only template and image must be accessible, but the sysop-only
	 * template must still be denied.
	 */
	public function testPublicPageWithRestrictedInclusionAllowedForRegistered(): void {
		$tplTitle = Title::newFromText( 'SemanticACLTest_AllowedTpl', NS_TEMPLATE );
		$this->createPage( $tplTitle,
			'<noinclude>[[Visible to::users]]</noinclude> Visible template content.'
		);

		$sysopTplTitle = Title::newFromText( 'SemanticACLTest_AllowedSysopTpl', NS_TEMPLATE );
		$this->createPage( $sysopTplTitle,
			'<noinclude>[[Visible to::whitelist]][[Visible to group::sysop]]</noinclude>'
			. ' Sysop-only template content.'
		);

		$fileTitle = Title::newFromText( 'SemanticACLTest_AllowedImg.png', NS_FILE );
		$this->createPage( $fileTitle, '[[Visible to::users]] This image is for users.' );

		$pageTitle = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $pageTitle,
			"[[Visible to::public]] "
			. "{{SemanticACLTest_AllowedTpl}} "
			. "{{SemanticACLTest_AllowedSysopTpl}} "
			. "[[File:SemanticACLTest_AllowedImg.png]] "
			. "<gallery>\nSemanticACLTest_AllowedImg.png|An image\n</gallery>"
		);

		$user = $this->getTestUser()->getUser();
		RequestContext::getMain()->setUser( $user );

		$this->enableBaseClassAcl();

		try {
			// Parse the page as a registered (non-sysop) user.
			$parser = $this->getServiceContainer()->getParserFactory()->create();
			$parserOptions = \ParserOptions::newFromUser( $user );
			$parserOutput = $parser->parse(
				"{{SemanticACLTest_AllowedTpl}} "
				. "{{SemanticACLTest_AllowedSysopTpl}} "
				. "[[File:SemanticACLTest_AllowedImg.png]] "
				. "<gallery>\nSemanticACLTest_AllowedImg.png|An image\n</gallery>",
				$pageTitle,
				$parserOptions
			);
			$html = $parserOutput->getText();

			// Users-only template content should be present.
			$this->assertStringContainsString(
				'Visible template content',
				$html,
				'Registered user should see the users-only template content'
			);
			// Sysop-only template content must NOT leak to a non-sysop.
			$this->assertStringNotContainsString(
				'Sysop-only template content',
				$html,
				'Non-sysop user should not see sysop-only template content'
			);

			// Image should not be marked as bad for a registered user.
			$bad = false;
			SemanticACL::onBadImage( $fileTitle->getDBkey(), $bad );
			$this->assertFalse( $bad, 'Image should not be marked as bad for registered user' );
		} finally {
			$this->disableBaseClassAcl();
		}
	}

	// --- Private link tests ---

	/**
	 * A private link key must only grant read access, never edit access.
	 * Even with the correct key in the URL, an anonymous user must be
	 * denied editing — the 'key' ACL type explicitly checks that the
	 * action is 'read' or 'raw' before granting permission.
	 */
	public function testPrivateLinkNeverGrantsEditAccessToAnonymous(): void {
		$key = 'my_secret_key_123';
		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $title,
			'[[Visible to::users]][[Visible to::key]] '
			. '[[Editable by::whitelist]][[Editable by group::sysop]] '
			. '{{#SEMANTICACL_PRIVATE_LINK:' . $key . '}}'
		);

		$anonUser = $this->getAnonUser();
		$request = new FauxRequest( [ 'semanticacl-key' => $key ] );
		RequestContext::getMain()->setRequest( $request );
		RequestContext::getMain()->setUser( $anonUser );

		// Read should be granted via private link.
		$readResult = true;
		TestableSemanticACL::onGetUserPermissionsErrors(
			$title, $anonUser, 'read', $readResult
		);
		$this->assertTrue( $readResult, 'Private link should grant read access to anonymous' );

		// Edit must be denied.
		TestableSemanticACL::resetPermissionCache();
		$editResult = true;
		TestableSemanticACL::onGetUserPermissionsErrors(
			$title, $anonUser, 'edit', $editResult
		);
		$this->assertFalse( $editResult, 'Private link must never grant edit access to anonymous' );
	}

	/**
	 * A logged-in user who accesses a page via private link can read it,
	 * but must not be able to edit unless they are in the group that has
	 * explicit edit permission.
	 */
	public function testPrivateLinkNeverGrantsEditAccessToRegisteredUser(): void {
		$key = 'my_secret_key_123';
		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $title,
			'[[Visible to::users]][[Visible to::key]] '
			. '[[Editable by::whitelist]][[Editable by group::sysop]] '
			. '{{#SEMANTICACL_PRIVATE_LINK:' . $key . '}}'
		);

		$user = $this->getMutableTestUser()->getUser(); // not a sysop
		$request = new FauxRequest( [ 'semanticacl-key' => $key ] );
		RequestContext::getMain()->setRequest( $request );
		RequestContext::getMain()->setUser( $user );

		// Read should be granted (user is registered + has key).
		$readResult = true;
		TestableSemanticACL::onGetUserPermissionsErrors(
			$title, $user, 'read', $readResult
		);
		$this->assertTrue( $readResult, 'Registered user should be able to read' );

		// Edit must be denied (not a sysop).
		TestableSemanticACL::resetPermissionCache();
		$editResult = true;
		TestableSemanticACL::onGetUserPermissionsErrors(
			$title, $user, 'edit', $editResult
		);
		$this->assertFalse( $editResult,
			'Registered user without sysop rights must not be able to edit despite private link'
		);
	}



	/**
	 * A page restricted to registered users + private link transcludes a
	 * users-only template.  An anonymous visitor with the correct key can
	 * read the page, but the template's own ACL (users-only) must still
	 * block the transclusion — the private link key only grants access to
	 * the page itself, not to its included resources.
	 *
	 */
	public function testPrivateLinkDoesNotGrantAccessToRestrictedInclusion(): void {
		$key = 'my_secret_key_123';

		// Users-only template.
		$tplTitle = Title::newFromText( 'SemanticACLTest_PrivateLinkTpl', NS_TEMPLATE );
		$this->createPage( $tplTitle,
			'<noinclude>[[Visible to::users]]</noinclude> Secret inclusion content.'
		);

		// Page restricted to users + key, editable only by sysops,
		// transcludes the template.
		$pageTitle = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $pageTitle,
			'[[Visible to::users]][[Visible to::key]] '
			. '[[Editable by::whitelist]][[Editable by group::sysop]] '
			. '{{#SEMANTICACL_PRIVATE_LINK:' . $key . '}} '
			. '{{SemanticACLTest_PrivateLinkTpl}}'
		);

		// Anonymous visitor with correct key.
		$anonUser = $this->getAnonUser();
		$request = new FauxRequest( [ 'semanticacl-key' => $key ] );
		RequestContext::getMain()->setRequest( $request );
		RequestContext::getMain()->setUser( $anonUser );

		// The page itself must be readable via private link.
		$result = true;
		TestableSemanticACL::onGetUserPermissionsErrors(
			$pageTitle, $anonUser, 'read', $result
		);
		$this->assertTrue( $result, 'Page should be readable with correct private link key' );

		// But editing must still be denied (sysop-only).
		$editResult = true;
		TestableSemanticACL::onGetUserPermissionsErrors(
			$pageTitle, $anonUser, 'edit', $editResult
		);
		$this->assertFalse( $editResult, 'Page should not be editable by anonymous even with private link key' );

		$this->enableBaseClassAcl();

		try {
			// Parse the page — the template must still be denied.
			$parser = $this->getServiceContainer()->getParserFactory()->create();
			$parserOptions = \ParserOptions::newFromAnon();
			$parserOutput = $parser->parse(
				'{{SemanticACLTest_PrivateLinkTpl}}',
				$pageTitle,
				$parserOptions
			);
			$html = $parserOutput->getText();

			$this->assertStringNotContainsString(
				'Secret inclusion content',
				$html,
				'Private link key must not grant access to a users-only template inclusion'
			);
			$this->assertStringContainsString(
				'cdx-message--error',
				$html,
				'An error box should replace the template for anonymous even with private link'
			);
		} finally {
			$this->disableBaseClassAcl();
		}
	}

	/**
	 * A page restricted to 'users' but also carrying a 'key' ACL type must
	 * grant access to an anonymous visitor who supplies the correct private
	 * link key in the URL.  The key is embedded in the page via the
	 * {{#SEMANTICACL_PRIVATE_LINK:…}} parser function and checked against the
	 * 'semanticacl-key' query parameter.
	 */
	public function testPrivateLinkGrantsAccessWithCorrectKey(): void {
		$key = 'my_secret_key_123';
		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $title,
			'[[Visible to::users]][[Visible to::key]] {{#SEMANTICACL_PRIVATE_LINK:' . $key . '}}'
		);

		// Simulate a request carrying the correct private link key.
		$request = new FauxRequest( [ 'semanticacl-key' => $key ] );
		RequestContext::getMain()->setRequest( $request );
		RequestContext::getMain()->setUser( $this->getAnonUser() );

		$result = true;
		TestableSemanticACL::onGetUserPermissionsErrors(
			$title, $this->getAnonUser(), 'read', $result
		);

		$this->assertTrue( $result, 'Anonymous user with correct key should be allowed' );
	}

	/**
	 * The same page must deny access when the URL carries a wrong key.
	 */
	public function testPrivateLinkDeniedWithWrongKey(): void {
		$key = 'my_secret_key_123';
		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $title,
			'[[Visible to::users]][[Visible to::key]] {{#SEMANTICACL_PRIVATE_LINK:' . $key . '}}'
		);

		$request = new FauxRequest( [ 'semanticacl-key' => 'wrong_key_value' ] );
		RequestContext::getMain()->setRequest( $request );
		RequestContext::getMain()->setUser( $this->getAnonUser() );

		$result = true;
		$hookResult = TestableSemanticACL::onGetUserPermissionsErrors(
			$title, $this->getAnonUser(), 'read', $result
		);

		$this->assertFalse( $result, 'Anonymous user with wrong key should be denied' );
	}

	/**
	 * Without any key in the URL, the anonymous user must be denied by the
	 * 'users' restriction — the 'key' ACL type only grants, never denies.
	 */
	public function testPrivateLinkDeniedWithoutKey(): void {
		$key = 'my_secret_key_123';
		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $title,
			'[[Visible to::users]][[Visible to::key]] {{#SEMANTICACL_PRIVATE_LINK:' . $key . '}}'
		);

		RequestContext::getMain()->setRequest( new FauxRequest() );
		RequestContext::getMain()->setUser( $this->getAnonUser() );

		$result = true;
		$hookResult = TestableSemanticACL::onGetUserPermissionsErrors(
			$title, $this->getAnonUser(), 'read', $result
		);

		$this->assertFalse( $result, 'Anonymous user without key should be denied' );
	}

	// --- SMW query result filtering tests ---

	/**
	 * Enable ACL enforcement on the base SemanticACL class (not just
	 * TestableSemanticACL) so that hooks registered in extension.json
	 * — which point to SemanticACL, not the test subclass — also enforce
	 * permissions in CLI mode.
	 */
	private function enableBaseClassAcl(): void {
		$ref = new \ReflectionProperty( SemanticACL::class, 'ignoreCliMode' );
		$ref->setAccessible( true );
		$ref->setValue( null, true );
	}

	/**
	 * Restore the base class CLI mode bypass.
	 */
	private function disableBaseClassAcl(): void {
		$ref = new \ReflectionProperty( SemanticACL::class, 'ignoreCliMode' );
		$ref->setAccessible( true );
		$ref->setValue( null, false );
	}

	/**
	 * A restricted page must be stripped from SMW #ask query results when
	 * the querying user is anonymous.  The AfterQueryResultLookupComplete
	 * hook iterates query results and removes entries the current user
	 * cannot read.
	 */
	public function testSmwQueryFiltersRestrictedPageForAnonymous(): void {
		$publicTitle = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ . '_Public' );
		$restrictedTitle = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ . '_Restricted' );

		$this->createPage( $publicTitle, '[[Visible to::public]] Public content.' );
		$this->createPage( $restrictedTitle, '[[Visible to::users]] Members only.' );

		// Set context to anonymous user.
		RequestContext::getMain()->setUser( $this->getAnonUser() );

		// The hook in extension.json targets SemanticACL (not TestableSemanticACL),
		// so we must disable the CLI mode bypass on the base class.
		$this->enableBaseClassAcl();

		try {
			// Build a query equivalent to {{#ask: [[:+]] }} over NS_MAIN.
			$description = new NamespaceDescription( NS_MAIN );
			$query = new SMWQuery( $description );
			$query->setLimit( 50 );

			$store = StoreFactory::getStore();
			$queryResult = $store->getQueryResult( $query );

			$resultTitles = [];
			foreach ( $queryResult->getResults() as $result ) {
				$resultTitles[] = $result->getTitle()->getPrefixedDBkey();
			}

			$this->assertContains(
				$publicTitle->getPrefixedDBkey(),
				$resultTitles,
				'Public page should appear in query results'
			);
			$this->assertNotContains(
				$restrictedTitle->getPrefixedDBkey(),
				$resultTitles,
				'Restricted page should be filtered from query results for anonymous user'
			);
		} finally {
			$this->disableBaseClassAcl();
		}
	}

	/**
	 * A registered user should see restricted-to-users pages in SMW query results.
	 */
	public function testSmwQueryShowsRestrictedPageForRegisteredUser(): void {
		$restrictedTitle = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $restrictedTitle, '[[Visible to::users]] Members only.' );

		$user = $this->getTestUser()->getUser();
		RequestContext::getMain()->setUser( $user );

		$this->enableBaseClassAcl();

		try {
			$description = new NamespaceDescription( NS_MAIN );
			$query = new SMWQuery( $description );
			$query->setLimit( 50 );

			$store = StoreFactory::getStore();
			$queryResult = $store->getQueryResult( $query );

			$resultTitles = [];
			foreach ( $queryResult->getResults() as $result ) {
				$resultTitles[] = $result->getTitle()->getPrefixedDBkey();
			}

			$this->assertContains(
				$restrictedTitle->getPrefixedDBkey(),
				$resultTitles,
				'Restricted page should appear in query results for registered user'
			);
		} finally {
			$this->disableBaseClassAcl();
		}
	}

	// --- Search result permission tests ---

	/**
	 * MediaWiki's search result widget calls definitelyCan('read') on each
	 * result title, which goes through the permission system and fires
	 * getUserPermissionsErrors.  A restricted page must be denied for an
	 * anonymous user through this path — the same path the search UI uses.
	 */
	public function testSearchResultDeniedForAnonymousUser(): void {
		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $title, '[[Visible to::users]] Members only.' );

		$anonUser = $this->getAnonUser();
		RequestContext::getMain()->setUser( $anonUser );

		$this->enableBaseClassAcl();

		try {
			$this->assertFalse(
				$anonUser->definitelyCan( 'read', $title ),
				'Anonymous user should not be able to read a users-only page via definitelyCan()'
			);
		} finally {
			$this->disableBaseClassAcl();
		}
	}

	/**
	 * A registered user must be able to read a users-only page through the
	 * same definitelyCan() path used by the search result widget.
	 */
	public function testSearchResultAllowedForRegisteredUser(): void {
		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $title, '[[Visible to::users]] Members only.' );

		$user = $this->getTestUser()->getUser();
		RequestContext::getMain()->setUser( $user );

		$this->enableBaseClassAcl();

		try {
			$this->assertTrue(
				$user->definitelyCan( 'read', $title ),
				'Registered user should be able to read a users-only page via definitelyCan()'
			);
		} finally {
			$this->disableBaseClassAcl();
		}
	}

	// --- IP whitelist tests ---

	/**
	 * A user whose IP is in $wgSemanticACLWhitelistIPs must bypass all ACL
	 * checks, regardless of the page's visibility settings.
	 */
	public function testIpWhitelistBypassesAcl(): void {
		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $title, '[[Visible to::whitelist]][[Visible to group::editors]] Editors only.' );

		$anonUser = $this->getAnonUser();

		// Set up a FauxRequest with a specific IP via reflection on the
		// protected WebRequest::$ip property.
		$request = new FauxRequest();
		$ipRef = new \ReflectionProperty( \MediaWiki\Request\WebRequest::class, 'ip' );
		$ipRef->setAccessible( true );
		$ipRef->setValue( $request, '192.168.1.100' );
		RequestContext::getMain()->setRequest( $request );
		RequestContext::getMain()->setUser( $anonUser );

		$GLOBALS['wgSemanticACLWhitelistIPs'] = [ '192.168.1.100' ];

		$result = true;
		TestableSemanticACL::onGetUserPermissionsErrors( $title, $anonUser, 'read', $result );

		$this->assertTrue( $result, 'Whitelisted IP should bypass ACL restrictions' );
	}

	// --- Cascading ACL override test ---

	/**
	 * A subpage with its own ACL annotations must use its own rules and
	 * ignore the parent's cascading permissions.  The cascading code at
	 * hasPermission() only applies when $aclTypes is empty for the current
	 * action's prefix.
	 */
	public function testCascadingOverrideBySubpageOwnAcl(): void {
		// Parent page with cascade + restrictive visibility (editors-only).
		$parentTitle = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $parentTitle,
			'[[Visible to::whitelist]][[Visible to group::editors]] '
			. '[[Cascade permissions to subpages::true]]'
		);

		// Subpage with its own less restrictive ACL — visible to all users.
		$subTitle = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ . '/Sub' );
		$this->createPage( $subTitle, '[[Visible to::users]] Subpage with own ACL.' );

		// A regular user (not in 'editors') — parent would deny, but subpage allows.
		$user = $this->getMutableTestUser()->getUser();
		$result = true;
		TestableSemanticACL::onGetUserPermissionsErrors(
			$subTitle, $user, 'read', $result
		);
		$this->assertTrue( $result,
			'Subpage with own ACL should override parent cascading (visible to users, not editors-only)'
		);

		// Anonymous should still be denied by the subpage's own 'users' restriction.
		TestableSemanticACL::resetPermissionCache();
		$result = true;
		TestableSemanticACL::onGetUserPermissionsErrors(
			$subTitle, $this->getAnonUser(), 'read', $result
		);
		$this->assertFalse( $result,
			'Subpage own ACL should still deny anonymous (visible to users, not public)'
		);
	}

	// --- Raw action test ---

	/**
	 * The 'raw' action (API raw content access, e.g. action=raw) must follow
	 * the same visibility rules as 'read'.  A page restricted to users must
	 * deny raw access to anonymous users.
	 */
	public function testRawActionDeniedForAnonymous(): void {
		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $title, '[[Visible to::users]] Members only.' );

		$result = true;
		$hookResult = TestableSemanticACL::onGetUserPermissionsErrors(
			$title, $this->getAnonUser(), 'raw', $result
		);

		$this->assertFalse( $hookResult );
		$this->assertFalse( $result, 'Raw action should be denied for anonymous on users-only page' );
	}

	// --- Multiple whitelisted groups test ---

	/**
	 * A page restricted to multiple groups via whitelist must grant access
	 * to a member of ANY of those groups (OR logic, not AND).
	 */
	public function testMultipleWhitelistedGroupsGrantAccess(): void {
		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $title,
			'[[Visible to::whitelist]][[Visible to group::group_a]][[Visible to group::group_b]]'
		);

		// User in group_b but NOT group_a — should still have access.
		$userB = $this->getMutableTestUser( [ 'group_b' ] )->getUser();
		$result = true;
		TestableSemanticACL::onGetUserPermissionsErrors( $title, $userB, 'read', $result );
		$this->assertTrue( $result, 'User in group_b should have access (OR logic across groups)' );

		// User in group_a but NOT group_b — should also have access.
		TestableSemanticACL::resetPermissionCache();
		$userA = $this->getMutableTestUser( [ 'group_a' ] )->getUser();
		$result = true;
		TestableSemanticACL::onGetUserPermissionsErrors( $title, $userA, 'read', $result );
		$this->assertTrue( $result, 'User in group_a should have access (OR logic across groups)' );

		// User in neither group — should be denied.
		TestableSemanticACL::resetPermissionCache();
		$noGroupUser = $this->getMutableTestUser()->getUser();
		$result = true;
		TestableSemanticACL::onGetUserPermissionsErrors( $title, $noGroupUser, 'read', $result );
		$this->assertFalse( $result, 'User in neither group should be denied' );
	}

	// --- Non-semantic namespace test ---

	/**
	 * A page in a namespace that is NOT in $smwgNamespacesWithSemanticLinks
	 * must always be accessible — hasPermission() returns true early without
	 * checking any ACL properties.
	 */
	public function testNonSemanticNamespaceAlwaysAccessible(): void {
		// NS_PROJECT (4) is not in our smwgNamespacesWithSemanticLinks
		// (which only includes NS_MAIN, NS_TEMPLATE, and NS_FILE in setUp).
		$title = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__, NS_PROJECT );
		$this->createPage( $title, 'Content in non-semantic namespace.' );

		$result = true;
		TestableSemanticACL::onGetUserPermissionsErrors(
			$title, $this->getAnonUser(), 'read', $result
		);
		$this->assertTrue( $result,
			'Pages in non-semantic namespaces should always be accessible'
		);
	}

	// --- Incomplete permissions cascading test ---

	/**
	 * A subpage that defines only edit restrictions but no visibility
	 * restrictions should fall back to the parent's cascading visibility
	 * rules for read access.  The cascading check in hasPermission() is
	 * per-prefix: $aclTypes is empty for ___VISIBLE on the subpage, so
	 * cascading kicks in for reads, while the subpage's own ___EDITABLE
	 * values are used for edit checks.
	 *
	 * Scenario:
	 *   Parent: visible to users, editable by editors, cascade enabled
	 *   Subpage: editable by reviewers only (no visibility restriction)
	 *
	 * Expected:
	 *   - Read: inherits parent's 'users' visibility via cascading → anonymous denied
	 *   - Edit: uses own 'reviewers' restriction → non-reviewer denied
	 */
	public function testIncompletePermissionsFallBackToCascading(): void {
		// Parent page with cascading permissions — both visibility and edit restrictions.
		$parentTitle = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ );
		$this->createPage( $parentTitle,
			'[[Visible to::users]] '
			. '[[Editable by::whitelist]][[Editable by group::editors]] '
			. '[[Cascade permissions to subpages::true]]'
		);

		// Subpage with only edit restrictions — no visibility annotation.
		$subTitle = Title::newFromText( 'SemanticACLTest_' . __FUNCTION__ . '/Sub' );
		$this->createPage( $subTitle,
			'[[Editable by::whitelist]][[Editable by group::reviewers]] Subpage with only edit ACL.'
		);

		$anonUser = $this->getAnonUser();
		$regularUser = $this->getMutableTestUser()->getUser();
		$reviewerUser = $this->getMutableTestUser( [ 'reviewers' ] )->getUser();

		// Read: subpage has no ___VISIBLE → should cascade from parent → deny anonymous.
		$result = true;
		TestableSemanticACL::onGetUserPermissionsErrors(
			$subTitle, $anonUser, 'read', $result
		);
		$this->assertFalse( $result,
			'Subpage with only edit ACL should inherit parent visibility (deny anonymous)'
		);

		// Read: registered user should be allowed via cascaded 'users' visibility.
		TestableSemanticACL::resetPermissionCache();
		$result = true;
		TestableSemanticACL::onGetUserPermissionsErrors(
			$subTitle, $regularUser, 'read', $result
		);
		$this->assertTrue( $result,
			'Subpage with only edit ACL should inherit parent visibility (allow registered)'
		);

		// Edit: subpage's own edit restriction (reviewers) should apply, not parent's (editors).
		TestableSemanticACL::resetPermissionCache();
		$result = true;
		TestableSemanticACL::onGetUserPermissionsErrors(
			$subTitle, $regularUser, 'edit', $result
		);
		$this->assertFalse( $result,
			'Regular user should not be able to edit (subpage requires reviewers group)'
		);

		// Edit: reviewer should be able to edit.
		TestableSemanticACL::resetPermissionCache();
		$result = true;
		TestableSemanticACL::onGetUserPermissionsErrors(
			$subTitle, $reviewerUser, 'edit', $result
		);
		$this->assertTrue( $result,
			'Reviewer should be able to edit (subpage editable by reviewers)'
		);
	}
}
