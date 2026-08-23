# CVIP-PDF&CSV
Allows printing a WooCommerce product as PDF and Catalog as CSV

## Description
This plugin generates a PDF of a product image, gallery and description, the link is generated via shortcode. Also creates button to download Catalog.

## Installation
1. Upload the plugin files to the `/wp-content/plugins/my-wordpress-plugin` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.

## Usage
Once activated, the plugin will automatically retrieve the featured image of the current post and provide an option to generate a PDF containing that image.
### PRODUCT PDF GENERATOR
To generate a PDF of a product image, gallery and description, the link is generated via shortcode, example usage: 

```php
[cvippdf linkText="Genera PDF de este producto" generandoText="Generando PDF (Por favor acepta la descarga)" textoPie="www.YourSite.com | phone: 506 8888-8888 | Columbia Central city"]
```
### CATALOG CSV GENERATOR
To add a catalog download button on any page, use:

```php
[boton_descargar_productos]
[boton_descargar_productos linktext="Descargar inventario"]
```

The button points to a public `admin-ajax.php` endpoint (`exportar_productos_csv`, with `nopriv`) that generates a CSV of published products.

## Features
- Retrieves the featured image of a post.
- Generates a PDF document with the featured image.
- Supports SVG images via svg2pdf.js.
- Downloads a CSV catalog of products via shortcode.

## Localization
The plugin is ready for translation. The language files can be found in the `languages` directory.

## CSS Styles
Custom styles for the plugin can be found in the `assets/css/style.css` file.

## Support
For support, please open an issue on the plugin's repository.

## Changelog
* 20260822: Added public CSV catalog download via `[boton_descargar_productos]` (v1.7.0).
* 20260822: Updated jsPDF to 4.2.1 (v1.6.1).
* 20260822: Added SVG image support via svg2pdf.js (v1.6.0).
* 20250128: version and author corrected, plugin zip added.
* 20250102: updated name
