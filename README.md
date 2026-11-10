# AwOrderGrid

A modern boilerplate for PrestaShop 8 / 9 module development, by [Axelweb](https://axelweb.fr).

## Requirements

- PrestaShop 8.0+
- PHP 8.1+
- Composer

## Getting started

1. Copy this module and rename it (folder, main PHP file, class name, namespace, service IDs, route names).
2. Run `composer install` in the module directory.
3. Install the module from the PrestaShop back-office.

---

## Features

### Back-office tab

A menu tab is automatically registered in the back-office on install via the `$tabs` property in the main class.

```php
public $tabs = [
    [
        'name'              => ['en' => 'Module Base', 'fr' => 'Module Base'],
        'class_name'        => 'AwOrderGrid',
        'parent_class_name' => 'AdminParentModulesSf',
        'wording'           => 'Module Base',
        'wording_domain'    => 'Modules.Awordergrid.Admin',
    ],
];
```

- Change `name` to set the label shown in the menu.
- Change `parent_class_name` to move the tab to a different menu section (e.g. `AdminCatalog`, `AdminOrders`).
- Remove the `$tabs` property entirely if you do not need a menu entry.

The tab links to the Symfony route `awordergrid_index` (`/awordergrid/index`), handled by `AwOrderGridController`.

---

### Configuration page

The module redirects `getContent()` to a Symfony-based configuration page at `/awordergrid/configuration`.

The stack follows PrestaShop's modern form pattern:

| File | Role |
|---|---|
| `src/Form/GeneralFormType.php` | Symfony form definition (fields) |
| `src/Form/GeneralDataConfiguration.php` | Read/write values to `ps_configuration` |
| `src/Form/GeneralFormDataProvider.php` | Glue between form and data configuration |
| `src/Controller/AdminConfigurationController.php` | Renders and handles the form |
| `views/templates/admin/form.html.twig` | Twig template for the form |

A sample field `AWORDERGRID_SAMPLE_CONFIG` is included as a starting point. Add, rename, or remove fields in `GeneralFormType` and `GeneralDataConfiguration`.

---

### SQL tables

SQL scripts run automatically on install and uninstall:

- **`sql/install.php`** — table creation
- **`sql/uninstall.php`** — table deletion

Both files contain a commented-out example. Uncomment and adapt it for your own tables:

```php
$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'awordergrid_example` (
    `id_awordergrid_example` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `value` TEXT DEFAULT NULL,
    `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `date_add` DATETIME NOT NULL,
    `date_upd` DATETIME NOT NULL,
    PRIMARY KEY (`id_awordergrid_example`),
    KEY `active` (`active`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
```

If your module does not need a dedicated table, leave the arrays empty — the scripts will simply return `true`.

---

### Front-office assets

The hook `actionFrontControllerSetMedia` loads a CSS and a JS file on every front-office page:

- `views/css/awordergrid.css`
- `views/js/awordergrid.js`

Remove the hook registration in `install()` and delete `hookActionFrontControllerSetMedia()` if no front-office assets are needed.

---

### Back-office assets

Admin CSS and JS are loaded directly from the Twig template (`views/templates/admin/form.html.twig`) via the `stylesheets` and `javascripts` blocks:

- `views/css/admin/form.css`
- `views/js/admin/form.js`

---

### Symfony services (DI)

Services are declared in `config/services.yml`. The namespace prefix used throughout is `axelweb.awordergrid.*`. Update all service IDs when renaming the module.

---

### Symfony routes

Routes are defined in `config/routes.yml`:

| Route name | Path | Controller |
|---|---|---|
| `awordergrid_index` | `/awordergrid/index` | `AwOrderGridController::index` |
| `awordergrid_form_configuration` | `/awordergrid/configuration` | `AdminConfigurationController::index` |

The `_legacy_controller` and `_legacy_link` keys are required for the tab system to work correctly.

---

### Translation

The module uses PrestaShop's new translation system (`isUsingNewTranslationSystem()` returns `true`). The translation domain is `Modules.Awordergrid.Admin`.

---

### Symfony cache

The Symfony cache is cleared automatically after a successful install (`Tools::clearSf2Cache()`) to avoid route-not-found errors.

---

## Project structure

```
awordergrid/
├── awordergrid.php          # Main module class
├── composer.json
├── config/
│   ├── routes.yml            # Symfony routes
│   └── services.yml          # Symfony DI services
├── sql/
│   ├── install.php           # Table creation
│   └── uninstall.php         # Table deletion
├── src/
│   ├── Controller/
│   │   ├── AdminConfigurationController.php
│   │   └── AwOrderGridController.php
│   └── Form/
│       ├── GeneralDataConfiguration.php
│       ├── GeneralFormDataProvider.php
│       └── GeneralFormType.php
├── vendor/                   # Composer dependencies
└── views/
    ├── css/
    │   ├── admin/form.css
    │   └── awordergrid.css
    ├── js/
    │   ├── admin/form.js
    │   └── awordergrid.js
    └── templates/
        └── admin/
            ├── awordergrid.html.twig
            └── form.html.twig
```

---

## TODO

- [X] Replace all header stamp
- [X] Remove smartpack reference

---

## License

[Academic Free License 3.0 (AFL-3.0)](https://opensource.org/licenses/AFL-3.0)
