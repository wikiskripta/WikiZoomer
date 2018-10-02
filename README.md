# WikiZoomer

Mediawiki extension.

## Description

* Version 1.0
* Extension can zoom images.
* Images for zooming must have "(zoom)" at the end of the caption
* Not available for "thumb" option.

## Installation

* Make sure you have MediaWiki 1.29+ installed.
* Download and place the extension to your /extensions/ folder.
* Add the following code to your LocalSettings.php:

```php
wfLoadExtension( 'WikiZoomer' );
```

* Extension uses non-commercial version of Magic Zoom Plus, so they website has to be linked from one of our pages.

```txt
The image zoom on this site is created by Magic Zoom Plus.
Zoom obrázků na webu WikiSkript je realizován pomocí Magic Zoom Plus.
http://www.magictoolbox.com/magiczoomplus
```

* More info about Magic Zoom Plus integration [here](http://www.magictoolbox.com/magiczoom/integration/).

## Internationalization

This extension is available in English and Czech language. For other languages, just edit files in /i18n/ folder.

## Authors and license

* [Josef Martiňák](https://www.wikiskripta.eu/w/User:Josmart)
* MIT License, Copyright (c) 2018 First Faculty of Medicine, Charles University
