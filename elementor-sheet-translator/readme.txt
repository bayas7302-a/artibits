=== Elementor Sheet Translator ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.1.1
License: GPLv2 or later

Translate an Elementor website with spreadsheets. English in column A, one column per language, import back, done.

== What it does ==

* Scans the whole site: Elementor pages, posts, headers, footers, popups, sections and
  other templates (including theme header/footer post types built with Elementor),
  block-editor / classic posts (e.g. case studies), navigation menus, site title and tagline.
* Export: one Excel workbook with one sheet per page, a ZIP with one CSV per page, a
  single CSV, or a single page from the Content screen.
* Every sheet: row 1 = "English (en) | Arabic (ar) | ...", column A = English text,
  the other columns = one language each (empty, or pre-filled with existing translations).
* Import: upload the filled .xlsx, .zip or .csv file(s). Each row is matched by its
  English text; empty cells are skipped and never delete a translation.
* Translated pages live at /ar/..., /fr/... (the default language stays at the root).
* Correct direction: <html dir="rtl" lang="ar">, WordPress/Elementor RTL stylesheets,
  body classes "rtl est-lang-ar est-dir-rtl" and optional per-language custom CSS.
* Internal links, menus and buttons keep the visitor in their language; hreflang tags for SEO.
* "Language Switcher" Elementor widget (dropdown or inline list) for your header, plus
  the shortcode [est_language_switcher].

== Quick start ==

1. Plugins > Add New > Upload Plugin, upload elementor-sheet-translator.zip and activate.
2. Sheet Translator > Languages & Settings > Add a language > Arabic.
3. Sheet Translator > Content & Export > Download export (Excel workbook).
4. Fill column B (Arabic) in every sheet. Keep any HTML tags such as <p> or <b>
   and translate only the text. Leave a cell empty to keep the English text.
5. Sheet Translator > Import, upload the file.
6. Edit your Elementor Header template, search the widget panel for "Language Switcher",
   drop it in, style it, update.
7. Visit https://your-site.com/ar/

To add another language later, add it in Languages & Settings and export again: the new
column appears in every sheet. You can also type a new header such as "French (fr)" in
the sheet; the import creates the language for you.

== Notes ==

* The same English text gets the same translation everywhere (e.g. "Contact us").
* After importing, the plugin purges WP Rocket, W3 Total Cache, WP Super Cache and
  LiteSpeed caches automatically. With other cache plugins, purge manually.
* Elementor's "Element Caching" is bypassed on translated pages so languages never mix.
* Some layouts use fixed "left/right" alignments or margins. Fix them for RTL pages with
  the language's Custom CSS box, e.g. `.est-dir-rtl .my-box { text-align: right; }`.
* If a widget text is missing from the export, add its setting key under
  "Extra Elementor setting keys to translate".
* Images: Sheet Translator > Images lets you pick a different image per language
  (image widgets, backgrounds, galleries, post images). Tick "Include image URLs" on
  export to get an "Images" sheet; on import, rows whose column A is an image URL are
  mapped automatically.
