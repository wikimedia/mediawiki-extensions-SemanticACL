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
 * @license https://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

namespace MediaWiki\Extension\SemanticACL;

use Article;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use RequestContext;
use SMW;
use SMWDIProperty;
use SMWDIWikiPage;
use SMWQueryResult;
use Title;

/**
 * SemanticACL extension main class.
 */
class SemanticACL {
	/** The minimum key length for private link access. */
	const MIN_KEY_LENGTH = 6;

	/** The name of URL argument for private link access. */
	const URL_ARG_NAME = 'semanticacl-key';

	/**
	 * Initialize SMW properties.
	 */
	public static function onSMWPropertyinitProperties( $propertyRegistry ) {
		// VISIBLE
		$propertyRegistry->registerProperty( '___VISIBLE', '_txt', 'Visible to' );
		$propertyRegistry->registerPropertyDescriptionByMsgKey( '__VISIBLE',	'sacl-property-visibility' );

		$propertyRegistry->registerProperty( '___VISIBLE_WL_GROUP', '_txt', 'Visible to group' );
		$propertyRegistry->registerPropertyDescriptionByMsgKey( '__VISIBLE_WL_GROUP', 'sacl-property-visibility-wl-group' );

		$propertyRegistry->registerProperty( '___VISIBLE_WL_USER', '_txt', 'Visible to user' );
		$propertyRegistry->registerPropertyDescriptionByMsgKey( '__VISIBLE_WL_USER',	'sacl-property-visibility-wl-user' );

		// EDITABLE
		$propertyRegistry->registerProperty( '___EDITABLE', '_txt', 'Editable by' );
		$propertyRegistry->registerPropertyDescriptionByMsgKey( '__EDITABLE', 'sacl-property-Editable' );

		$propertyRegistry->registerProperty( '___EDITABLE_WL_GROUP', '_txt', 'Editable by group' );
		$propertyRegistry->registerPropertyDescriptionByMsgKey( '__EDITABLE_WL_GROUP', 'sacl-property-editable-wl-group' );

		$propertyRegistry->registerProperty( '___EDITABLE_WL_USER', '_txt', 'Editable by user' );
		$propertyRegistry->registerPropertyDescriptionByMsgKey( '__EDITABLE_WL_USER', 'sacl-property-editable-wl-user' );

		return true;
	}

	/**
	 * Filter results out of queries the current user is not supposed to see.
	 */
	public static function onSMWStoreAfterQueryResultLookupComplete( SMW\Store $store, SMWQueryResult &$queryResult ) {
		/* NOTE: this filtering does not work with count queries. To do filtering on count queries, we would
		 * have to use SMW::Store::BeforeQueryResultLookupComplete to add conditions on ACL properties.
		 * However, doing that would make it extremely difficult to tweak caching on results.
		 */

		global $wgUser;
		$filtered = [];
		$changed = false; // If the result list was changed.

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
			if ( !self::hasPermission( $title, 'read', $wgUser, false ) ) {
				self::disableCaching(); // That item is not always visible, disable caching.
				$accessible = false;
			} elseif ( $title->getNamespace() == NS_FILE && !self::fileHasRequiredCategory( $title ) ) {
				self::disableCaching(); // That item is not always visible, disable caching.
				$accessible = false;
			} else {
				$semanticData = $store->getSemanticData( $result );

				// Look for a SemanticACL property in the page's list of properties.
				foreach ( $semanticData->getProperties() as $property ) {
					$key = $property->getKey();

					foreach ( [ '___VISIBLE', '___EDITABLE' ] as $semanticACLProperty ) {
						// If this is a SemanticACL property.
						if ( $key == $semanticACLProperty ) {
							foreach ( $semanticData->getPropertyValues( $property ) as $dataItem ) {
								// If this is a not a public item.
								if ( $dataItem->getSerialization() != 'public' ) {
									self::disableCaching(); // That item is not always visible, disable caching.
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
		$queryResult = new SMWQueryResult(
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

		if ( !self::fileHasRequiredCategory( $title ) && !$user->isAllowed( 'view-non-categorized-media' ) ) {
			self::disableCaching();
			$bad = true;
			return false;
		}

		if ( self::hasPermission( $title, 'read', $user, true ) ) {
			// The user is allowed to view that file.
			return true;
		}

		self::disableCaching();

		$bad = true;
		return false;
	}

	/**
	 * Called when the parser fetches a template.
	 * Replaces the template with an error message if the user cannot view the template.
	 *
	 * @param \Parser|false $parser Parser object or false
	 * @param Title $title Title object of the template to be fetched
	 * @param \Revision $rev Revision object of the template
	 * @param string|false|null $text transclusion text of the template or false or null
	 * @param array $deps array of template dependencies with 'title', 'page_id', 'rev_id' keys
	 * @return bool False if an error text oughta be shown, else true
	 */
	public static function onParserFetchTemplate( $parser, $title, $rev, &$text, &$deps ) {
		$user = RequestContext::getMain()->getUser();
		if ( self::hasPermission( $title, 'read', $user, true ) ) {
			// User is allowed to view that template.
			return true;
		}

		// Display error text instead of template.
		if ( $user->isAnon() ) {
			$msgKey = 'sacl-template-render-denied-anonymous';
		} else {
			$msgKey = 'sacl-template-render-denied-registered';
		}
		$text = wfMessage( $msgKey )->plain();

		return false;
	}

	/**
	 * To interrupt/advise the "user can do X to Y article" check.
	 *
	 * @param Title $title Title object being checked against
	 * @param \User $user Current user object
	 * @param string $action Action being checked
	 * @param array|string &$result User permissions error to add. If none, return true.
	 *   $result can be returned as a single error message key (string), or an array of error message
	 *   keys when multiple messages are needed (although it seems to take an array as one message key with parameters?).
	 * @return bool if the user has permissions to do the action
	 */
	public static function onUserCan( &$title, &$user, $action, &$result ) {
		// This hook is also triggered when displaying search results.
		return self::hasPermission( $title, $action, $user, false );
	}

	/**
	 * Register render callbacks with the parser.
	 *
	 * @param \Parser &$parser
	 */
	public static function onParserFirstCallInit( &$parser ) {
		$parser->setFunctionHook( 'SEMANTICACL_PRIVATE_LINK', __CLASS__ . '::getPrivateLink' );
	}

	/** @var string The key used for the private link. */
	private static $_key = '';

	/**
	 * Render callback to get a private link.
	 *
	 * @param \Parser &$parser The current parser
	 * @param string $key The key for the private link
	 * @return string A full URL with the correct arguments set on success, error message if $key
	 *   is too short
	 */
	public static function getPrivateLink( \Parser &$parser, $key = '' ) {
		global $wgEnablePrivateLinks;

		if ( !$wgEnablePrivateLinks ) {
			$key = wfMessage( 'sacl-private-links-disabled' )->text();
		}

		if ( strlen( $key ) <= self::MIN_KEY_LENGTH ) {
			// Must specify a key.
			return wfMessage( 'sacl-key-too-short' )->text();
		}

		self::disableCaching();

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
	 * @param \User $user the user to check permissions for
	 * @param bool $disableCaching force the page being checked to be rerendered for each user
	 * @return bool if the user is allowed to conduct the action
	 */
	protected static function hasPermission( $title, $action, $user, $disableCaching = true ) {
		global $smwgNamespacesWithSemanticLinks;
		global $wgSemanticACLWhitelistIPs;
		global $wgRequest;

		if ( $title->isTalkPage() ) {
			// Talk pages get the same permission as their subject page.
			$title = $title->getSubjectPage();
		} elseif ( \ExtensionRegistry::getInstance()->isLoaded( 'Flow' ) ) {
			// If the Flow extension is installed.
			if ( $title->getNamespace() == NS_TOPIC ) {
				// Retrieve the board associated with the topic.
				$storage = \Flow\Container::get( 'storage.workflow' );
				$uuid = \Flow\WorkflowLoaderFactory::uuidFromTitle( $title );
				$workflow = $storage->get( $uuid );
				if ( $workflow ) {
					// If for some reason there is no associated workflow, do not fail.
					return self::hasPermission( $workflow->getOwnerTitle(), $action, $user, $disableCaching );
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
		if ( $action == 'read' || $action == 'raw' ) {
			$prefix = '___VISIBLE';
		} else {
			$prefix = '___EDITABLE';
		}

		$subject = SMWDIWikiPage::newFromTitle( $title );
		$store = SMW\StoreFactory::getStore()->getSemanticData( $subject );
		$property = new SMWDIProperty( $prefix );
		$aclTypes = $store->getPropertyValues( $property );

		if ( $disableCaching ) {
			/* If the parser caches the page, the same page will be returned without consideration for the user viewing the page.
			 * Disable the cache to it gets rendered anew for every user.
			 */
			self::disableCaching();
		}

		// Failsafe: Some users are exempt from Semantic ACLs.
		if ( $user->isAllowed( 'sacl-exempt' ) ) {
			return true;
		}

		// Always allow whitelisted IPs through.
		if (
			isset( $wgSemanticACLWhitelistIPs ) &&
			in_array( $wgRequest->getIP(), $wgSemanticACLWhitelistIPs )
		) {
			return true;
		}

		if ( $title->getNamespace() == NS_FILE ) {
			if ( !self::fileHasRequiredCategory( $title ) && !$user->isAllowed( 'view-non-categorized-media' ) ) {
				return false;
			}
		}

		$hasPermission = true;

		foreach ( $aclTypes as $valueObj ) { // For each ACL specifier.
			switch ( strtolower( $valueObj->getString() ) ) {
				case 'whitelist':
					$isWhitelisted = false;

					$groupProperty = new SMWDIProperty( "{$prefix}_WL_GROUP" );
					$userProperty = new SMWDIProperty( "{$prefix}_WL_USER" );
					$whitelistValues = $store->getPropertyValues( $groupProperty );

					// Check if the current user is part of a whitelisted group.
					foreach ( $whitelistValues as $whitelistValue ) {
						/* MediaWiki does not seem to specify whether groups are case sensitive or not.
						 * To account for all cases group comparison is done in a case insentitive way.
						 * See: https://www.mediawiki.org/wiki/Topic:Vi9pg5qywcfjpqox
						 */
						$group = strtolower( $whitelistValue->getString() );

						if ( method_exists( MediaWikiServices::class, 'getUserGroupManager' ) ) {
							// MW 1.35+
							$effectiveGroups = MediaWikiServices::getInstance()->getUserGroupManager()
								->getUserEffectiveGroups( $user );
						} else {
							$effectiveGroups = $user->getEffectiveGroups();
						}

						if ( in_array( $group, array_map( 'strtolower', $effectiveGroups ) ) ) {
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

					global $wgEnablePrivateLinks;
					if ( !$wgEnablePrivateLinks ) {
						// Private links have been disabled.
						break;
					}

					// Only works when viewing pages.
					if ( $action != 'read' && $action != 'raw' ) {
						break;
					}

					/* Expand all the templates in the accessed page to retrieve the magic word.
					 * The magic word will be stored in self::$_key and set there by the getPrivateLink() parser hook.
					 */
					// Use a new parser to avoid interfering with the current parser.
					$parser = \MediaWiki\MediaWikiServices::getInstance()->getParserFactory()->create();
					$parser->startExternalParse( $title, \ParserOptions::newFromContext( RequestContext::getMain() ), \Parser::OT_PREPROCESS );
					$text = $parser->recursivePreprocess(
						Article::newFromTitle( $title, RequestContext::getMain() )->getPage()->getRevisionRecord()->getContent( SlotRecord::MAIN )->getNativeData(),
						$title,
						$parser->mOptions
					);

					$query = RequestContext::getMain()->getRequest()->getQueryValues();

					$key = self::$_key; // The key normally should have been set during page parsing and template expansion.

					if (
						strlen( $key ) > self::MIN_KEY_LENGTH && // The key must be a certain length.
						isset( $query[self::URL_ARG_NAME] ) &&
						// If the key provided in the request arguments matches the key in the page.
						$query[self::URL_ARG_NAME] === $key
					) {
						return true;
					}

					break;

				case 'users':
					$hasPermission = !$user->isAnon();
					break;

				case 'public':
					return true;
			}
		}

		return $hasPermission;
	}


	/**
	 * Disable caching for the page currently being rendered.
	 */
	protected static function disableCaching() {
		global $wgParser;

		if ( $wgParser->getOutput() ) {
			$wgParser->getOutput()->updateCacheExpiry( 0 );
		}

		RequestContext::getMain()->getOutput()->enableClientCache( false );
	}

	/**
	 * Files that have not been categorized most likely have an unknown status when it comes to author's rights.
	 * This function tests if a file is part of a category for files whose's permissions have been set.
	 *
	 * @param Title $title the title of the file
	 * @return bool if the file has been properly categorized
	 */
	protected static function fileHasRequiredCategory( $title ) {
		global $wgPublicImagesCategory;

		if (
			isset( $wgPublicImagesCategory ) && $wgPublicImagesCategory &&
			$title->getNamespace() == NS_FILE
		) {
			$inCategory = false;

			$page = Article::newFromTitle( $title, RequestContext::getMain() );
			$file = $page->getFile();

			if ( !$file->isLocal() ) {
				// Foreign files are always shown.
				return true;
			}

			foreach ( $page->getCategories() as $category ) {
				if ( $category->getDBkey() == str_replace( ' ', '_', $wgPublicImagesCategory ) ) {
					return true;
				}
			}

			return false;
		}

		return true;
	}
}
