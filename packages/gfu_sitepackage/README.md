# gfu_sitepackage

Simple TYPO3 v13 sitepackage scaffold.

# Helpful Links
## Page TS Config 
https://docs.typo3.org/m/typo3/reference-typoscript/13.4/en-us/UsingSettingTSconfig/PageTSconfig.html#page-tsconfig-on-site-level

## BE Layouts
https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Backend/BackendLayout.html

### < v13 
```
    /*
    page.10 = FLUIDTEMPLATE
    page.10 {
    file.stdWrap.cObject = CASE
    file.stdWrap.cObject {
        # https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Backend/BackendLayout.html
        # V13 Method replaces former cryptic TS notations  :-)
        #key.data = pagelayout

        # < V 13. Method
        key.field = backend_layout
        ifEmpty.data = levelfield:-2,backend_layout_next_level,slide
        ifEmpty.ifEmpty = default

        default = TEXT
        default {
            value = EXT:gfu_sitepackage/Resources/Private/Templates/Page/Subpage.html
        }

        pagets__startpage = TEXT
        pagets__startpage {
            value = EXT:gfu_sitepackage/Resources/Private/Templates/Page/Startpage.html
        }

        pagets__subpage = TEXT
        pagets__subpage {
            value = EXT:gfu_sitepackage/Resources/Private/Templates/Page/Subpage.html
        }

        pagets__footer = TEXT
        pagets__footer {
            value = EXT:gfu_sitepackage/Resources/Private/Partials/Page/Footer.html
        }
    }}
*/
```
### V 13 
```
page.10 = FLUIDTEMPLATE
page.10 {
    templateName = TEXT
    templateName {
        cObject = TEXT
        cObject {
            data = pagelayout
            required = 1
            case = uppercamelcase
            split {
                token = pagets__
                cObjNum = 1
                1.current = 1
            }
        }
        ifEmpty = Default
    }
 }
```
#### do not copy