=== Cookie Consent Banner ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.1.1
License: GPLv2 or later

GDPR / CNIL cookie banner that blocks Google Analytics until the visitor accepts.

== What it does ==

* Bottom banner with "Allow all cookies", "Reject all" and "Cookie settings"
  (preferences window with Necessary / Analytics / Marketing switches).
* Google Analytics - including the Google tag added by Site Kit - and other trackers
  are held back (not downloaded, not run) until the visitor allows that category.
  Patterns are editable under Settings > Cookie Banner.
* Google Consent Mode v2: default "denied", updated when the visitor chooses.
* The choice is stored in one first-party cookie (ccb_consent) for 182 days.
* Works with page caching: the page is the same for everyone, the browser unblocks.
* Texts per language: Settings > Cookie Banner > "Texts - <language>" tabs. English is
  the default and is used for any empty field. With Elementor Sheet Translator the
  banner follows the visitor's language (/, /en/, /es/...).
* [cookie_settings] shortcode: a "Cookie settings" link for the footer so visitors
  can change their choice at any time.

== Setup ==

1. Upload and activate.
2. Settings > Cookie Banner: set the privacy policy page and colours.
3. Fill in the French / Spanish texts in their tabs.
4. Add [cookie_settings] to the footer (Elementor Shortcode widget).
5. Purge the page cache.
6. Connect Google Analytics in Site Kit. Its tag is blocked automatically until consent.
* Preferences window styled as a "privacy preference centre": header with round close
  button, intro with "Learn more" link, bordered category rows ("Always active" /
  switch), three full-width buttons (Accept all / Reject all / Confirm my choices).
* Translate with a spreadsheet: Settings > Cookie Banner > "Export texts (CSV)" (one row
  per text, one column per language), fill in, "Import texts". Empty cells are skipped.
* The "Marketing" category is off by default (switch it on in Settings if needed).
