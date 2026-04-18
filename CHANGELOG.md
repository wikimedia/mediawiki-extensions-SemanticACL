# Changelog

All notable changes to the SemanticACL extension are documented in this file.

## 0.5 (2026-04-18)

* Add comprehensive PHPUnit integration test suite (45 tests, 96
  assertions) covering visibility, editability, whitelist, private
  links, cascading ACL, talk pages, Flow topics, SMW query filtering,
  search result filtering, IP whitelist, image/file protection, template
  transclusion denial, and end-to-end rendering
* Add TODO for allowing talk pages to define their own ACL annotations
  before falling back to the subject page

### Known bugs

* Visibility-only restriction does not imply edit restriction: a page
  with `[[Visible to::users]]` but no `Editable by` annotation still
  allows anonymous editing

## 0.4 (2025-08-30)

* Add support for Semantic MediaWiki 5.0+ (T392202)
* Require MediaWiki 1.43+
* Move template access denial markup from i18n messages to PHP using
  `Html::errorBox()`, making CSS class changes transparent to
  translators (T304760)

## 0.3 (2024-08-23)

* Implement cascading ACL: parent pages can cascade their permissions to
  subpages via `[[Cascade permissions to subpages::true]]`
  (off by default, enable with `$wgSemanticACLEnableCascadingACL`)
* Migrate to the `BeforeParserFetchTemplateRevisionRecord` hook,
  replacing the deprecated `ParserFetchTemplate`
* Fix deprecated `errorbox` CSS class, now uses `mw-message-box-error`
  (T304760)
* Rename configuration variables to conform with MediaWiki naming
  conventions: `$wgEnablePrivateLinks` -> `$wgSemanticACLEnablePrivateLinks`,
  `$wgPublicImageCategory` -> `$wgSemanticACLPublicImagesCategory`
* Fix inverted CLI-mode ACL bypass logic

## 0.2b (2020-03-20)

* Migrate to `extension.json` architecture
* Add support for private links via `{{#SEMANTICACL_PRIVATE_LINK:...}}`
  parser function and `semanticacl-key` query parameter
* Filter restricted pages from SMW `#ask` query results (T242699)
* Make user group comparison case insensitive
* Fix private link key retrieval when inside a template
* Fix parser recursive processing error with template permission checks
* Fix multiple template permission check
* Create new Parser instance to avoid interference with main Parser
* Handle missing `RequestContext::getMain()->getTitle()` in CLI mode
* Fix key access

## 0.2 (2019-12-20)

* Major rewrite by Antoine Mercier-Linteau
* Port to Semantic MediaWiki 3.0+
* Add support for image/file access control via `onBadImage` hook
* Add support for template transclusion access control via
  `ParserFetchTemplate` hook
* Add support for Flow extension: topics inherit their board page's ACL
* Add talk page ACL inheritance from subject page
* Add `view-non-categorized-media` user right and
  `$wgPublicImageCategory` configuration
* Add IP whitelist support via `$wgSemanticACLWhitelistIPs`
* Add `sacl-exempt` to `$wgAvailableRights`
* Add edit restriction properties (`Editable by`, `Editable by group`,
  `Editable by user`)

## 0.1 (2011-05-24)

* Initial release by Andrew Garrett (Werdna)
* Basic page visibility control using semantic properties
  (`Visible to`, `Visible to group`, `Visible to user`)
* Whitelist support for groups and individual users
* `sacl-exempt` permission for sysops to bypass ACL
