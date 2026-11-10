# AwOrderGrid

Adds extra columns to the back-office order list (`Orders > Orders`), by [Axelweb](https://axelweb.fr).

Replaces the order list customisation previously done with `ambbocustomizer`.

## Columns

Added (all filterable and sortable):

- Carrier
- Tracking number
- Customer email
- Marketplace order ID and shipping deadline, read from the `marketplace_orders` table of the
  `amazon` module (only when that table exists). Disable the same columns in the amazon module
  settings to avoid duplicates: its own filters only apply to the current page on PS 9.2.

Removed: "New client" and "Delivery" (country, France for every order).

The carrier filter matches on the carrier name, so every version of a carrier (PrestaShop creates
a new `id_carrier` on each edit) is included.

## How it works

- `actionOrderGridDefinitionModifier`: columns and filters (`src/Grid/OrderGridDefinitionModifier.php`)
- `actionOrderGridQueryBuilderModifier`: select, filter, sort (`src/Grid/OrderGridQueryModifier.php`)

Since PrestaShop 9.2 the order grid paginates order ids first, in a sub-query already rendered in
the FROM clause. When one of our filters or sorts is active, that sub-query is rebuilt from the
count query, otherwise the filter would only apply to the current page.

## Requirements

- PrestaShop 8.0+ (target: 9.2)
- PHP 8.1+
- Composer

## Installation

1. Run `composer install` in the module directory.
2. Install the module from the PrestaShop back-office.

No configuration page, no tab, no SQL table.

## Build

```bash
cd bin && ./build-zip.sh
```

## License

[Academic Free License 3.0 (AFL-3.0)](https://opensource.org/licenses/AFL-3.0)
