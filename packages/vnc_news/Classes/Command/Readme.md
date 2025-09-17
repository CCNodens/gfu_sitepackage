## HowTo Import of WXL File to News

** This script was intended to transfer all _Posts_ from WordPress to a clean TYPO3 News database without already existing records.
It will TRUNCATE TABLE tx_news_domain_model_news after parsing the import file on run.**

## What it can do
+ Imports WordPress _Posts_ from an WXR export to TYPO3 News Records
+ Links WordPress categories to matching TYPO3 category records
+ Links images from WordPress articles to news records using sys_file
+ Transforms _pubDate_ to news' _datetime_ field
+ Transforms _description_ to news' teaser field
+ Transforms _content_ to news' bodytext field

## What it cannot do
+ Supports only _Posts_ record types
+ Does not respect post status in wp:status (e.g. published)

+ Will not preserve existing news records!

## Necessary preparations
### 
+ Create export file from WordPress and upload it to your project: https://wordpress.com/support/export/
+ Download images form /wp_content/. Upload to some public dir, inside fileadmin. Keep directory structure   
  !Adapt the said structures in the method below
```
    private const DESTINATION_MAP = [
        'wp-dir' => '/wp-content/uploads/',
        'typo3-dir' => 'vnc_news'
    ];
```
+ Adapt the category matching table in Controller to TYPO3's category uids
    ```
  private const CATEGORY_MAP = [
      'Allgemein' => 1,
      'Genuss' => 2,
      'Menschen' => 3,
      'Nützlich' => 4,
      'Pflanzen' => 5,
      'Schön' => 6,
      ];
    ```
## Console Command Usage:
  typo3 vnc:import-wp-news --file="var/import/export.xml" [--pid=123] [--wpdomain=www.dingers-blog.de]
+ file: the file to import 
+ pid: the storage pid of news records (empty = pid 0)
+ wpdomain: if the bodytext contains <img src="https://www.myoldblog.com/wp_uploads ..." /> the domain name will be substituted with your current domain the pid is located

## Finalize
+ Use extension ig_slug https://github.com/internetgalerie/ig_slug
  typo3 ig_slug:update tx_news_domain_model_news
