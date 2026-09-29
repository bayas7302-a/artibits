# Hello Elementor Child – theme files

Upload the contents of this folder to `wp-content/themes/hello-elementor-child/`
(keep your existing `style.css` and `screenshot.png`).

```
hello-elementor-child/
├── functions.php            ← parent theme styles, AED symbol, loads everything below
├── fanvil-woocommerce.php   ← page template: shop, categories, promotions, search, product, events
├── soharon-emails.php       ← branded WooCommerce emails + tracking in emails
└── inc/
    ├── brand.php            ← brand colours, icons, Poppins font (needed by the others)
    ├── cart.php             ← [soharon_custom_cart]
    ├── checkout-account.php ← [soharon_checkout], thank-you page, [soharon_my_account]
    ├── order-tracking.php   ← WooCommerce → Order Tracking, courier box, Track order tab, [soharon_track_order]
    ├── product-links.php    ← Product URL / Data Sheet fields
    ├── header.php           ← [fanvil_site_header]
    ├── site-templates.php   ← header/footer/templates without Elementor Pro, sticky footer
    ├── shop.php             ← [fanvil_shop], categories, tags, search, Promotions
    ├── product-page.php     ← [fanvil_product]
    ├── home.php             ← [fanvil_categories], [fanvil_products]
    └── events.php           ← Events post type, /events/, [fanvil_events]
```

All files in `inc/` are required – `functions.php` loads them in the order above.
To change one feature, edit (and upload) only its file.
