=== Corinthian Admin Column Manager ===
Contributors: jeangalea
Tags: admin columns, columns, list table, custom fields, sort
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Choose, order, resize and sort the columns on every WordPress admin list screen, and save column sets per screen and role.

== Description ==

Corinthian turns the posts, pages, media, users, comments and taxonomy screens into tables you can shape around your work.

Pick the columns each screen shows, drag them into order and set their widths. Save several column sets per screen and switch between them, or give each role its own set.

Columns cover the post fields, dates, authors, taxonomies, featured images, word count and reading time, any custom field, and user, media, comment and term details.

Sort any column. Sorting runs in the database, so it stays fast on large sites.

Drag a column edge to resize it. Widths are saved for each user, so everyone can arrange the table their own way.

Wide tables scroll sideways instead of squeezing columns until they can't be read.

Developers can define column sets in PHP and keep them in version control.

On multisite, network activate Corinthian to manage the network Users and Sites screens as well.

Corinthian Pro adds smart filters and saved segments, inline and bulk editing, CSV export, conditional formatting, quick add, and columns for WooCommerce, Advanced Custom Fields, Meta Box, Pods, Yoast SEO, Rank Math, SEOPress, The Events Calendar and BuddyPress.

== Installation ==

1. Install and activate the plugin.
2. Go to Settings > Columns, pick a screen and create a set from its current columns.
3. Add, reorder and resize columns, then save. The list screen shows the set right away.

To bring over views saved by the Admin Columns plugin, run `wp corinthian import-admin-columns`. Add `--dry-run` to preview the result first.

== Frequently Asked Questions ==

= Who can change column sets? =

Users with the manage_options capability. The `corinthian_manage_capability` filter changes it.

= Where do my column widths go when I drag them? =

Dragged widths are saved for your account only. Double-click a column edge to reset it.

= Can I define column sets in code? =

Yes. Call `corinthian_register_set()` from the `corinthian_register_types` action. Sets defined in code are read-only in the admin.

== Developers ==

Corinthian is built to be extended. Column types implement `Corinthian\Columns\ColumnType`, plus `Sortable`, `Filterable` and `Editable` for the features they support, and register on the `corinthian_register_types` action.

PHP hooks: `corinthian_register_types`, `corinthian_screens`, `corinthian_booted`, `corinthian_query_sql`, `corinthian_type_description`, `corinthian_table_payload`, `corinthian_table_enqueue`, `corinthian_editor_payload`, `corinthian_editor_enqueue`, `corinthian_switcher_actions`, `corinthian_cell` and `corinthian_manage_capability`.

JavaScript hooks (`wp.hooks`): the `corinthian.table.ready` action, and the `corinthian.editor.setPanels` and `corinthian.editor.columnToggles` filters.

== Screenshots ==

1. The Posts list with a custom column set applied, sorted by word count, and the set switcher beside the list filters.
2. The Settings > Columns editor with the Editorial view set open, showing column order, widths and options.
3. Dragging a column edge on the Posts list to resize it.
4. The Users list with its own column set applied.

== Changelog ==

= 0.1.1 =
* The Admin Columns importer skips columns a site no longer has, and creates no set when none are left.
* Every column set keeps its screen's main column, so rows keep their link and row actions.

= 0.1.0 =
* First release.
