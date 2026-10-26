Better Downloads 0.1.0-dev8j
- Adds a visible side/bottom border around the Downloads admin list body and Edit Download form body.
- Header/body join remains seamless with no doubled top border.
- No public Downloads or Better Portal presentation changes.

Better Downloads 0.1.0-dev8i — Stage 5D Better Tags Integration Hooks

- Adds optional Add/Edit Download form integration slot.
- Adds optional download-saved hook.
- Adds optional public download-detail metadata slot.
- Adds optional download-deleted hook.
- Calls normal SMF integration hooks first, then safely falls back to Better Tags only when installed.
- Better Downloads remains fully usable without Better Tags.

Better Downloads 0.1.0-dev8h
- Fixes Better Storage dashboard integration status.
- Registers Better Downloads as Connected when the Better Storage API is available.
- Self-heals the integration marker at runtime so installation order does not matter.
- Clears the integration marker on uninstall.


- Adds optional Better Storage integration with no hard dependency.
- When Better Storage is installed, new download files follow the per-source Better Downloads route selected in Better Storage.
- Amazon S3 uploads are stored under the Better Downloads prefix and tracked in the central Better Storage registry.
- S3-backed downloads are delivered with short-lived signed URLs after Better Downloads performs its normal permission checks.
- Replacing or deleting a download removes the corresponding S3 object and registry record.
- If Better Storage is not installed, Better Downloads continues using its normal local server storage exactly as before.
- Existing local downloads are left in place; migration is intentionally deferred to Better Storage migration tools.
- Screenshots remain on their existing storage path in this first adapter pass.

Better Downloads 0.1.0-dev8d — Guest Catalog Visibility Fix

- Fixes browse-only guests seeing an empty Downloads page when categories were restricted to membergroups.
- When “Allow guests to browse, but require login to download” is enabled, guests can now see all enabled categories and all approved/enabled download listings and detail pages.
- Category membergroup restrictions continue to apply normally to registered members.
- The actual file remains protected: guests still receive “Login to Download,” and direct download URLs redirect to SMF login before counters, redirects, or file delivery.
- No database schema changes.

Better Downloads 0.1.0-dev8c — Guest Browse Setting Visibility Fix

- Adds a General setting: “Allow guests to browse, but require login to download.”
- Enabled by default on fresh installs and upgrades where the setting does not yet exist.
- Guests can browse categories, item details, descriptions and screenshots even if the guest View Downloads permission was not previously granted.
- Download buttons become “Login to Download” for guests.
- Direct download URLs are protected server-side and send browse-only guests to the SMF login page before any counter increment, external redirect or file delivery occurs.
- Registered-member and admin download permissions continue to use the existing Better Downloads permission model.
- Better Portal/integration API now reports guest download capability consistently through better_downloads_can_download().
- No database schema changes.

Better Downloads 0.1.0-dev8 — Portal Submission API

Adds a permission-aware member-facing submission API for Better Portal. Better Downloads remains authoritative for category visibility, file validation/storage, screenshot storage, approval policy, and download records. Admins may submit through integrations; member submissions still require the master switch, allowed membergroup, and Submit Downloads permission.

Better Downloads 0.1.0-dev6c — Menu Icon Size & Padding Polish

Standalone downloads manager for SMF 2.1.x.

New in dev5:
- Added a master “Allow members to submit downloads” setting. It defaults OFF on fresh installs.
- Added membergroup selection for the future public submission workflow, including a Regular Members option.
- Added “Require approval for member submissions” directly under the submission controls.
- Added one authoritative better_downloads_member_can_submit() capability check for future submission screens/API use.
- The master switch, selected group audience, and existing SMF Submit downloads permission must all allow the member before submissions are permitted.
- Admin-managed downloads remain available regardless of the community-submission switch.
- Fresh installs no longer grant Submit downloads/Edit own downloads to Regular Members by default.
- No database schema changes. Existing downloads, categories and screenshot galleries are preserved.

Still included from dev4:
- Native SMF admin subsection tabs and unified admin typography.
- Multi-screenshot gallery with uploads, external URLs, cover selection, ordering and removal.
- Polished public cards/detail pages and Better Portal-ready read APIs.


0.1.0-dev6
- Adds a dedicated Downloads icon to the SMF main menu.
- Keeps the icon scoped to Better Downloads and installs it with the package.


0.1.0-dev6a
- Replaces the generic tray-arrow menu icon with a package/download icon designed to better match SMF's existing navigation icon style.
- Keeps the existing menu spacing and Better Downloads behavior unchanged.


0.1.0-dev6b
- Replaces the package/blob-style menu icon with the approved glossy cloud + down-arrow icon.
- Keeps the icon at the existing 16x16 SMF menu footprint so alignment and spacing remain unchanged.
- No behavior or database changes.


0.1.0-dev6c
- Uses the approved bolder glossy cloud + down-arrow artwork.
- Crops excess transparent padding from the source artwork so the symbol fills its menu slot.
- Increases the menu icon canvas from 16x16 to 18x18 for closer visual weight with SMF's other main-menu icons.
- No behavior or database changes.


Better Downloads 0.1.0-dev7 — Portal Interactive API
- Adds read-only public integration APIs for one visible download, its visible screenshot gallery, and category counts.
- Better Downloads remains authoritative for category/membergroup visibility, download permission, screenshot delivery, and download counting.
- No database schema changes.
- Intended to power Better Portal dev31's full interactive Downloads Browser without Portal querying Better Downloads tables directly.


dev8c
- Fixes the guest browse/login-to-download setting being hidden when a custom/generated theme contains a stale BetterDownloads.template.php.
- Admin Settings now render through a runtime template supplied by the MOD source, so new settings cannot be masked by old theme-local template copies.
- Adds language fallbacks for the guest browse controls and Login to Download label.
- Keeps SMF 2.1.x package compatibility through 2.1.99, including SMF 2.1.8.


0.1.0-dev8e
- Made download/category/detail/admin cards theme-aware.
- Uses Theme Lab --tl-surface, --tl-surface-alt, --tl-border and --tl-text variables when available.
- Removed hard-coded light card surfaces so dark/custom themes inherit correctly.
