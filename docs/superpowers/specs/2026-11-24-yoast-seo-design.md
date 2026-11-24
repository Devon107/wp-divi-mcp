# Yoast SEO title and description — design

Date: 2026-11-24. Status: approved by the owner.

## Why

The owner uses Yoast SEO on client sites. They want Claude to set the SEO meta title and meta description of any page or post, new or existing. Today Yoast keeps these in protected meta (`_yoast_wpseo_title`, `_yoast_wpseo_metadesc`), and the plugin deliberately blocks underscore meta. So Claude cannot read or write them.

## Goals

- Read and write the Yoast meta title and meta description of any post type the plugin already exposes.
- Report both the stored values and the effective values the site renders (Yoast's template when the field is empty).
- The change is visible on the front end right away.

## Non-goals

- Focus keyphrase, social (OG/Twitter), noindex, canonical, primary category, Yoast site-wide settings and SEO/readability scores. They can be added later with the same pattern.

## Anti-goals

- No writes to other protected meta.
- No change to post content.
- No confirm flag for published pages: this touches only SEO meta, not content.

## Approaches

1. **Dedicated tools `seo_get` / `seo_update` (chosen).** They validate input, return effective values and give length warnings.
2. Allowlist the two meta keys in `update_post`. Rejected: no effective values, no warnings, and Yoast's cache could go stale.

## Design

- **Registration.** A new tool group registers only when Yoast SEO is active (`defined( 'WPSEO_VERSION' )`), the same way the Divi tools need Divi.
- **`seo_get {id}`** returns:
  - `{id, title, description, effective_title, effective_description, yoast_version}`.
  - The stored values are `''` when unset.
  - The effective values come from Yoast's own API (`YoastSEO()->meta->for_post( $id )`), with Yoast variables already replaced.
  - Readable by anyone who may edit the post. Same visibility rules as `get_post` for others.
- **`seo_update {id, title?, description?}`**:
  - Needs at least one field and `edit_post` on the post.
  - Strings are sanitized as plain text. Yoast variables such as `%%title%%` and `%%sep%%` pass through.
  - An empty string deletes the meta, so Yoast falls back to its template.
  - After writing, the plugin refreshes Yoast's indexable for the post (Yoast renders from its indexables table, not from post meta), then re-reads the effective values.
  - Returns `{id, previous: {title, description}, title, description, effective_title, effective_description, warnings}`.
  - Warnings: an effective title over 60 characters, or an effective description over 155 or under 50. Warnings only; it still saves.
  - Not available on read-only connections (`read_only: false`).
- **Activity log.** These tools use the existing tool-call logging.
- **Tests.** Yoast SEO is added to the wp-env tests environment, and the tests run against real Yoast:
  - read when unset;
  - write and effective update;
  - empty resets to the template;
  - variables pass through;
  - permission denied for another author's post;
  - read-only connection blocked;
  - tools hidden when Yoast is inactive;
  - length warnings.
- **Knowledge.** One line in the server instructions and `knowledge/guide.md` about the tools.
- **E2E.** On the non-WP-Engine test site (Yoast 28.9), on a new draft only.

## Left to the builder

- How to trigger the indexable refresh: use Yoast's own builder or watcher API, and confirm it against the Yoast source.
- Exact warning thresholds and their wording.
