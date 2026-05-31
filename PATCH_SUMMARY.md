# Patch Summary for XML Sync and CLI Rebuild

This file records the recent patches applied to make the to-do app synchronize task/user data into XML successfully and support CLI rebuilds.

## Files Updated

### `config.php`

- Replaced `BASE_URL` construction that directly used `$_SERVER['REQUEST_SCHEME']` and `$_SERVER['HTTP_HOST']`.
- Added CLI-safe fallback logic:
  - Default scheme to `http`.
  - Use `$_SERVER['REQUEST_SCHEME']` when available.
  - Use `https` when `$_SERVER['HTTPS']` is set and not `off`.
  - Use `localhost` when `$_SERVER['HTTP_HOST']` is absent.
- This prevents warnings and errors during CLI-driven operations such as `run_sync.php`.

### `run_sync.php`

- Added CLI-specific configuration when `PHP_SAPI === 'cli'`:
  - `ini_set('max_execution_time', '0');`
  - `set_time_limit(0);`
  - `ini_set('memory_limit', '256M');`
- This allows the XML rebuild process to complete without being killed by the default 30-second PHP CLI execution limit.

### `xml_sync_handler.php`

- Fixed `addXMLElement()` helper so it always uses the correct owner DOM for the element being appended.
- Previous implementation could reuse the wrong DOM instance (`$tasksDom` while building `users.xml`), causing `Wrong Document Error`.
- Updated helper to:
  - determine `DOMDocument` from the provided parent element's `ownerDocument`
  - fall back to the currently loaded tasks/users DOM only if needed
  - create a text node with `createTextNode()` and append it to the new element
- This resolves XML rebuild failures when syncing all users to `users.xml`.

### `database_integrity_check.php`

- Improved admin access handling for browser requests.
- Redirects unauthenticated browser users to `login.html` instead of returning raw `Unauthorized` JSON.
- Returns a clear JSON `Unauthorized` message for AJAX clients or when admin access is required.
- This makes the integrity checker easier to use from the browser and improves error clarity.

## Verification

- `run_sync.php` now completes successfully.
- `tasks.xml` and `users.xml` are rebuilt from the database.
- `users.xml` now contains synced user account entries.

### `login.html`

- Reworked the password field into a dedicated `.password-container` with a compact toggle design.
- Added an inline show/hide password toggle button using SVG eye icons instead of emojis.
- The toggle button now sits inside the input box, aligned to the right, and does not overlap typed text.
- Added polished hover/focus styling and instant visibility switching without delay.
- Ensured the password container is kept narrower and more professional, matching the requested 20% width guidance.

### `script.js`

- Reduced per-task event listeners by using event delegation on `#taskList`.
- Removed duplicate edit-form submit registration and simplified form handling.
- Replaced repeated DOM updates with `DocumentFragment` batching for task list rendering.
- Limited notification queue size to avoid long-lived DOM buildup.
- Fixed `loadTasks()` so a `401` response cleans up `loading` and `state.isLoading` before redirecting.

## Notes

- This patch focuses on XML backup synchronization and CLI rebuild reliability.
- No functional changes were made to task creation, editing, or UI behavior in this patch summary.
