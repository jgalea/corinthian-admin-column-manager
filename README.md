<h1 align="center">Corinthian Admin Column Manager</h1>

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/LICENSE-GPL--2.0--or--later-5C9E31?style=for-the-badge" alt="License"></a>
  <a href="https://github.com/jgalea"><img src="https://img.shields.io/badge/BUILT%20BY-JEAN%20GALEA-8A2BE2?style=for-the-badge" alt="Built by"></a>
</p>

<p align="center"><strong>Choose, order, resize and sort the columns on every WordPress admin list screen, and save column sets per screen and role.</strong></p>

## Features

- Column sets for posts, pages, custom post types, media, users, comments and taxonomies, shown to everyone or to chosen roles and people
- Columns for post fields, dates, authors, taxonomies, featured images, word count, reading time, custom fields, and user, media, comment and term details
- Sorting in the database, so large sites stay fast
- Drag-to-resize columns, saved per user, and sideways scrolling for wide tables
- Column sets defined in PHP for version control
- `wp corinthian import-admin-columns` to bring over views saved by the Admin Columns plugin
- Network Users and Sites screens on multisite

[Corinthian Pro](https://github.com/jgalea/corinthian-pro) adds smart filters and saved segments, inline and bulk editing, CSV export, conditional formatting and plugin integrations.

## Requirements

- WordPress 6.9 or later
- PHP 8.1 or later

## Install

Download `corinthian-admin-column-manager.zip` from the [latest release](https://github.com/jgalea/corinthian-admin-column-manager/releases/latest) and upload it under Plugins → Add New → Upload Plugin, then go to Settings → Columns.

## Development

```sh
composer install
npm install
npm run build
bin/build.sh /path/to/empty/folder   # release zip from the committed HEAD
```

Column types implement `Corinthian\Columns\ColumnType`, plus `Sortable`, `Filterable` and `Editable` for the features they support, and register on the `corinthian_register_types` action. The readme lists every PHP and JavaScript hook.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
