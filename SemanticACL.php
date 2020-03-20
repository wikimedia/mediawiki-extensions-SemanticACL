<?php
/**
 * SemanticACL extension - Allows per-page read and edit restrictions to be set with properties.
 *
 * @link https://www.mediawiki.org/wiki/Extension:SemanticACL Documentation
 *
 * @file SemanticACL.php
 * @ingroup Extensions
 * @defgroup SemanticACL
 * @package MediaWiki
 * @author Werdna (Andrew Garrett)
 * @author Tinss (Antoine Mercier-Linteau)
 * @copyright (C) 2011 Werdna
 * @license https://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

// Ensure that the script cannot be executed outside of MediaWiki.
if ( !defined( 'MEDIAWIKI' ) ) {
    die( 'This is an extension to MediaWiki and cannot be run standalone.' );
}

// Display extension properties on MediaWiki.
$wgExtensionCredits['semantic'][] = array(
	'path' => __FILE__,
	'name' => 'Semantic ACL',
	'author' => array(
		'Andrew Garrett',
	    'Antoine Mercier-Linteau',
		'...'
	),
	'descriptionmsg' => 'sacl-desc',
	'url' => 'https://www.mediawiki.org/wiki/Extension:SemanticACL',
	'license-name' => 'GPL-2.0-or-later'
);

// Register extension messages and other localisation.
$wgMessagesDirs['SemanticACL'] = __DIR__ . '/i18n';

// Register extension hooks.
$wgHooks['userCan'][] = 'saclGetUserPermissionsErrors';
$wgHooks['ParserFetchTemplate'][] = 'saclParserFetchTemplate';
$wgHooks['BadImage'][] = 'saclBadImage';

// Create extension's permissions
$wgGroupPermissions['sysop']['sacl-exempt'] = true;
$wgAvailableRights[] = 'sacl-exempt';

/* Initialise predefined properties. */
\Hooks::register( 'SMW::Property::initProperties', function( $propertyRegistry ) {

	// VISIBLE
	$propertyRegistry->registerProperty( '___VISIBLE', '_txt', 'Visible to' );
	$propertyRegistry->registerPropertyDescriptionByMsgKey('__VISIBLE',	'sacl-property-visibility');

	$propertyRegistry->registerProperty( '___VISIBLE_WL_GROUP', '_txt', 'Visible to group' );
	$propertyRegistry->registerPropertyDescriptionByMsgKey('__VISIBLE_WL_GROUP', 'sacl-property-visibility-wl-group');

	$propertyRegistry->registerProperty( '___VISIBLE_WL_USER', '_txt', 'Visible to user' );
	$propertyRegistry->registerPropertyDescriptionByMsgKey('__VISIBLE_WL_USER',	'sacl-property-visibility-wl-user');

	// EDITABLE
	$propertyRegistry->registerProperty( '___EDITABLE', '_txt', 'Editable by' );
	$propertyRegistry->registerPropertyDescriptionByMsgKey('__EDITABLE', 'sacl-property-Editable');

	$propertyRegistry->registerProperty( '___EDITABLE_WL_GROUP', '_txt', 'Editable by group' );
	$propertyRegistry->registerPropertyDescriptionByMsgKey('__EDITABLE_WL_GROUP', 'sacl-property-editable-wl-group');

	$propertyRegistry->registerProperty( '___EDITABLE_WL_USER', '_txt', 'Editable by user' );
	$propertyRegistry->registerPropertyDescriptionByMsgKey('__EDITABLE_WL_USER', 'sacl-property-editable-wl-user');

	return true;
} );

/* Filter results  out of queries the current user is not supposed to see. */
\Hooks::register( 'SMW::Store::AfterQueryResultLookupComplete', function( SMW\Store $store, SMWQueryResult &$queryResult ) {
    
    /* NOTE: this filtering does not work with count queries. To do filtering on count queries, we would
     * have to use SMW::Store::BeforeQueryResultLookupComplete to add conditions on ACL properties. 
     * However, doing that would make it extremely difficult to tweak caching on results.*/
    
    global $wgUser;
    $filtered = [];
    $changed = false; // If the result list was changed.
    
    foreach($queryResult->getResults() as $result) {
        
        $title = $result->getTitle();
        $accessible = true;
        
        /* Check if the current user has permission to view that item.
         * Disable the handling of caching so we can do it ourselves.
         * */
        if(!hasPermission($title, 'read', $wgUser, false)) {
            
            disableCaching(); // That item is not always visible, disable caching.
            $accessible = false;
        }
        else if($title->getNamespace() == NS_FILE && !fileHasRequiredCategory($title)) {
            
            disableCaching(); // That item is not always visible, disable caching.
            $accessible = false;
        }
        else {
            
            $semanticData = $store->getSemanticData($result);
            
            // Look for a SemanticACL property in the page's list of properties.
            foreach($semanticData->getProperties() as $property) {
                
                $key = $property->getKey();
                
                foreach(['___VISIBLE', '___EDITABLE'] as $semanticACLProperty) {
                    
                    // If this is a SemanticACL property.
                    if($key == $semanticACLProperty) {
                        
                        foreach($semanticData->getPropertyValues($property) as $dataItem) {
                            
                            // If this is a not a public item.
                            if($dataItem->getSerialization() != 'public') {
                                
                                disableCaching(); // That item is not always visible, disable caching.
                                break 3;
                            }
                        }
                    }
                }
            }
        }
        
        if($accessible) { $filtered[] = $result; } 
        else { $changed = true; } // Skip that item.
    }
    
    if(!$changed) { return; } // No changes to the query results.
    
    // Build a new query result object
    $queryResult = new SMWQueryResult(
        $queryResult->getPrintRequests(),
        $queryResult->getQuery(),
        $filtered,
        $store,
        $queryResult->hasFurtherResults()
    );
    
} );

/** When checking against the bad image list. Change $bad and return
 * false to override. If an image is "bad", it is not rendered inline in wiki
 * pages or galleries in category pages.
 * @param string $name image name being checked
 * @param bool &$bad  Whether or not the image is "bad"
 * @return bool false if the image is bad */
function saclBadImage($name, &$bad) {
	// Also works with galleries and categories.

	$title = Title::newFromText($name, NS_FILE);

	$user = RequestContext::getMain()->getUser();

	if(!fileHasRequiredCategory($title) && !$user->isAllowed( 'view-non-categorized-media') ) {
		disableCaching();
		$bad  = true;
		return false;
	}

	if(hasPermission($title, 'read', $user , true)){
		return true; // The user is allowed to view that file.
	}

	disableCaching();

	$bad = true;
	return false;
}

/**
 * Called when the parser fetches a template. Replaces the template with an error message if the user cannot
 * view the template.
 * @param Parser|false $parser Parser object or false
 * @param Title $title Title object of the template to be fetched
 * @param Revision $rev Revision object of the template
 * @param string|false|null $text transclusion text of the template or false or null
 * @param array $deps array of template dependencies with 'title', 'page_id', 'rev_id' keys
 * */
function saclParserFetchTemplate($parser, $title, $rev, &$text, &$deps) {
//function saclCheckTemplatePermission ($parser, $title, &$skip, $id) {

	if(hasPermission($title, 'read', RequestContext::getMain()->getUser(), true)){
		return true; // User is allowed to view that template.
	}

	global $wgHooks;

	$hookName = 'saclParserFetchTemplate';
	if($hookIndex = array_search($hookName, $wgHooks['ParserFetchTemplate']) === false) {
		throw new Exception('Function name could no be found in hook.'); // This would only happen with a code refactoring mistake.
	}

	// Since we will be rendering wikicode, unset the hook to prevent a recursive permission error on templates.
	unset($wgHooks['ParserFetchTemplate'][$hookIndex]);

	$text = wfMessage(RequestContext::getMain()->getUser()->isAnon() ? 'sacl-template-render-denied-anonymous' : 'sacl-template-render-denied-registered')->plain();

	$wgHooks['ParserFetchTemplate'] = $hookName; // Reset the hook.

	return false;
}

/**
 *  To interrupt/advise the "user can do X to Y article" check.
 * @param Title $title Title object being checked against
 * @param User $user Current user object
 * @param string $action Action being checked
 * @param array|string &$result User permissions error to add. If none, return true. $result can be returned as a single error message key (string), or an array of error message keys when multiple messages are needed (although it seems to take an array as one message key with parameters?).
 * @return bool if the user has permissions to do the action
 * */
function saclGetUserPermissionsErrors( &$title, &$user, $action, &$result ) {

	// This hook is also triggered when displaying search results.

	return hasPermission($title, $action, $user, false);
}

/** Checks if the provided user can do an action on a page.
 * @param Title $title the title object to check permission on
 * @param string $action the action the user wants to do
 * @param User $user the user to check permissions for
 * @param bool $disableCaching force the page being checked to be rerendered for each user
 * @return boolean if the user is allowed to conduct the action */
function hasPermission($title, $action, $user, $disableCaching = true) {
	global $smwgNamespacesWithSemanticLinks;
	global $wgSemanticACLWhitelistIPs;
	global $wgRequest;

	if($title->isTalkPage())
	{
		$title = $title->getSubjectPage(); // Talk pages get the same permission as their subject page.
	}
	else if(\ExtensionRegistry::getInstance()->isLoaded( 'Flow' )) // If the Flow extension is installed.
	{
		if($title->getNamespace() == NS_TOPIC)
		{
			// Retrieve the board associated with the topic.
			$storage = Flow\Container::get( 'storage.workflow' );
			$uuid = Flow\WorkflowLoaderFactory::uuidFromTitle( $title );
			$workflow = $storage->get( $uuid );
			if($workflow) // If for some reason there is no associated workflow, do not fail.
			{
				return hasPermission($workflow->getOwnerTitle(), $action, $user, $disableCaching);
			}
		}
	}

	if(!isset($smwgNamespacesWithSemanticLinks[$title->getNamespace()]) || !$smwgNamespacesWithSemanticLinks[$title->getNamespace()]) {
		return true; // No need to check permissions on namespaces that do not support SemanticMediaWiki
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
	$store = SMW\StoreFactory::getStore()->getSemanticData($subject);
	$property = new SMWDIProperty($prefix);
	$aclTypes = $store->getPropertyValues( $property );

	if($disableCaching)
	{
		/* If the parser caches the page, the same page will be returned without consideration for the user viewing the page.
		 * Disable the cache to it gets rendered anew for every user. */
		disableCaching();
	}

	// Failsafe: Some users are exempt from Semantic ACLs.
	if ( $user->isAllowed( 'sacl-exempt') ) {
		return true;
	}

	// Always allow whitelisted IPs through.
	if(isset($wgSemanticACLWhitelistIPs) && in_array($wgRequest->getIP(), $wgSemanticACLWhitelistIPs))
	{
		return true;
	}

	if($title->getNamespace() == NS_FILE) {

		if(!fileHasRequiredCategory($title) && !$user->isAllowed( 'view-non-categorized-media') ) {
			return false;
		}
	}

	foreach( $aclTypes as $valueObj ) {

		$value = strtolower($valueObj->getString());

		if ( $value == 'users' ) {
			if ( $user->isAnon() ) {
				return false;
			}
		} elseif ( $value == 'whitelist' ) {
			$isWhitelisted = false;

			$groupProperty = new SMWDIProperty( "{$prefix}_WL_GROUP" );
			$userProperty = new SMWDIProperty( "{$prefix}_WL_USER" );
			$whitelistValues = $store->getPropertyValues( $groupProperty );
            
			// Check if the current user is part of a whitelisted group.
			foreach( $whitelistValues as $whitelistValue ) {
			    /* MediaWiki does not seem to specify whether groups are case sensitive or not.
			     * To account for all cases group comparison is done in a case insentitive way.
			     * See: https://www.mediawiki.org/wiki/Topic:Vi9pg5qywcfjpqox
			     * */
			    $group = strtolower($whitelistValue->getString());
			    
			    if (in_array($group, array_map('strtolower', $user->getEffectiveGroups())))
			    {
			        $isWhitelisted = true;
			        break;
			    }
			}

			$whitelistValues = $store->getPropertyValues( $userProperty );

			foreach( $whitelistValues as $whitelistValue ) {
				$title = Title::newFromDBkey($whitelistValue->getString());

				if ( $user->getUserPage()->equals($title) ) {
					$isWhitelisted = true;
				}
			}

			if ( ! $isWhitelisted ) {
				return false;
			}
		} elseif ( $value == 'public' ) {
		    
			return true;
		}
	}

	return true;
}


/** Disable caching for the page currently being rendered. */
function disableCaching() {
	global $wgParser;
	if($wgParser->getOutput()) {
		$wgParser->getOutput()->updateCacheExpiry(0);
	}

	RequestContext::getMain()->getOutput()->enableClientCache(false);
}

/** Files that have not been categorized most likely have an unknown status when it comes to author's rights. This function tests if
 * a file is part of a category for files whose's permissions have been set.
 * @param Title $title the title of the file
 * @return boolean if the file has been properly categorized */
function fileHasRequiredCategory($title) {
	global $wgPublicImagesCategory;

	if(isset($wgPublicImagesCategory) && $wgPublicImagesCategory && $title->getNamespace() == NS_FILE) {

		$inCategory = false;

		$page = Article::newFromTitle($title, RequestContext::getMain());
		$file = $page->getFile(); //wfFindFile( $this->getTitle() )

		if(!$file->isLocal()) { // Foreign files are always shown.
			return true;
		}

		foreach($page->getCategories() as $category)
		{
			if($category->getDBKey() == str_replace(" ", "_", $wgPublicImagesCategory))
			{
				return true;
			}
		}

		return false;
	}

	return true;
}
