## HowTo Import of WXL File to News

** This script was intended to transfer all _Posts_ from WordPress to a clean TYPO3 News database without already
existing records.
It will TRUNCATE TABLE tx_news_domain_model_news after parsing the import file on run.**

## What it can do

+ Imports WordPress _Posts_ from an WXR export to TYPO3 News Records
+ Links WordPress categories to matching TYPO3 category records
+ Links images from WordPress articles to news records using sys_file
+ Transforms _pubDate_ to news' _datetime_ field
+ Transforms _description_ to news' teaser field
+ Transforms _content_ to news' bodytext field
+ Reads system categories from sys_categories and matches by title

## What it cannot do

+ Supports only _Posts_ record types
+ Does not respect post status in wp:status (e.g. published)
+ Does not support for TYPO3 categories
+ Will not preserve existing news records!

## Necessary preparations

+ Create export file from WordPress and upload it to your project: https://wordpress.com/support/export/
+ Make sure all categories exist in TYPO3 and have unique titles ()
+ Create News storage Folder in TYPO3 or use existing one
+ Download images form /wp_content/. Upload to some public dir, below fileadmin. Keep sub-directory structure

## Console Command Usage:

`````
typo3 vnc:import-wp-news --file="var/export/beitraege.xml" --sourcefolder=wp_content/uploads --targetfolder=new_blog [--pid=123] [--wpdomain=www.myoldblog.com]
`````

+ file: the file to import
+ pid: the storage pid of news records (empty = pid 0)
+ wpdomain: the domain name of the WordPress Site
+ sourcefolder: the WordPress folder where the images reside
+ targetfolder: the TYPO3 folder the images will be copied to

### Examples:

The HTML source of the blog post cointains all information needed:
<img src="https://www.myoldblog.com/wp_content/uploads/path/to/image.jpg" /> the parameter must be:
--sourcefolder=wp_content/uploads
The Folder structure in TYPO3: fileadmin/new_blog/path/to/ the parameter must be: --targetfolder=new_blog

## Finalize

+ Use extension ig_slug https://github.com/internetgalerie/ig_slug
  typo3 ig_slug:update tx_news_domain_model_news
