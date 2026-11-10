# AwOrderGrid

Adds extra columns to the back-office order list (`Orders > Orders`), by [Axelweb](https://axelweb.fr).

Replaces the order list customisation previously done with `ambbocustomizer`.

## Columns

- Carrier
- Tracking number
- Customer email

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
