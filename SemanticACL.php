<?php
/**
 * 
 * SemanticACL extension - Allows per-page read and edit restrictions to be set with properties.
 * 
 * This PHP entry point is deprecated. Please use wfLoadExtension() and the extension.json file
 * instead. See https://www.mediawiki.org/wiki/Manual:Extension_registration for more details.
 *
 * @link https://www.mediawiki.org/wiki/Extension:SemanticACL Documentation
 *
 * @file SemanticACL.php
 * @ingroup Extensions
 * @defgroup SemanticACL
 * @package MediaWiki
 * @author Tinss (Antoine Mercier-Linteau)
 * @license https://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'SemanticACL' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['SemanticACL'] = __DIR__ . '/i18n';
	wfWarn(
		'Deprecated PHP entry point used for SemanticACL extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the SemanticACL extension requires MediaWiki 1.31+' );
}

if ( !defined( 'MEDIAWIKI' ) ) {
	die();
}