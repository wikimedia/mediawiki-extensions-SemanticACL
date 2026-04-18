<?php
/**
 * SemanticACL extension - Allows per-page read and edit restrictions to be set with properties.
 *
 * @link https://www.mediawiki.org/wiki/Extension:SemanticACL Documentation
 *
 * @file SemanticACL.class.php
 * @ingroup Extensions
 * @defgroup SemanticACL
 * @package MediaWiki
 * @author Werdna (Andrew Garrett)
 * @author Tinss (Antoine Mercier-Linteau)
 * @copyright (C) 2011 Werdna
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\SemanticACL;

use Article;
use LogicException;
use MediaWiki\Content\TextContent;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Context\RequestContext;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Parser;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use SMW\DIProperty;
use SMW\DIWikiPage;
use SMW\Query\QueryResult;
use SMW\Store;
use SMW\StoreFactory;

/**
 * SemanticACL extension main class.
 */
class SemanticACL {
	/** The minimum key length for private link access. */
	public const MIN_KEY_LENGTH = 6;

	/** The name of URL argument for private link access. */
	public const URL_ARG_NAME = 'semanticacl-key';

	/**
	 * When true, the CLI mode bypass in hasPermission() is skipped.
	 * Intended for use in integration tests, where MW_ENTRY_POINT is always 'cli'.
	 */
	protected static bool $ignoreCliMode = false;

	/**
	 * When true, cascading permission lookups are suppressed.
	 * Used as a recursion guard in hasPermission().
	 */
	private static bool $inCascadingLookup = false;

	/**
	 * Initialize SMW properties.
	 *
	 * @param \SMW\PropertyRegistry $propertyRegistry
	 * @return bool
	 */
	public static function onSMWPropertyinitProperties( $propertyRegistry ) {
		// VISIBLE
		$propertyRegistry->registerProperty( '___VISIBLE', '_txt', 'Visible to' );
		$propertyRegistry->registerPropertyDescriptionByMsgKey( '__VISIBLE', 'sacl-property-visibility' );

		$propertyRegistry->registerProperty( '___VISIBLE_WL_GROUP', '_txt', 'Visible to group' );
		$propertyRegistry->registerPropertyDescriptionByMsgKey(
			'__VISIBLE_WL_GROUP', 'sacl-property-visibility-wl-group'
		);

		$propertyRegistry->registerProperty( '___VISIBLE_WL_USER', '_txt', 'Visible to user' );
		$propertyRegistry->registerPropertyDescriptionByMsgKey(
			'__VISIBLE_WL_USER', 'sacl-property-visibility-wl-user'
		);

		// EDITABLE
		$propertyRegistry->registerProperty( '___EDITABLE', '_txt', 'Editable by' );
		$propertyRegistry->registerPropertyDescriptionByMsgKey(
			'__EDITABLE', 'sacl-property-Editable'
		);

		$propertyRegistry->registerProperty( '___EDITABLE_WL_GROUP', '_txt', 'Editable by group' );
		$propertyRegistry->registerPropertyDescriptionByMsgKey(
			'__EDITABLE_WL_GROUP', 'sacl-property-editable-wl-group'
		);

		$propertyRegistry->registerProperty( '___EDITABLE_WL_USER', '_txt', 'Editable by user' );
		$propertyRegistry->registerPropertyDescriptionByMsgKey(
			'__EDITABLE_WL_USER', 'sacl-property-editable-wl-user'
		);

		$config = MediaWikiServices::getInstance()->getMainConfig();

		if ( $config->get( 'SemanticACLEnableCascadingACL' ) ) {
			// CASCADE
			$propertyRegistry->registerProperty(
				'___CASCADE_PERMISSIONS', '_boo', 'Cascade permissions to subpages'
			);
			$propertyRegistry->registerPropertyDescriptionByMsgKey(
				'___CASCADE_PERMISSIONS', 'sacl-property-cascade-permissions'
			);
		}

		return true;
	}

	/**
	 * Filter results out of queries the current user is not supposed to see.
	 */
	public static function onSMWStoreAfterQueryResultLookupComplete( Store $store, QueryResult &$queryResult ) {
		/* NOTE: this filtering does not work with count queries. To do filtering on count queries, we would
		 * have to use SMW::Store::BeforeQueryResultLookupComplete to add conditions on ACL properties.
		 * However, doing that would make it extremely difficult to tweak caching on results.
		 */

		$filtered = [];
		// Whether the result list was changed.
		$changed = false;
		$user = RequestContext::getMain()->getUser();

		foreach ( $queryResult->getResults() as $result ) {
			$title = $result->getTitle();
			if ( !$title instanceof Title ) {
				// T296559
				continue;
			}

			$accessible = true;

			/* Check if the current user has permission to view that item.
			 * Disable the handling of caching so we can do it ourselves.
			 */
			if ( !static::hasPermission( $title, 'read', $user, false ) ) {
				// That item is not always visible, disable caching.
				static::disableCaching();
				$accessible = false;
			} elseif ( $title->getNamespace() === NS_FILE && !static::fileHasRequiredCategory( $title ) ) {
				// That item is not always visible, disable caching.
				static::disableCaching();
				$accessible = false;
			} else {
				$semanticData = $store->getSemanticData( $result );

				// Look for a SemanticACL property in the page's list of properties.
				foreach ( $semanticData->getProperties() as $property ) {
					$key = $property->getKey();

					foreach ( [ '___VISIBLE', '___EDITABLE' ] as $semanticACLProperty ) {
						// If this is a SemanticACL property.
						if ( $key === $semanticACLProperty ) {
							foreach ( $semanticData->getPropertyValues( $property ) as $dataItem ) {
								// If this is a not a public item.
								if ( $dataItem->getSerialization() !== 'public' ) {
									// That item is not always visible, disable caching.
									static::disableCaching();
									break 3;
								}
							}
						}
					}
				}
			}

			if ( $accessible ) {
				$filtered[] = $result;
			} else {
				// Skip that item.
				$changed = true;
			}
		}

		if ( !$changed ) {
			// No changes to the query results.
			return;
		}

		// Build a new query result object
		$queryResult = new QueryResult(
			$queryResult->getPrintRequests(),
			$queryResult->getQuery(),
			$filtered,
			$store,
			$queryResult->hasFurtherResults()
		);
	}

	/**
	 * When checking against the bad image list.
	 * Change $bad and return false to override.
	 * If an image is "bad", it is not rendered inline in wiki pages or galleries in category pages.
	 *
	 * @param string $name image name being checked
	 * @param bool &$bad Whether or not the image is "bad"
	 * @return bool false if the image is bad
	 */
	public static function onBadImage( $name, &$bad ) {
		// Also works with galleries and categories.
		$title = Title::newFromText( $name, NS_FILE );
		$user = RequestContext::getMain()->getUser();

		if ( !static::fileHasRequiredCategory( $title ) && !$user->isAllowed( 'view-non-categorized-media' ) ) {
			static::disableCaching();
			$bad = true;
			return false;
		}

		if ( static::hasPermission( $title, 'read', $user, true ) ) {
			// The user is allowed to view that file.
			return true;
		}

		static::disableCaching();

		$bad = true;
		return false;
	}

	/**
	 * This hook is called before a template is fetched by Parser.
	 *
	 * @param ?LinkTarget $contextTitle The top-level page title, if any
	 * @param LinkTarget $title The template link (from the literal wikitext)
	 * @param bool &$skip Skip this template and link it?
	 * @param ?RevisionRecord &$revRecord The desired revision record
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public static function onBeforeParserFetchTemplateRevisionRecord( $contextTitle, $title, &$skip, &$revRecord ) {
		$user = RequestContext::getMain()->getUser();
		if ( static::hasPermission( $title, 'read', $user, true ) ) {
			// User is allowed to view that template.
			return true;
		}

		// Display error text instead of template.
		if ( $user->isAnon() ) {
			$msgKey = 'sacl-template-render-denied-anonymous';
		} else {
			$msgKey = 'sacl-template-render-denied-registered';
		}

		// Fetch the RevisionRecord for the replacement messages (used as a template).
		// Use a placeholder so the wikitext message (which may contain [[links]]) is embedded
		// inside the error box div without being pre-rendered to HTML (which would strip <a> tags).
		$placeholder = 'SEMACL_MSG';
		$wikitext = str_replace(
				$placeholder, wfMessage( $msgKey )->plain(), Html::errorBox( $placeholder )
			);
		$revRecord = new MutableRevisionRecord(
				Title::newFromText( $msgKey, NS_MEDIAWIKI )
			);
		$revRecord->setContent( SlotRecord::MAIN, new WikitextContent( $wikitext ) );

		return true;
	}

	/**
	 * To interrupt/advise the "user can do X to Y article" check.
	 *
	 * @param Title $title Title object being checked against
	 * @param \MediaWiki\User\User $user Current user object
	 * @param string $action Action being checked
	 * @param array|string|bool &$result User permissions error to add.
	 *   If none, return true. Can be a single error message key (string),
	 *   or an array of error message keys when multiple messages are needed.
	 * @return bool False to abort execution of any other function in this hook, true to allow
	 *   execution of other functions in this hook
	 */
	public static function onGetUserPermissionsErrors( $title, $user, $action, &$result ) {
		// This hook is also triggered when displaying search results.
		if ( !static::hasPermission( $title, $action, $user, false ) ) {
			$result = false;
			return false;
		}
		return true;
	}

	/**
	 * Register render callbacks with the parser.
	 *
	 * @param Parser &$parser
	 */
	public static function onParserFirstCallInit( &$parser ) {
		$parser->setFunctionHook( 'SEMANTICACL_PRIVATE_LINK', __CLASS__ . '::getPrivateLink' );
	}

	/** @var string The key used for the private link. */
	private static $_key = '';

	/** @var array Permission cache to avoid repeated expensive SMW store lookups. */
	private static array $_permissionCache = [];

	/**
	 * Render callback to get a private link.
	 *
	 * @param Parser &$parser The current parser
	 * @param string $key The key for the private link
	 * @return string A full URL with the correct arguments set on success, error message if $key
	 *   is too short
	 */
	public static function getPrivateLink( Parser &$parser, $key = '' ) {
		$enablePrivateLinks = static::getConfigWithDeprecatedFallback(
			'SemanticACLEnablePrivateLinks', 'EnablePrivateLinks'
		);

		if ( !$enablePrivateLinks ) {
			$key = wfMessage( 'sacl-private-links-disabled' )->text();
		}

		if ( strlen( $key ) <= self::MIN_KEY_LENGTH ) {
			// Must specify a key.
			return wfMessage( 'sacl-key-too-short' )->text();
		}

		static::disableCaching();

		/* Save the key. If this function is called within hasPermission() through template
		 * expansion, it will be used to confirm access using a private link.
		 */
		self::$_key = $key;

		return $parser->getTitle()->getFullURL( [ self::URL_ARG_NAME => urlencode( $key ) ] );
	}

	/**
	 * Checks if the provided user can do an action on a page.
	 *
	 * @param Title $title the title object to check permission on
	 * @param string $action the action the user wants to do
	 * @param \MediaWiki\User\User $user the user to check permissions for
	 * @param bool $disableCaching force the page being checked to be rerendered for each user
	 * @return bool if the user is allowed to conduct the action
	 */
	protected static function hasPermission( $title, $action, $user, $disableCaching = true ) {
		global $smwgNamespacesWithSemanticLinks;
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$whitelistIPs = $config->get( 'SemanticACLWhitelistIPs' );

		$isTalkPageWithOwnAcl = false;

		if ( $title->isTalkPage() ) {
			// Talk pages can define their own ACL annotations. If the talk
			// namespace supports semantic links and the talk page has any
			// ACL property (visible or editable), it owns its ACL. Missing
			// permission types fall back to the subject page individually.
			$talkNs = $title->getNamespace();

			if (
				isset( $smwgNamespacesWithSemanticLinks[$talkNs] ) &&
				$smwgNamespacesWithSemanticLinks[$talkNs]
			) {
				$talkSubject = DIWikiPage::newFromTitle( $title );
				$talkStore = StoreFactory::getStore()->getSemanticData( $talkSubject );
				$hasVisible = (bool)$talkStore->getPropertyValues( new DIProperty( '___VISIBLE' ) );
				$hasEditable = (bool)$talkStore->getPropertyValues( new DIProperty( '___EDITABLE' ) );
				$isTalkPageWithOwnAcl = $hasVisible || $hasEditable;
			}

			if ( !$isTalkPageWithOwnAcl ) {
				$title = $title->getSubjectPage();
			}
		} elseif ( \ExtensionRegistry::getInstance()->isLoaded( 'Flow' ) ) {
			// If the Flow extension is installed.
			if ( $title->getNamespace() === NS_TOPIC ) {
				// Retrieve the board associated with the topic.
				$storage = \Flow\Container::get( 'storage.workflow' );
				$uuid = \Flow\WorkflowLoaderFactory::uuidFromTitle( $title );
				$workflow = $storage->get( $uuid );
				if ( $workflow ) {
					// If for some reason there is no associated workflow, do not fail.
					return static::hasPermission( $workflow->getOwnerTitle(), $action, $user, $disableCaching );
				}
			}
		}

		if (
			!isset( $smwgNamespacesWithSemanticLinks[$title->getNamespace()] ) ||
			!$smwgNamespacesWithSemanticLinks[$title->getNamespace()]
		) {
			// No need to check permissions on namespaces that do not support SemanticMediaWiki
			return true;
		}

		// The prefix for the whitelisted group and user properties
		// Either ___VISIBLE or ___EDITABLE
		$prefix = '';

		// Build the semantic property prefix according to the action.
		if ( $action === 'read' || $action === 'raw' ) {
			$prefix = '___VISIBLE';
		} else {
			$prefix = '___EDITABLE';
		}

		if ( $disableCaching ) {
			/* If the parser caches the page, the same page will be returned
			 * without consideration for the user viewing the page. Disable the
			 * cache so it gets rendered anew for every user.
			 */
			static::disableCaching();
		}

		// Failsafe: Some users are exempt from Semantic ACLs.
		if ( $user->isAllowed( 'sacl-exempt' ) ) {
			return true;
		}

		// Do not use ACL in command line mode.
		if ( !static::$ignoreCliMode && defined( 'MW_ENTRY_POINT' ) && MW_ENTRY_POINT === 'cli' ) {
			return true;
		}

		// Always allow whitelisted IPs through.
		if ( $whitelistIPs !== null &&
			in_array( RequestContext::getMain()->getRequest()->getIP(), $whitelistIPs )
		) {
			return true;
		}

		if ( $title->getNamespace() === NS_FILE ) {
			if ( !static::fileHasRequiredCategory( $title ) && !$user->isAllowed( 'view-non-categorized-media' ) ) {
				return false;
			}
		}

		// Return the permission if it was computed before.
		$cacheKey = $title->getFullText() . '-' . $action . '-' . $user->getId();
		if ( isset( self::$_permissionCache[$cacheKey] ) ) {
			return self::$_permissionCache[$cacheKey];
		}

		// Fetch semantic data only after the cheap checks above.
		$subject = DIWikiPage::newFromTitle( $title );
		$store = StoreFactory::getStore()->getSemanticData( $subject );
		$property = new DIProperty( $prefix );
		$aclTypes = $store->getPropertyValues( $property );

		$hasPermission = true;

		// For each ACL specifier.
		foreach ( $aclTypes as $valueObj ) {
			switch ( strtolower( $valueObj->getString() ) ) {
				case 'whitelist':
					$isWhitelisted = false;

					$groupProperty = new DIProperty( "{$prefix}_WL_GROUP" );
					$userProperty = new DIProperty( "{$prefix}_WL_USER" );
					$whitelistValues = $store->getPropertyValues( $groupProperty );

					// Check if the current user is part of a whitelisted group.
					/* MediaWiki does not seem to specify whether groups are
					 * case sensitive or not. To account for all cases, group
					 * comparison is done in a case insensitive way.
					 * See: https://www.mediawiki.org/wiki/Topic:Vi9pg5qywcfjpqox
					 */
					$effectiveGroups = array_map(
						'strtolower',
						MediaWikiServices::getInstance()
							->getUserGroupManager()
							->getUserEffectiveGroups( $user )
					);

					foreach ( $whitelistValues as $whitelistValue ) {
						$group = strtolower( $whitelistValue->getString() );

						if ( in_array( $group, $effectiveGroups ) ) {
							$isWhitelisted = true;
							break;
						}
					}

					$whitelistValues = $store->getPropertyValues( $userProperty );

					foreach ( $whitelistValues as $whitelistValue ) {
						$userPage = Title::newFromDBkey( $whitelistValue->getString() );

						if ( $user->getUserPage()->equals( $userPage ) ) {
							$isWhitelisted = true;
						}
					}

					if ( !$isWhitelisted ) {
						$hasPermission = false;
					}

					break;

				case 'key':
					/* The key is parsed out of the page's content because it cannot be set as semantic
					 * property. Doing so would expose it to searches and queries.
					 */

					$enablePrivateLinks = static::getConfigWithDeprecatedFallback(
						'SemanticACLEnablePrivateLinks', 'EnablePrivateLinks'
					);

					if ( !$enablePrivateLinks ) {
						// Private links have been disabled.
						break;
					}

					// Only works when viewing pages.
					if ( $action !== 'read' && $action !== 'raw' ) {
						break;
					}

					/* Expand all the templates in the accessed page to retrieve
					 * the magic word. It will be stored in self::$_key and set
					 * there by the getPrivateLink() parser hook.
					 */
					// Use a new parser to avoid interfering with the current parser.
					$parser = MediaWikiServices::getInstance()->getParserFactory()->create();
					$parserOpts = \ParserOptions::newFromContext( RequestContext::getMain() );
					$parser->startExternalParse(
						$title, $parserOpts, \Parser::OT_PREPROCESS
					);
					$revision = Article::newFromTitle( $title, RequestContext::getMain() )
						->getPage()->getRevisionRecord();
					if ( !$revision ) {
						// Should not happen
						throw new LogicException( 'SemanticACL: unknown revision with the \'key\' mechanism' );
					}
					$content = $revision->getContent( SlotRecord::MAIN );
					if ( !( $content instanceof TextContent ) ) {
						// Not implemented on non-text contents
						break;
					}
					$text = $parser->recursivePreprocess(
						$content->getText(),
						$title,
						$parser->getOptions()
					);

					$query = RequestContext::getMain()->getRequest()->getQueryValues();

					// The key should have been set during page parsing and template expansion.
					$key = self::$_key;
					// Clear the key to prevent it from leaking to subsequent permission checks.
					self::$_key = '';

					// The key must be a certain length.
					if (
						strlen( $key ) > self::MIN_KEY_LENGTH &&
						isset( $query[self::URL_ARG_NAME] ) &&
						// If the key provided in the request arguments matches the key in the page.
						$query[self::URL_ARG_NAME] === $key
					) {
						$hasPermission = true;
					}

					break;

				case 'users':
					$hasPermission = !$user->isAnon();
					break;

				case 'public':
					$hasPermission = true;
			}
		}

		// Talk pages with their own ACL fall back to the subject page for
		// any missing permission type (e.g. has Visible to but no Editable by).
		// Cascading ACL is not supported for talk pages.
		if ( !$aclTypes && $isTalkPageWithOwnAcl ) {
			$hasPermission = static::hasPermission(
				$title->getSubjectPage(), $action, $user, $disableCaching
			);
			self::$_permissionCache[$cacheKey] = $hasPermission;
			return $hasPermission;
		}

		// When a page has no explicit edit restrictions, fall back to its
		// visibility restrictions — a page that is not visible should not
		// be editable either.
		if ( !$aclTypes && $prefix === '___EDITABLE' ) {
			$visibleProperty = new DIProperty( '___VISIBLE' );
			$visibleTypes = $store->getPropertyValues( $visibleProperty );

			if ( $visibleTypes ) {
				// Re-check using the read permission: if the user cannot
				// read the page, they certainly cannot edit it.
				return static::hasPermission( $title, 'read', $user, $disableCaching );
			}
		}

		// Check for cascading permissions.
		// Subpages can always override access control.
		// Only cascade for subpages.
		if ( !$aclTypes &&
			!self::$inCascadingLookup &&
			$config->get( 'SemanticACLEnableCascadingACL' ) &&
			$title->isSubPage()
		) {
			$parent = $title;

			do {
				$parent = $parent->getBaseTitle();

				if ( !$parent->exists() ) {
					if ( !$parent->isSubPage() ) {
						break;
					}
					// Skip page, it does not exist.
					continue;
				}

				// Check if cascading is enabled for the parent page.
				$subject = DIWikiPage::newFromTitle( $parent );
				$store = StoreFactory::getStore()->getSemanticData( $subject );
				$cascade = $store->getPropertyValues(
					new DIProperty( '___CASCADE_PERMISSIONS' )
				);

				// Get permissions from the parent page.
				if ( isset( $cascade[0] ) && $cascade[0]->getBoolean() ) {
					// Disable cascading during the lookup.
					self::$inCascadingLookup = true;
					try {
						$hasPermission = static::hasPermission(
							$parent, $action, $user, $disableCaching
						);
					} finally {
						self::$inCascadingLookup = false;
					}
					break;
				}

				// No cascading on that page, keep going.
			} while ( $parent->isSubPage() );
		}

		// Cache the permission.
		self::$_permissionCache[$cacheKey] = $hasPermission;

		return $hasPermission;
	}

	/**
	 * Disable caching for the page currently being rendered.
	 */
	/** @var ?\ReflectionProperty Cached reflection for Parser::$mOutput. */
	private static ?\ReflectionProperty $mOutputRef = null;

	protected static function disableCaching() {
		$parser = MediaWikiServices::getInstance()->getParser();

		// Parser::getOutput() emits E_USER_DEPRECATED when called before the
		// parser has been initialized (since MW 1.42). PHP offers no public API
		// to test for this, so we inspect the uninitialized typed property via
		// reflection (safe on PHP 8.1+ without setAccessible).
		self::$mOutputRef ??= new \ReflectionProperty( $parser, 'mOutput' );
		if ( self::$mOutputRef->isInitialized( $parser ) ) {
			$parser->getOutput()->updateCacheExpiry( 0 );
		}

		RequestContext::getMain()->getOutput()->disableClientCache();
	}

	/**
	 * Reset the permission cache. Intended for use in integration tests where
	 * multiple users are checked against the same page within a single test.
	 */
	public static function resetPermissionCache(): void {
		self::$_permissionCache = [];
	}

	/**
	 * Files that have not been categorized most likely have an unknown status when it comes to author's rights.
	 * This function tests if a file is part of a category for files whose's permissions have been set.
	 *
	 * @param Title $title the title of the file
	 * @return bool if the file has been properly categorized
	 */
	protected static function fileHasRequiredCategory( $title ) {
		$publicImagesCategory = static::getConfigWithDeprecatedFallback(
			'SemanticACLPublicImagesCategory', 'PublicImagesCategory'
		);

		if ( $publicImagesCategory && $title->getNamespace() === NS_FILE ) {
			$page = Article::newFromTitle( $title, RequestContext::getMain() );
			$file = $page->getFile();

			if ( !$file->isLocal() ) {
				// Foreign files are always shown.
				return true;
			}

			foreach ( $page->getForeignCategories() as $category ) {
				$normalized = str_replace( ' ', '_', $publicImagesCategory );
				if ( $category->getDBkey() === $normalized ) {
					return true;
				}
			}

			return false;
		}

		return true;
	}

	/**
	 * Get a config value, falling back to a deprecated global if the old
	 * name is still set in LocalSettings.php.
	 *
	 * @param string $newName The current config key (without "wg" prefix)
	 * @param string $oldName The deprecated global name (without "wg" prefix)
	 * @return mixed The config value
	 */
	private static function getConfigWithDeprecatedFallback( $newName, $oldName ) {
		$globalName = 'wg' . $oldName;
		if ( !empty( $GLOBALS[$globalName] ) ) {
			wfDeprecated(
				"\$$globalName was replaced with \$wg$newName", '0.3'
			);
			return $GLOBALS[$globalName];
		}
		return MediaWikiServices::getInstance()->getMainConfig()->get( $newName );
	}
}
