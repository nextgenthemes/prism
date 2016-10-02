<?php
/**
* @package   Prism Syntax Highlighter for WordPress
* @author    Nicolas Jonas
* @license   GPL-3.0
* @link      http://nextgenthemes.com/plugins/prism
* @copyright Copyright (c) 2015 Nicolas Jonas
*
* @wordpress-plugin
* Plugin Name:       Prism Syntax Highlighter for WordPress
* Plugin URI:        http://nextgenthemes.com/plugins/prism
* Description:       Most minimalistic yet most configurabale Prismjs integration plugin, includes shortcode for custom field content (detached)
* Version:           1.1.1
* Author:            Nicolas Jonas
* Author URI:        https://nextgenthemes.com
* License:           GPL-3.0
* License URI:       https://www.gnu.org/licenses/gpl-3.0.html
* GitHub Plugin URI: https://github.com/nextgenthemes/prism
*
* WordPress-Plugin-Boilerplate: v2.6.1 (Only parts of it)
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die( 'NSA spyware installed, thank you!' );
}

add_action( 'plugins_loaded', array( 'Prism', 'get_instance' ) );

class Prism {

	protected static $instance = null;

	const PRISM_VERSION = '2016-10-02';

	private function __construct() {

		add_action( 'wp_enqueue_scripts', array( $this, 'register_styles' ), 0 );
		add_filter( 'mce_css', array( $this, 'plugin_editor_style' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ), 0 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_load_prism' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_load_prism' ) );

		add_action( 'admin_head',    array( $this, 'print_admin_css' ) );
		add_action( 'media_buttons', array( $this, 'add_media_button' ), 11 );
		add_action( 'admin_footer',  array( $this, 'print_admin_javascript' ) );

		add_shortcode( 'prism', array( $this, 'shortcode' ) );

		add_filter( 'mce_buttons_2', array( $this, 'mce_add_buttons' ) );
		add_filter( 'tiny_mce_before_init', array( $this, 'filter_tiny_mce_before_init' ) );
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

	public function plugin_editor_style( $mce_css ){

			$mce_css .= ', ' . plugins_url( 'prism.css', __FILE__ );
			return $mce_css;
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

		} else {

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

	public function admin_load_prism() {

		#wp_enqueue_style( 'prism' );
		$this->register_scripts();
		wp_enqueue_script( 'prism' );
	}

	public function shortcode( $atts, $content = null ) {

		$pairs = array(
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
		);

		$atts = shortcode_atts( $pairs, $atts, 'prism' );

		$pre_attr = array(
			'id'               => ( $atts['id'] ) ? $atts['id'] : $atts['field'],
			'class'            => $atts['class'],
			'data-src'         => esc_url( $atts['data_src'] ),
			'data-start'       => $atts['data_start'],
			'data-line'        => $atts['data_line'],
			'data-line-offset' => $atts['data_line_offset'],
			'data-manual'      => $atts['data_manual'],
		);

		$code_attr = array(
			'class' => 'language-' . $atts['language'],
		);

		if ( $atts['url'] ) {

			if ( false === filter_var( $atts['url'], FILTER_VALIDATE_URL ) ) {

				return sprintf( '<p><strong>Prism Shortcode Error:</strong> URL <code>%s</code> is invalid </p>', esc_html( $atts['url'] ) );
			}

			$response = wp_remote_get( esc_url( $atts['url'] ) );

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

		if ( $atts['data_src'] ) {

			wp_enqueue_style( 'prism' );
			wp_enqueue_script( 'prism' );

			$pre_attr['class'] .= " language-{$atts['language']}";

			return sprintf( '<pre %s></pre>', $this->parse_attr( $pre_attr ) );
		}

		if ( ! $atts['field'] ) {

			return '<p><strong>Prism Shortcode Error:</strong> field, url, data_src is missing</p>';
		}

		global $post;

		$from_post = ( $atts['post_id'] ) ? $atts['post_id'] : $post->ID;

		$field_content = get_post_meta( $from_post, $atts['field'], true );

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

		send_to_editor( '[prism field="" language=""]' );
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

	function filter_tiny_mce_before_init( $settings ) {

		$languages = array(
			'Bash',
			'CSS',
			'JavaScript',
			'Markup',
			'PHP',
			'SCSS',
		);

		$style_formats[] = array(
			'title'    => "<code>",
			'inline'   => 'code'
		);

		foreach ( $languages as $lang ) {

			$lang_lowercase = strtolower( $lang );

			$style_formats[] = array(
				'title'    => "$lang <pre>",
				'block'    => 'pre',
				'classes'  => "language-$lang_lowercase",
			);
			$style_formats[] = array(
				'title'    => "$lang <code>",
				'inline'   => 'code',
				'classes'  => "language-$lang_lowercase",
			);
		}

	  $settings['style_formats'] = json_encode( $style_formats );
		$settings['style_formats_merge'] = false;
		#$settings['block_formats'] = 'Paragraph=p;Heading 3=h3;Heading 4=h4;CSS Code=pre';

	  return $settings;
	}

	function mce_add_buttons( $buttons ) {
    array_splice( $buttons, 1, 0, 'styleselect' );
    return $buttons;
	}
}
