<?php

/**
 * All hooked functions used by WikiZoomer
 * @ingroup Extensions
 * @author Josef Martiňák
 */

class WikiZoomerHooks {

	/**
	 * Make image zoomable
	 * @param $out OutputPage: instance of OutputPage
	 * @param $skin Skin: instance of Skin 
	 */
	public static function zoomIt( &$out,&$skin ) {

		// If not article, return
		$title = $out->getTitle();
		if (!$out->isArticle() || $title->isMainPage() ) {
			return true;
		}

		// get list of <a href="" ...><img src="" ... /></a> and add magiczoom parameters
		//<a href="/w/Soubor:Z_9-14_AML_20x_oznaceno.jpg" class="image" title="AML (zoom)"><img alt="AML (zoom)" src="/images/thumb/1/1e/Z_9-14_AML_20x_oznaceno.jpg/200px-Z_9-14_AML_20x_oznaceno.jpg" width="200" height="150" srcset="/images/thumb/1/1e/Z_9-14_AML_20x_oznaceno.jpg/300px-Z_9-14_AML_20x_oznaceno.jpg 1.5x, /images/thumb/1/1e/Z_9-14_AML_20x_oznaceno.jpg/400px-Z_9-14_AML_20x_oznaceno.jpg 2x" data-file-width="2560" data-file-height="1920" /></a>
		$tmp =  preg_replace_callback( '/\<a.*?href="([^"]*)"[^\>]*\>\<img.*?alt="([^"]*)".*?\/\>\<\/a\>/', 'self::insertZoomer', $out->mBodytext );
		if( $tmp ) {
			$out->addModules('ext.WikiZoomer');
			$out->mBodytext = $tmp;
		}

		return true;
	}

	/* callback function */
	public static function insertZoomer( $matches ) {
		$zoomplus = true;
		global $wgServer, $wgScriptPath, $zoomplus;
		$output = $matches[0];
		if( stripos( $matches[2], '(zoom)' ) !== false ) {
			// get image name
			$arr = preg_split( "/File:|Soubor:|Image:/i", $matches[1] );
			$imageName = $arr[1];
			// get url of full sized image

			// get wiki path
			if( strpos($wgServer,"http") === false && strpos($wgServer,"https") === false ) {
				$wikipath = "https:" . $wgServer;
			}
			else $wikipath = $wgServer;

			if( !empty( $wgScriptPath ) ) {
				$wikipath .= "/$wgScriptPath";
			}
			$iinfo = file_get_contents( "$wikipath/api.php?action=query&titles=File:$imageName&prop=imageinfo&iiprop=url&format=json" );

			if( preg_match( "/\"url\":\"([^\"]*)\"/",$iinfo, $m ) ) {
				// apply magiczoom
				$bigPhoto = $m[1];
				if($zoomplus) {
					//$output = preg_replace( '/href="[^"]*"/', 'class="MagicZoomPlus" rel="hint-position:tr; zoom-position:top; zoom-width:600px; zoom-height:400px; zoom-align:center; zoom-fade:true; zoom-fade-in-speed:800; smoothing-speed:17; expand-effect:back; restore-speed:0; group:topzoom; show-title: false; expand-size: fit-screen; expand-align: screen;" href="'.$bigPhoto.'"', $matches[0] );
					$output = preg_replace( '/href="[^"]*"/', 'class="MagicZoomPlus" rel="hint-position:tr; zoom-fade:true; zoom-fade-in-speed:1200; zoom-fade-out-speed:800; smoothing-speed:16; zoom-width:600px; zoom-height:300px; caption-source:#slow; expand-speed:1000; background-color:#666666; background-opacity:95; background-speed:1000; hint-text:; opacity-reverse:true; group:slow; zoom-position:top; expand-effect:back; restore-speed:0; group:topzoom; show-title: false; expand-size: fit-screen; expand-align: screen;" href="'.$bigPhoto.'"', $matches[0] );
				}
				else {
					//$output = preg_replace( '/href="[^"]*"/', 'class="MagicZoom" rel="zoom-width:100%; zoom-height:100%" href="'.$bigPhoto.'"', $matches[0] );
					$output = preg_replace( '/href="[^"]*"/', 'class="MagicZoom" rel="zoom-width:800px; zoom-height:600px;" href="'.$bigPhoto.'"', $matches[0] );
				}
				// expand-size: original zobrazí celý obrázek
				$output = preg_replace( '/ \(zoom\)/i', '', $output );
			}
		}
		return $output;
	}
}