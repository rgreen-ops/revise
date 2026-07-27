<?php
/**
 * Wizard card images — a picker so the team can change the pictures on the
 * "Tell us about your project" wizard (inc/project-wizard.php) without code.
 *
 * The wizard has two image-card steps: Step 1 "What lighting solution…"
 * (Gym / Office / Other) and Step 3 "What mounting options…" (Suspended /
 * Surface mounted / Recessed). Each card's picture defaults to a theme asset;
 * this screen (Ricoman → Wizard Images) lets you choose a Media Library image
 * per card instead. Choices are stored in the ricoman_wizard_images option
 * (key => attachment ID) and applied via the ricoman_wizard_config filter, so
 * an unset card keeps its built-in default.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The wizard config groups that have picture cards. */
function ricoman_wizard_image_groups() {
	return array(
		'solution' => __( 'Step 1 — “What lighting solution are you looking for?”', 'ricoman' ),
		'mounting' => __( 'Step 3 — “What mounting options are you looking for?”', 'ricoman' ),
	);
}

/** Stable option key for one card (group + option value). */
function ricoman_wizard_img_key( $group, $value ) {
	return $group . '|' . strtolower( trim( (string) $value ) );
}

/**
 * Apply the team's chosen images over the wizard's built-in defaults.
 * Runs on the same filter the wizard reads, so both the front end and this
 * admin screen see the effective picture. A deleted/invalid attachment simply
 * falls through to the default.
 */
add_filter( 'ricoman_wizard_config', 'ricoman_wizard_apply_images', 20 );
function ricoman_wizard_apply_images( $cfg ) {
	$over = get_option( 'ricoman_wizard_images', array() );
	if ( ! is_array( $over ) || ! $over ) {
		return $cfg;
	}
	foreach ( array_keys( ricoman_wizard_image_groups() ) as $g ) {
		if ( empty( $cfg[ $g ] ) || ! is_array( $cfg[ $g ] ) ) {
			continue;
		}
		foreach ( $cfg[ $g ] as $i => $item ) {
			if ( empty( $item['v'] ) ) {
				continue;
			}
			$key = ricoman_wizard_img_key( $g, $item['v'] );
			if ( empty( $over[ $key ] ) ) {
				continue;
			}
			$url = wp_get_attachment_image_url( (int) $over[ $key ], 'large' );
			if ( $url ) {
				$cfg[ $g ][ $i ]['img'] = esc_url( $url );
			}
		}
	}
	return $cfg;
}

/** The built-in default image URL per card key (config without our override). */
function ricoman_wizard_default_images() {
	remove_filter( 'ricoman_wizard_config', 'ricoman_wizard_apply_images', 20 );
	$cfg = function_exists( 'ricoman_wizard_config' ) ? ricoman_wizard_config() : array();
	add_filter( 'ricoman_wizard_config', 'ricoman_wizard_apply_images', 20 );

	$map = array();
	foreach ( array_keys( ricoman_wizard_image_groups() ) as $g ) {
		if ( empty( $cfg[ $g ] ) || ! is_array( $cfg[ $g ] ) ) {
			continue;
		}
		foreach ( $cfg[ $g ] as $item ) {
			if ( empty( $item['v'] ) ) {
				continue;
			}
			$map[ ricoman_wizard_img_key( $g, $item['v'] ) ] = isset( $item['img'] ) ? $item['img'] : '';
		}
	}
	return $map;
}

/* ------------------------------------------------------------- admin page -- */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'ricoman-hub',
		__( 'Wizard Images', 'ricoman' ),
		__( 'Wizard Images', 'ricoman' ),
		'edit_pages',
		'ricoman-wizard-images',
		'ricoman_wizard_images_page'
	);
}, 29 );

/** Media picker JS + a little layout CSS (admin only). */
function ricoman_wizard_img_assets() {
	wp_enqueue_media();
	$js = <<<'JS'
jQuery(function($){
	$(document).on('click','.rm-wizimg-pick',function(e){
		e.preventDefault();
		var wrap=$(this).closest('.rm-wizimg');
		var frame=wp.media({title:'Choose card image',button:{text:'Use this image'},multiple:false,library:{type:'image'}});
		frame.on('select',function(){
			var a=frame.state().get('selection').first().toJSON();
			var url=(a.sizes&&a.sizes.large)?a.sizes.large.url:((a.sizes&&a.sizes.medium)?a.sizes.medium.url:a.url);
			wrap.find('.rm-wizimg-val').val(a.id);
			wrap.find('.rm-wizimg-thumb').css('background-image','url('+url+')');
			wrap.find('.rm-wizimg-reset').show();
		});
		frame.open();
	});
	$(document).on('click','.rm-wizimg-reset',function(e){
		e.preventDefault();
		var wrap=$(this).closest('.rm-wizimg');
		var def=wrap.find('.rm-wizimg-thumb').data('default')||'';
		wrap.find('.rm-wizimg-val').val('');
		wrap.find('.rm-wizimg-thumb').css('background-image', def?('url('+def+')'):'');
		$(this).hide();
	});
});
JS;
	wp_add_inline_script( 'jquery-core', $js );
	$css = '.rm-wizimg-grid{display:flex;flex-wrap:wrap;gap:18px;margin:14px 0 6px}'
		. '.rm-wizimg{width:220px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:10px}'
		. '.rm-wizimg-thumb{display:block;width:100%;aspect-ratio:3/2;border-radius:6px;background:#f0f0f1 center/cover no-repeat;margin-bottom:8px}'
		. '.rm-wizimg-cap{display:block;font-weight:600;color:#1d2327;margin-bottom:8px}'
		. '.rm-wizimg-btns{display:flex;align-items:center;gap:10px;flex-wrap:wrap;min-height:26px}';
	wp_add_inline_style( 'common', $css );
}

function ricoman_wizard_images_page() {
	if ( ! current_user_can( 'edit_pages' ) ) {
		return;
	}

	// Save.
	if ( isset( $_POST['rm_wizimg_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['rm_wizimg_nonce'] ), 'rm_wizimg' ) ) {
		$in  = isset( $_POST['rm_wizimg'] ) && is_array( $_POST['rm_wizimg'] ) ? wp_unslash( $_POST['rm_wizimg'] ) : array();
		$out = array();
		foreach ( $in as $k => $v ) {
			$id = (int) $v;
			if ( $id > 0 ) {
				$out[ sanitize_text_field( $k ) ] = $id;
			}
		}
		update_option( 'ricoman_wizard_images', $out, false );
		if ( function_exists( 'ricoman_pagecache_flush' ) ) {
			ricoman_pagecache_flush(); // so anonymous visitors see the change now, not after the cache TTL.
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Wizard images saved.', 'ricoman' ) . '</p></div>';
	}

	ricoman_wizard_img_assets();

	$cfg      = function_exists( 'ricoman_wizard_config' ) ? ricoman_wizard_config() : array(); // effective (override or default).
	$over     = get_option( 'ricoman_wizard_images', array() );
	$over     = is_array( $over ) ? $over : array();
	$defaults = ricoman_wizard_default_images();
	$groups   = ricoman_wizard_image_groups();

	echo '<div class="wrap"><h1>' . esc_html__( 'Wizard card images', 'ricoman' ) . '</h1>';
	echo '<p class="description" style="max-width:760px">' . esc_html__( 'These are the picture cards in the “Tell us about your project” wizard. Choose an image from your Media Library for each card, then Save. Leave a card unset to keep its built-in default. A landscape photo works best — each picture is shown as a wide card.', 'ricoman' ) . '</p>';

	echo '<form method="post">';
	wp_nonce_field( 'rm_wizimg', 'rm_wizimg_nonce' );

	foreach ( $groups as $g => $heading ) {
		if ( empty( $cfg[ $g ] ) || ! is_array( $cfg[ $g ] ) ) {
			continue;
		}
		echo '<h2 style="margin-top:1.4em">' . esc_html( $heading ) . '</h2>';
		echo '<div class="rm-wizimg-grid">';
		foreach ( $cfg[ $g ] as $item ) {
			$v   = isset( $item['v'] ) ? $item['v'] : '';
			$key = ricoman_wizard_img_key( $g, $v );
			$eff = isset( $item['img'] ) ? $item['img'] : '';                 // shown now (override or default).
			$def = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';       // built-in default, for Reset.
			$sid = isset( $over[ $key ] ) ? (int) $over[ $key ] : 0;          // stored attachment id (0 = default).
			echo '<div class="rm-wizimg">';
			echo '<span class="rm-wizimg-thumb" data-default="' . esc_url( $def ) . '" style="background-image:url(' . esc_url( $eff ) . ')"></span>';
			echo '<span class="rm-wizimg-cap">' . esc_html( $v ) . '</span>';
			echo '<input type="hidden" class="rm-wizimg-val" name="rm_wizimg[' . esc_attr( $key ) . ']" value="' . esc_attr( $sid ? (string) $sid : '' ) . '">';
			echo '<span class="rm-wizimg-btns">';
			echo '<button type="button" class="button button-small rm-wizimg-pick">' . esc_html__( 'Choose image', 'ricoman' ) . '</button>';
			echo '<button type="button" class="button-link rm-wizimg-reset"' . ( $sid ? '' : ' style="display:none"' ) . '>' . esc_html__( 'Reset to default', 'ricoman' ) . '</button>';
			echo '</span>';
			echo '</div>';
		}
		echo '</div>';
	}

	submit_button( __( 'Save wizard images', 'ricoman' ) );
	echo '</form></div>';
}
