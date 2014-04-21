<?php
/**
* @package PrismJS Code Highlighter
* @author Nicolas Jonas
* @license GPL-3.0
* @link http://nextgenthemes.com/plugins/prism
* @copyright Copyright (c) 2014 Nicolas Jonas
*
* @wordpress-plugin
* Plugin Name: Prism for WP
* Plugin URI: http://nextgenthemes.com/plugins/prism
* Description: Most minimalistic yet most configurabale Prismjs integration plugin, includes shortcode for custom field content (detached)
* Version: 0.9.2
* Author: Nicolas Jonas
* Author URI: http://nicolasjonas.com
* License: GPL-3.0
* License URI: http://www.gnu.org/licenses/gpl-3.0.html
* GitHub Plugin URI: https://github.com/nextgenthemes/prism
* WordPress-Plugin-Boilerplate: v2.6.1 (Only parts of it)
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die( 'NSA spyware installed, thank you!' );
}

add_action( 'plugins_loaded', array( 'Prism', 'get_instance' ) );

class Prism {

	protected static $instance = null;

	const PRISM_VERSION = '20140418';

	private function __construct() {

		add_action( 'wp_enqueue_scripts', array( $this, 'register_styles' ), 0 );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ), 0 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_load_prism' ) );

		add_action( 'admin_head',    array( $this, 'print_admin_css' ) );
		add_action( 'media_buttons', array( $this, 'add_media_button' ), 11 );
		add_action( 'admin_footer',  array( $this, 'print_admin_javascript' ) );		

		add_shortcode( 'prism', array( $this, 'shortcode' ) );
	}

	public function register_styles() {

		$upload_dir = wp_upload_dir();

		$cssfile_dir = trailingslashit( $upload_dir['basedir'] ) . 'prism/prism.css';
		$cssfile_url = trailingslashit( $upload_dir['baseurl'] ) . 'prism/prism.css';

		if ( is_file( $cssfile_dir ) ) {

			wp_register_style( 'prism', $cssfile_url, array(), filemtime( $cssfile_dir ) );
		
		} else {
			
			wp_register_style( 'prism', plugins_url( 'prism.css', __FILE__ ), array(), self::PRISM_VERSION );
		}
	}

	public function register_scripts() {

		$upload_dir = wp_upload_dir();

		$jsfile_dir = trailingslashit( $upload_dir['basedir'] ) . 'prism/prism.js';
		$jsfile_url = trailingslashit( $upload_dir['baseurl'] ) . 'prism/prism.js';

		if ( is_file( $jsfile_dir ) ) {

			wp_register_script( 'prism', $jsfile_url, array(), filemtime( $jsfile_dir ), true );
		
		} else {
			
			wp_register_script( 'prism', plugins_url( 'prism.js', __FILE__ ), array(), self::PRISM_VERSION, true );
		}
	}

	public function maybe_load_prism() {

		global $post, $wp_query;

		$post_contents = '';

		if ( is_singular() ) {

			$post_contents = $post->post_content;

		} elseif ( defined( 'PRISM_ARCHIVE_SCAN' ) && PRISM_ARCHIVE_SCAN ) {

			$post_ids = wp_list_pluck( $wp_query->posts, 'ID' );

			foreach ( $post_ids as $post_id ) {

				$post_contents .= get_post_field( 'post_content', $post_id );
			}
		}

		if ( strpos( $post_contents, '<code class="language-' ) !== false ) {

			wp_enqueue_style( 'prism' );
			wp_enqueue_script( 'prism' );
		}
	}

	public function shortcode( $atts, $content = null ) {

		extract( shortcode_atts( 
			array(
				'field'            => false,
				'url'              => false,
				'post_id'          => false,
				//* <code>
				'language'         => 'none', 
				//* <pre>
				'id'               => false, 
				'class'            => false,
				'data_src'         => false, 
				'data_start'       => false, 
				'data_line'        => false, 
				'data_line_offset' => false,
				'data_manual'      => false,
			),
			$atts,
			'prism'
		) );

		$pre_attr = array(
			'id'               => ( $id ) ? $id : $field,
			'class'            => $class,
			'data-src'         => esc_url( $data_src ),
			'data-start'       => $data_start,
			'data-line'        => $data_line,
			'data-line-offset' => $data_line_offset,
		);

		$code_attr = array(
			'class' => "language-{$language}",
		);

		if ( $url ) {

			if ( false === filter_var( $url, FILTER_VALIDATE_URL ) ) {

				return sprintf( '<p><strong>Prism Shortcode Error:</strong> URL <code>%s</code> is invalid </p>', esc_html( $url ) );
			}

			$response = wp_remote_get( esc_url( $url ) );

			if ( is_wp_error( $response ) ) {

				return sprintf( '<p><strong>Prism Shortcode Error:</strong> could not get remote content. WP_Error message:<br>%s</p>', esc_html( $response->get_error_message() ) );
			
			} elseif( 200 != $response['response']['code'] ) {

				return sprintf( '<p><strong>Prism Shortcode Error:</strong> could not get remote content. HTTP response code %s</p>', esc_html( $response['response']['code'] ) );
			}

			wp_enqueue_style( 'prism' );
			wp_enqueue_script( 'prism' );

			return sprintf( 
				'<pre %s><code %s>%s</code></pre>',
				$this->parse_attr( $pre_attr ),
				$this->parse_attr( $code_attr ),
				esc_html( $response['body'] )
			);
		}

		if ( $data_src ) {

			wp_enqueue_style( 'prism' );
			wp_enqueue_script( 'prism' );

			$pre_attr['class'] .= " language-{$language}";

			return sprintf( '<pre %s></pre>', $this->parse_attr( $pre_attr ) );
		}

		if ( ! $field ) {

			return '<p><strong>Prism Shortcode Error:</strong> field, url, data_src is missing</p>';
		}

		global $post;

		$from_post = ( $post_id ) ? $post_id : $post->ID;

		$field_content = get_post_meta( $from_post, $field, true );

		if ( empty( $field_content ) ) {

			return '<p><strong>Prism Shortcode Error:</strong> Custom field not set or empty</p>';
		}

		wp_enqueue_style( 'prism' );
		wp_enqueue_script( 'prism' );

		return sprintf( 
			'<pre %s><code %s>%s</code></pre>',
			$this->parse_attr( $pre_attr ),
			$this->parse_attr( $code_attr ),
			esc_html( $field_content )
		);
	}
	
	public function parse_attr( $attr = array() ) {

		$out = '';

		foreach ( $attr as $key => $value ) {

			if ( 'data-manual' == $key && false !== $value ) {
				$out .= ' data-manual';
				continue;
			}

			if ( empty( $value ) ) {
				continue;
			}

			$out .= sprintf( ' %s="%s"', esc_html( $key ), esc_attr( $value ) );
		}

		return trim( $out );
	}

	public function add_media_button() {

?>
<a id="prism-shortcode" class="button add_media" title="Prism Shortcode Snippet">
	<span class="wp-media-buttons-icon prism-icon"></span> Prism
</a>
<?php

	}

	public function print_admin_css() {

?>
<style>
#prism-shortcode {
	padding-left: 1px;
}
.prism-icon:before {
	content: "\f499" !important;
}
</style>
<?php

	}

	public function print_admin_javascript() {

?>
<script>
(function ( $ ) {
	"use strict";

	$( '#prism-shortcode' ).click( function( event ) {

		event.preventDefault();

		send_to_editor( '[prism field= language=]' );
	} );

}(jQuery));
</script>
<?php

	}

	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}
}