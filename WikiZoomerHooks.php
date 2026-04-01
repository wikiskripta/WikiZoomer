<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;

/**
 * Hook handlers for WikiZoomer.
 */
class WikiZoomerHooks {
	private const ZOOM_REL =
		'hint-position:tr; zoom-fade:true; zoom-fade-in-speed:1200; zoom-fade-out-speed:800; ' .
		'smoothing-speed:16; zoom-width:600px; zoom-height:300px; caption-source:#slow; ' .
		'expand-speed:1000; background-color:#666666; background-opacity:95; background-speed:1000; ' .
		'hint-text:; opacity-reverse:true; zoom-position:top; expand-effect:back; restore-speed:0; ' .
		'group:topzoom; show-title:false; expand-size:fit-screen; expand-align:screen;';

	/**
	 * Add zoom support to images whose rendered alt/title text contains "(zoom)".
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 * @return bool
	 */
	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ): bool {
		$title = $out->getTitle();
		if ( !$title || !$out->isArticle() || $title->isMainPage() ) {
			return true;
		}

		$bodyHtml = $out->getHTML();
		if ( $bodyHtml === '' || stripos( $bodyHtml, '(zoom)' ) === false ) {
			return true;
		}

		$updatedHtml = self::transformBodyHtml( $bodyHtml );
		if ( $updatedHtml !== $bodyHtml ) {
			$out->clearHTML();
			$out->addHTML( $updatedHtml );
			$out->addModules( 'ext.WikiZoomer' );
		}

		return true;
	}

	/**
	 * @param string $html
	 * @return string
	 */
	private static function transformBodyHtml( string $html ): string {
		if ( !class_exists( DOMDocument::class ) ) {
			return self::transformBodyHtmlWithRegex( $html );
		}

		$wrappedHtml = '<!DOCTYPE html><html><body><div id="wikizoomer-root">' . $html . '</div></body></html>';
		$dom = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$loaded = $dom->loadHTML(
			$wrappedHtml,
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( !$loaded ) {
			return self::transformBodyHtmlWithRegex( $html );
		}

		$xpath = new DOMXPath( $dom );
		$nodes = $xpath->query('//a[contains(concat(" ", normalize-space(@class), " "), " image ")][img]');
		if ( !$nodes || $nodes->length === 0 ) {
			return $html;
		}

		$changed = false;
		foreach ( $nodes as $node ) {
			if ( !( $node instanceof DOMElement ) ) {
				continue;
			}
			$img = $node->getElementsByTagName( 'img' )->item( 0 );
			if ( !( $img instanceof DOMElement ) ) {
				continue;
			}

			$markerText = trim( implode( ' ', array_filter( [
				$img->getAttribute( 'alt' ),
				$img->getAttribute( 'title' ),
				$node->getAttribute( 'title' ),
			] ) ) );
			if ( stripos( $markerText, '(zoom)' ) === false ) {
				continue;
			}

			$fullImageUrl = self::resolveFullImageUrlFromHref( $node->getAttribute( 'href' ) );
			if ( !$fullImageUrl ) {
				continue;
			}

			$classes = preg_split( '/\s+/', trim( $node->getAttribute( 'class' ) ) ) ?: [];
			$classes = array_values( array_unique( array_filter( array_merge( $classes, [ 'MagicZoomPlus' ] ) ) ) );
			$node->setAttribute( 'class', implode( ' ', $classes ) );
			$node->setAttribute( 'href', $fullImageUrl );
			$node->setAttribute( 'rel', self::ZOOM_REL );

			foreach ( [ $img, $node ] as $element ) {
				foreach ( [ 'alt', 'title' ] as $attribute ) {
					$value = $element->getAttribute( $attribute );
					if ( $value !== '' ) {
						$element->setAttribute( $attribute, preg_replace( '/\s*\(zoom\)/i', '', $value ) );
					}
				}
			}

			$changed = true;
		}

		if ( !$changed ) {
			return $html;
		}

		$root = $dom->getElementById( 'wikizoomer-root' );
		if ( !$root ) {
			return $html;
		}

		$result = '';
		foreach ( $root->childNodes as $child ) {
			$result .= $dom->saveHTML( $child );
		}

		return $result !== '' ? $result : $html;
	}

	/**
	 * Regex fallback for environments where DOMDocument is unavailable.
	 *
	 * @param string $html
	 * @return string
	 */
	private static function transformBodyHtmlWithRegex( string $html ): string {
		return preg_replace_callback(
			'/<a([^>]*\bclass="[^"]*\bimage\b[^"]*"[^>]*)href="([^"]+)"([^>]*)>\s*<img([^>]*)>\s*<\/a>/i',
			static function ( array $matches ): string {
				$imgAttributes = $matches[4];
				if ( !preg_match( '/\b(?:alt|title)="([^"]*\(zoom\)[^"]*)"/i', $imgAttributes . ' ' . $matches[1] . ' ' . $matches[3], $markerMatch ) ) {
					return $matches[0];
				}

				$fullImageUrl = self::resolveFullImageUrlFromHref( html_entity_decode( $matches[2], ENT_QUOTES ) );
				if ( !$fullImageUrl ) {
					return $matches[0];
				}

				$replacement = preg_replace(
					'/href="[^"]+"/i',
					'href="' . htmlspecialchars( $fullImageUrl, ENT_QUOTES ) . '"',
					$matches[0],
					1
				);
				if ( !preg_match( '/\bclass="[^"]*\bMagicZoomPlus\b/i', $replacement ) ) {
					$replacement = preg_replace(
						'/<a\b/i',
						'<a class="MagicZoomPlus" rel="' . htmlspecialchars( self::ZOOM_REL, ENT_QUOTES ) . '"',
						$replacement,
						1
					);
				}
				return preg_replace( '/\s*\(zoom\)/i', '', $replacement );
			},
			$html
		) ?? $html;
	}

	/**
	 * Resolve original file URL without issuing HTTP requests.
	 *
	 * @param string $href
	 * @return string|null
	 */
	private static function resolveFullImageUrlFromHref( string $href ): ?string {
		if ( $href === '' ) {
			return null;
		}

		$parsedUrl = parse_url( html_entity_decode( $href, ENT_QUOTES ) );
		$path = $parsedUrl['path'] ?? '';
		if ( $path === '' ) {
			return null;
		}

		$path = rawurldecode( $path );
		$path = preg_replace( '#/+#', '/', $path );
		$fileName = null;

		if ( preg_match( '#/(?:File|Image|Soubor):([^/#?]+)$#iu', $path, $matches ) ) {
			$fileName = str_replace( '_', ' ', $matches[1] );
		}

		if ( $fileName === null || $fileName === '' ) {
			return null;
		}

		$titleText = 'File:' . $fileName;
		$title = MediaWikiServices::getInstance()->getTitleFactory()->newFromText( $titleText );
		if ( !$title ) {
			return null;
		}

		$file = MediaWikiServices::getInstance()->getRepoGroup()->findFile( $title );
		return $file ? $file->getFullUrl() : null;
	}
}
