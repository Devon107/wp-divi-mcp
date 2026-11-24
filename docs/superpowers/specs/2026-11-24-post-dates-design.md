# Publication dates and scheduling — design

Date: 2026-11-24. Status: approved by the owner.

## Why

The owner wants Claude to change publication dates (backdate or correct) and to schedule publishing. Scheduling already works through `create_post` / `update_post` with `status: "future"` and a future `date`. Two things are missing:

- `date` is refused for any status other than `future`;
- no response returns the publication date, only `modified`.

## Goals

- `date` is accepted with any status, on create and on update.
- Responses carry the publication date.

## Non-goals

- Bulk date edits.
- Changing the modified date.
- Time zone settings.

## Anti-goals

- Changing a date never touches content.
- Changing a date does not require `confirm_published`.

## Approaches

1. **Extend the existing `date` argument of `create_post` / `update_post` (chosen).** It is one argument with WordPress semantics.
2. A separate `set_post_date` tool. Rejected: it duplicates the validation and permissions of update_post, and gives Claude two ways to do the same thing.

## Design

- **`date` with any status.** The date is parsed in the site time zone, or as ISO 8601 with an offset, as today. Per resulting status:
  - `publish` or `private` with a past date: the date is set as given (backdating).
  - `publish` with a future date: the post becomes `future` (scheduled). WordPress does the same.
  - `future` still requires a future date; otherwise `invalid_argument`, as today.
  - `draft` or `pending`: the date is stored (`edit_date`) and kept when the post is published later.
- **Permissions.** Setting `date` requires the type's `publish_posts` capability, as in WordPress, where authors without it cannot set dates.
- **No `confirm_published`.** A date-only change does not need it. Content changes still do.
- **`update_post` response.** Adds `previous_date` when the date changed, for undo.
- **`date` field.** `PostFormatter::summary` gains `date`: the publication date in UTC, ISO 8601, falling back to the local date for drafts with a zeroed GMT date, like `modified`. It therefore appears in `list_posts`, `search`, `get_post`, `create_post` and `update_post`.
- **Docs.** The `date` argument description is updated. `knowledge/guide.md` gets one line.
- **Tests:**
  - backdate a published post;
  - publish with a future date gives `future`;
  - date on a draft is kept after publish;
  - `future` with a past date fails;
  - an author without `publish_posts` gets `forbidden`;
  - a date change on a published post works without `confirm_published`;
  - `previous_date`;
  - `date` in the summary, including drafts;
  - an unparseable date gives `invalid_argument`.

## Left to the builder

- Wording of the descriptions.
- Whether `previous_date` is also returned when the date did not change. Proposal: only when it changed.
