# DOCUMENTATION.md — gfu_sitepackage

The gfu_sitepackage is the provider/sitepackage extension for TYPO3 13.4 LTS. It bundles base configuration, TypoScript, TSConfig, templates, assets, and a Set that can be enabled in your Site Configuration. This document explains what each file and folder does, how they interact, and how to work with or extend them.

## Quick facts

- TYPO3: 13.4 LTS
- Purpose: Site bootstrap (FE rendering, assets, backend layouts, settings)
- Set: Configuration/Sets/Gfu (constants + setup)
- Activation: Enable the Set in Site Configuration → Sets (or include TypoScript manually)
- Namespaces: PSR-4 "Gfu\\GfuSitepackage\\": "Classes/"

---

## Directory structure overview

- Classes/
  - Controller/
    - Placeholder for Extbase/PSR-15 controllers if needed later. Currently empty; no runtime logic is shipped here yet.
- composer.json
  - Declares the extension as a TYPO3 CMS extension.
  - Requires TYPO3 v13 and helhum/typo3-console.
  - Registers PSR-4 autoloading for Gfu\\GfuSitepackage\\.
- Configuration/
  - Sets/
    - Gfu/
      - constants.typoscript
      - setup.typoscript
    - A TYPO3 “Set” combining base TypoScript constants and setup. Use the Site Configuration Sets UI to activate it.
  - TCA/
    - Overrides/
      - Reserved for TCA overrides (none included yet).
  - TsConfig/
    - Page/BackendLayouts/
      - Startpage.tsconfig
      - Subpage.tsconfig
      - Footer.tsconfig
    - Backend layouts for editors in the page module.
  - TypoScript/
    - Configuration/
      - Config.typoscript
      - Page/Page.typoscript
      - Lib/lib.dynamicContent.typoscript
      - Lib/lib.logo.typoscript
- ext_emconf.php
  - Extension metadata for the Extension Manager (key, version, constraints, etc.).
- ext_tables.php
  - Entry point for low-level registration (currently typically minimal or empty; use for registration tasks when needed).
- README.md
  - Short, top-level readme (keep high-level notes here).
- Resources/
  - Private/
    - Language/
      - locallang.xlf (labels for backend/UI, e.g. backend layout titles and column labels)
    - Layouts/ — Fluid layout files (empty scaffold; used by Page.typoscript path mapping)
    - Partials/ — Fluid partials (empty scaffold; used by Page.typoscript path mapping)
    - Templates/ — Fluid templates (empty scaffold; used by Page.typoscript path mapping)
  - Public/
    - Css/ — Public styles (main.css referenced in TypoScript)
    - Icons/ — Place custom icons here if needed
    - Images/ — Logos and images (e.g., site logo SVG)
    - Js/ — Public scripts (scripts.js referenced in TypoScript)

---

## Composer metadata (composer.json)

- name/type: Declares a TYPO3 extension.
- require: 
  - "typo3/cms-core": "^13"
  - "helhum/typo3-console": "^8.2"
- autoload:
  - "Gfu\\GfuSitepackage\\": "Classes/"
- extra.typo3/cms.extension-key: gfu_sitepackage

This ensures TYPO3 auto-discovers the extension and PHP classes, and lets you use the TYPO3 Console for faster local workflows.

---

## The Set: Configuration/Sets/Gfu

TYPO3 13 introduces “Sets” as a way to ship optional configuration bundles. This extension provides one Set:

- constants.typoscript
  - Defines constants and base settings used across the site:
    - plugin.tx_gfusitepackage.settings.siteTitle
    - metaNavigationPid
    - footerNavigationPid
    - siteLogo, siteFavicon
    - startPagePid
  - Defines default view root paths for layouts/templates/partials via:
    - plugin.tx_gfusitepackage.view.layoutRootPath
    - plugin.tx_gfusitepackage.view.templateRootPath
    - plugin.tx_gfusitepackage.view.partialRootPath

- setup.typoscript
  - Imports the “Configuration/TypoScript/Configuration/*.typoscript” files:
    - Config.typoscript
    - Page/Page.typoscript
    - Lib/*.typoscript
  - Configures multi-path resolution for templates/partials/layouts via .view.templateRootPaths / .partialRootPaths / .layoutRootPaths to allow overrides by constants.
  - Notes: After changing imports, clear caches.

Activation
- Go to Site Configuration → Sets, and enable the Set from this extension.
- Alternatively, include TypoScript (constants + setup) manually via the Site module TypoScript if you prefer.

---

## TypoScript Configuration

Location: Configuration/TypoScript/Configuration/

1) Config.typoscript
- Frontend rendering options for local/dev:
  - Disables JS/CSS concatenation/compression and exception handler (useful during development).
- Adds an HTML tag class “h-100” to allow full-height layout.

2) Page/Page.typoscript
- Defines the main PAGE object (page) and the FLUIDTEMPLATE at page.10.
- Assets:
  - includeCSS:
    - Bootstrap 5 (via CDN)
    - EXT:gfu_sitepackage/Resources/Public/Css/main.css
  - includeJSFooterlibs:
    - Bootstrap bundle (via CDN)
  - includeJSFooter:
    - EXT:gfu_sitepackage/Resources/Public/Js/scripts.js
- Body/meta:
  - Adds bodyTagAdd class “d-flex flex-column h-100”
  - Sets a responsive viewport meta tag
  - shortcutIcon is taken from {$plugin.tx_gfusitepackage.settings.siteFavicon}
- Template resolving:
  - templateName is derived from the Backend Layout (pagelayout). Falls back to “Default” when none is set.
- settings passed to Fluid:
  - startPagePid, siteLogo, footerNavigationPid, metaNavigationPid, siteTitle
  - siteTitle can also be read from site settings in Fluid via siteSettings:siteTitle
- variables passed to Fluid:
  - myTemplateVar (COA), anyOtherTemplateVar (TEXT), and siteTitle from siteSettings
- DataProcessing:
  - 10: MenuProcessor for the main navigation (levels=2), with nested FilesProcessor for media per page.
  - 20: MenuProcessor for “metaNavigation” using a directory by metaNavigationPid (with FilesProcessor).
  - 30: MenuProcessor for “footerNavigation” using directory by footerNavigationPid.

Note: The mapping from Backend Layout to templateName uses the pagets__ prefix convention and UpperCamelCase normalization. Ensure your template file names in Resources/Private/Templates/Page/ align with Backend Layout identifiers (e.g., Startpage, Subpage).

3) Lib/lib.dynamicContent.typoscript
- Generic renderer for tt_content records of a given colPos, optionally from another page (content_from_pid).
- Useful in templates to render columns like:
  - Top content (colPos=1)
  - Main content (colPos=0)
  - Footer columns (custom colPos)

4) Lib/lib.logo.typoscript
- Renders the logo as an inline SVG (lib.logo), wrapped in a link to {$plugin.tx_gfusitepackage.settings.startPagePid}.
- Default SVG path: EXT:gfu_sitepackage/Resources/Public/Images/Logo/gfu-logo.svg
- Provides width/height defaults and a wrapper CSS class (gfu-logo).

---

## TSConfig: Backend Layouts

Location: Configuration/TsConfig/Page/BackendLayouts/

- Startpage.tsconfig
  - 2 rows, 1 column per row
  - Top column: colPos = 1 (“Top Content”)
  - Main column: colPos = 0 (“Main Content”)
  - Title from locallang: “Startpage / Homepage Design”

- Subpage.tsconfig
  - Same structure as Startpage; tailored for subpages
  - Top colPos = 1, Main colPos = 0
  - Title from locallang: “Subpage Design”

- Footer.tsconfig
  - 1 row, 3 columns
  - Footer columns with colPos = 2, 3, 4
  - Titles sourced from locallang: Footer Left, Center, Right

These layouts drive both the backend editor experience and, via Page.typoscript, the templateName selection logic. Use lib.dynamicContent in the corresponding Fluid templates to output content from each colPos.

---

## Language labels

- Resources/Private/Language/locallang.xlf
  - Contains labels for backend layout titles and column names:
    - startpage, subpage, column_top, column_main, footer_left, footer_center, footer_right
  - Also includes a “site_title” string for example display.

---

## Fluid templates and paths

- Resources/Private/Templates/, Partials/, Layouts/
  - Empty scaffolding to place your Page templates (e.g., Default.html, Startpage.html, Subpage.html), shared partials, and layouts.
- Paths are defined in:
  - Configuration/Sets/Gfu/setup.typoscript → plugin.tx_gfusitepackage.view.*RootPaths
  - Constants in constants.typoscript allow override in Site Configuration or environment-specific TypoScript Includes.
- Typical usage:
  - Create Templates/Page/Startpage.html to match the Backend Layout “startpage” (templateName “Startpage”).
  - Use lib.dynamicContent to render the columns by colPos.

---

## Site Settings integration

- The Set reads site settings like siteTitle via siteSettings:siteTitle and exposes it to Fluid.
- You can define and maintain site settings in your Site Configuration (e.g., settings.yaml in the site package), and consume them in templates/DataProcessors.

---

## Assets

- CSS: Resources/Public/Css/main.css
- JS: Resources/Public/Js/scripts.js
- Images: Resources/Public/Images/ (logo SVG referenced in TypoScript)
- Icons: Resources/Public/Icons/ (optional; place custom icons if needed)

These are included by Page.typoscript. For production, consider moving away from CDN or adding Subresource Integrity (SRI) attributes.

---

## Development workflow

- Apply/activate the Set
  - Site Configuration → Sets → enable the Set provided by gfu_sitepackage.
- Clear caches after changes
  - ddev exec vendor/bin/typo3 cache:flush
- Compile assets (if you add tooling)
  - Keep CSS/JS minimal here, or integrate a build pipeline (e.g., via npm/Vite) and output to Resources/Public.
- Adjust constants
  - Override constants in your site or environment setup for logo paths, PIDs, and template paths.

---

## Extending and overriding

- TypoScript:
  - Use the configured RootPaths to override templates/partials/layouts via constants without touching the extension files.
  - Include additional TypoScript files via the Set or your project TypoScript Includes.
- Backend Layouts:
  - Add new layouts by creating additional TsConfig files and referencing new identifiers (pagets__youridentifier). Provide matching Fluid templates.
- DataProcessors:
  - Add new MenuProcessor or custom processors in page.10.dataProcessing for additional variables passed to Fluid.
- TCA:
  - Place field or palette overrides into Configuration/TCA/Overrides/ as needed.

---

## Notes and suggestions

- In Page.typoscript settings, metaNavigationPid currently references the footerNavigationPid constant. If you intend to read the dedicated metaNavigationPid constant, use:
  - metaNavigationPid = {$plugin.tx_gfusitepackage.settings.metaNavigationPid}
- Consider enabling compression/concatenation and TYPO3’s contentObjectExceptionHandler in production.

---

## Troubleshooting

- Templates not found or wrong template chosen
  - Verify the page’s Backend Layout and ensure the template name (UpperCamelCase) matches a file in Templates/Page/.
- Menus empty
  - Ensure start pages exist under the configured PIDs (metaNavigationPid/footerNavigationPid) and that pages are visible in menus.
- Assets not loading
  - Check that main.css and scripts.js exist and paths in TypoScript match.
  - If using CDN, verify network access and CSP/SRI settings.

---

## Changelog hints

- Add entries in README.md or a separate CHANGELOG.md when modifying TypoScript structure, constants, or Set registrations, so integrators know what to reconfigure after an update.
