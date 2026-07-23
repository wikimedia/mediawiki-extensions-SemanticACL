# Changelog

All notable changes to the SemanticACL extension are documented in this file.

## 0.5.2 (2026-07-23)

* Keep pages whose only ACL value is `public` cacheable: an explicit
  `[[Visible to::public]]` annotation (common on file pages cleared for
  public viewing) grants everyone by definition, but still counted as
  "verdict may vary by user" and disabled caching for the page — and
  for every page embedding such a file or template

## 0.5.1 (2026-07-22)

* Fix pages containing any image or template transclusion never being
  cacheable: the file/bad-image and template permission checks that run
  during every parse unconditionally disabled the parser cache and
  client/CDN caching for the host page. Caching is now only disabled
  when the verdict can vary by user — the checked page carries ACL
  properties for the action (or visibility properties, for edit checks),
  is a non-categorized file while a public images category is required,
  or inherits ACLs from a parent with cascading permissions. The
  decision still happens before the `sacl-exempt` and whitelisted-IP
  early-returns, so a privileged rendering of protected content can
  never enter a shared cache
* Keep ACL pages HTTP-cacheable for plain anonymous requests: the
  HTTP/CDN cache only ever stores anonymous responses, and the
  canonical anonymous rendering (including denials and filtered
  results) is identical for every anonymous visitor. The client cache
  is now only disabled for requests that can produce a non-canonical
  rendering — registered users, requests carrying a private-link key
  (whose response would outlive key rotation under the keyed URL), and
  ACL-whitelisted IPs. The parser cache, being shared across all
  users, is still always invalidated on ACL-influenced renderings
* Fix a fatal error on every view of a page carrying a malformed
  `Visible to user` value (the invalid title parsed to null and crashed
  the whitelist comparison); such values now simply never match
* Fix `{{#SEMANTICACL_PRIVATE_LINK:}}` emitting a bogus URL (with the
  "disabled" notice embedded as the key) when private links are turned
  off; it now returns the notice itself
* Prevent a private-link key defined by another page parsed earlier in
  the same request from granting access to a page that declares the
  `key` audience without defining a key of its own
* Fix the six ACL property descriptions never appearing on
  Special:Properties (they were registered under property ids with two
  leading underscores instead of three)
* Guard `onBadImage()` against invalid file names that fail title
  parsing instead of throwing a fatal error
* Compare private-link keys in constant time (`hash_equals()`)
* Fix a private link key being ignored on a page that also defines a group
  or user whitelist. Multiple `Visible to` audience specifiers are now
  combined as a logical AND (the safest, most restrictive verdict wins),
  while a matching private link key acts as a separate override that grants
  read access regardless of the audience verdict, independent of the order
  in which the Semantic MediaWiki store returns the values

## 0.5 (2026-04-18)

* Add comprehensive PHPUnit integration test suite (49 tests, 107
  assertions) covering visibility, editability, whitelist, private
  links, cascading ACL, talk pages, Flow topics, SMW query filtering,
  search result filtering, IP whitelist, image/file protection, template
  transclusion denial, and end-to-end rendering
* Allow talk pages to define their own ACL annotations before falling
  back to the subject page (requires the talk namespace to have
  semantic links enabled in `$smwgNamespacesWithSemanticLinks`);
  missing permission types fall back to the subject page individually
  and cascading ACL is not supported for talk pages
* Fix visibility-only restriction not implying edit restriction: a page
  with `[[Visible to::users]]` but no `Editable by` annotation now
  correctly denies editing to users who cannot read it

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
