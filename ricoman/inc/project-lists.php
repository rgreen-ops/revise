<?php
/**
 * Saved project lists for logged-in customers.
 *
 * Account holders keep several named, renamable project lists on their account,
 * add products to them, set quantities, and download a "project pack" (a ZIP of
 * every product's datasheet / instructions / IES-LDT files plus an index).
 * Guests keep the lightweight browser-only list in my-project.js.
 *
 * Storage: user meta `_ricoman_projects` — a list of
 *   [ id, name, created, items => [ [ id => product_id, qty => n ], … ] ]
 * and `_ricoman_active_project` for the one the add-button targets.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ----------------------------------------------------------------- data store */

function ricoman_new_project_record( $name ) {
	return array(
		'id'      => 'p' . substr( uniqid( '', true ), -10 ),
		'name'    => $name ? $name : __( 'Untitled project', 'ricoman' ),
		'created' => time(),
		'items'   => array(),
	);
}

/** All of a user's projects (creating a default the first time). */
function ricoman_get_projects( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	if ( ! $user_id ) {
		return array();
	}
	$projects = get_user_meta( $user_id, '_ricoman_projects', true );
	if ( ! is_array( $projects ) || ! $projects ) {
		$projects = array( ricoman_new_project_record( __( 'My Project', 'ricoman' ) ) );
		update_user_meta( $user_id, '_ricoman_projects', $projects );
	}
	return $projects;
}

function ricoman_save_projects( $user_id, $projects ) {
	update_user_meta( (int) $user_id, '_ricoman_projects', array_values( $projects ) );
}

function ricoman_active_project_id( $user_id = 0 ) {
	$user_id  = $user_id ? (int) $user_id : get_current_user_id();
	$projects = ricoman_get_projects( $user_id );
	$active   = (string) get_user_meta( $user_id, '_ricoman_active_project', true );
	foreach ( $projects as $p ) {
		if ( $p['id'] === $active ) {
			return $active;
		}
	}
	return $projects ? $projects[0]['id'] : '';
}

/** Total item quantity in a user's active project (header badge). */
function ricoman_active_project_count( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	$active  = ricoman_active_project_id( $user_id );
	foreach ( ricoman_get_projects( $user_id ) as $p ) {
		if ( $p['id'] === $active ) {
			$n = 0;
			foreach ( $p['items'] as $it ) {
				$n += max( 1, (int) $it['qty'] );
			}
			return $n;
		}
	}
	return 0;
}

/* --------------------------------------------------------------- mutations */

/** Apply one op to the user's projects. */
function ricoman_projects_apply( $user_id, $op, $args ) {
	$projects = ricoman_get_projects( $user_id );
	$active   = ricoman_active_project_id( $user_id );
	$find     = function ( $id ) use ( &$projects ) {
		foreach ( $projects as $i => $p ) {
			if ( $p['id'] === $id ) {
				return $i;
			}
		}
		return -1;
	};

	switch ( $op ) {
		case 'create':
			$rec        = ricoman_new_project_record( $args['name'] );
			$projects[] = $rec;
			$active     = $rec['id'];
			break;
		case 'rename':
			$i = $find( $args['pid'] );
			if ( $i >= 0 && '' !== $args['name'] ) {
				$projects[ $i ]['name'] = $args['name'];
			}
			break;
		case 'delete':
			$i = $find( $args['pid'] );
			if ( $i >= 0 ) {
				array_splice( $projects, $i, 1 );
			}
			if ( ! $projects ) {
				$projects[] = ricoman_new_project_record( __( 'My Project', 'ricoman' ) );
			}
			$active = $projects[0]['id'];
			break;
		case 'switch':
			if ( $find( $args['pid'] ) >= 0 ) {
				$active = $args['pid'];
			}
			break;
		case 'add':
			$prod = (int) $args['product'];
			if ( $prod && 'product' === get_post_type( $prod ) ) {
				// Target a specific project, a brand-new one, or the active one.
				if ( '' !== $args['newname'] ) {
					$rec        = ricoman_new_project_record( $args['newname'] );
					$projects[] = $rec;
					$active     = $rec['id'];
				} elseif ( '' !== $args['pid'] && $find( $args['pid'] ) >= 0 ) {
					$active = $args['pid'];
				}
				$i = $find( $active );
				if ( $i < 0 ) {
					$i      = 0;
					$active = $projects[0]['id'];
				}
				$found = false;
				foreach ( $projects[ $i ]['items'] as $k => $it ) {
					if ( (int) $it['id'] === $prod ) {
						$projects[ $i ]['items'][ $k ]['qty'] = (int) $it['qty'] + 1;
						$found = true;
						break;
					}
				}
				if ( ! $found ) {
					$projects[ $i ]['items'][] = array( 'id' => $prod, 'qty' => 1 );
				}
			}
			break;
		case 'addcustom':
			// A saved custom design (e.g. a Flow+ run from the designer tool).
			if ( '' !== $args['name'] || '' !== $args['summary'] ) {
				if ( '' !== $args['pid'] && $find( $args['pid'] ) >= 0 ) {
					$active = $args['pid'];
				}
				$i = $find( $active );
				if ( $i < 0 ) {
					$i      = 0;
					$active = $projects[0]['id'];
				}
				$projects[ $i ]['items'][] = array(
					'id'      => 'c' . substr( uniqid( '', true ), -10 ),
					'custom'  => true,
					'type'    => $args['ctype'] ? $args['ctype'] : 'custom',
					'name'    => $args['name'] ? $args['name'] : __( 'Custom design', 'ricoman' ),
					'summary' => $args['summary'],
					'qty'     => 1,
				);
			}
			break;
		case 'remove':
			$i = $find( '' !== $args['pid'] ? $args['pid'] : $active );
			if ( $i >= 0 ) {
				foreach ( $projects[ $i ]['items'] as $k => $it ) {
					if ( (string) $it['id'] === (string) $args['product'] ) {
						array_splice( $projects[ $i ]['items'], $k, 1 );
						break;
					}
				}
			}
			break;
		case 'qty':
			$i = $find( '' !== $args['pid'] ? $args['pid'] : $active );
			if ( $i >= 0 ) {
				$qty = max( 1, (int) $args['qty'] );
				foreach ( $projects[ $i ]['items'] as $k => $it ) {
					if ( (string) $it['id'] === (string) $args['product'] ) {
						$projects[ $i ]['items'][ $k ]['qty'] = $qty;
						break;
					}
				}
			}
			break;
	}

	ricoman_save_projects( $user_id, $projects );
	update_user_meta( $user_id, '_ricoman_active_project', $active );
}

/* ------------------------------------------------------------------- render */

/** The logged-in projects manager (server-rendered; AJAX swaps this fragment). */
function ricoman_render_projects_manager( $user_id = 0 ) {
	$user_id  = $user_id ? (int) $user_id : get_current_user_id();
	$projects = ricoman_get_projects( $user_id );
	$active   = ricoman_active_project_id( $user_id );

	$tabs = '';
	$cur  = null;
	foreach ( $projects as $p ) {
		$on    = ( $p['id'] === $active );
		$count = 0;
		foreach ( $p['items'] as $it ) {
			$count += max( 1, (int) $it['qty'] );
		}
		$tabs .= '<button type="button" class="rm-proj-tab' . ( $on ? ' on' : '' ) . '" data-proj-switch="' . esc_attr( $p['id'] ) . '">'
			. esc_html( $p['name'] ) . ' <span>' . (int) $count . '</span></button>';
		if ( $on ) {
			$cur = $p;
		}
	}
	if ( ! $cur && $projects ) {
		$cur = $projects[0];
	}

	$rows = '';
	if ( $cur && $cur['items'] ) {
		foreach ( $cur['items'] as $it ) {
			$qty = max( 1, (int) $it['qty'] );
			// Custom saved designs (e.g. a Flow+ run from the designer).
			if ( ! empty( $it['custom'] ) ) {
				$cid     = (string) $it['id'];
				$cname   = $it['name'] ? $it['name'] : __( 'Custom design', 'ricoman' );
				$csum    = isset( $it['summary'] ) ? (string) $it['summary'] : '';
				$rows   .= '<tr><td class="rm-proj-thumb"><span class="rm-proj-custico" aria-hidden="true">✎</span></td>'
					. '<td class="rm-proj-name"><strong>' . esc_html( $cname ) . '</strong>'
					. ( $csum ? '<span class="rm-proj-sku">' . esc_html( wp_trim_words( $csum, 18 ) ) . '</span>' : '' ) . '</td>'
					. '<td class="rm-proj-qty"><input type="number" min="1" value="' . $qty . '" data-proj-qty="' . esc_attr( $cid ) . '"></td>'
					. '<td class="rm-proj-rm"><button type="button" data-proj-remove="' . esc_attr( $cid ) . '" aria-label="Remove">&times;</button></td></tr>';
				continue;
			}
			$pid = (int) $it['id'];
			if ( 'product' !== get_post_type( $pid ) ) {
				continue;
			}
			$img   = function_exists( 'ricoman_product_img' ) ? ricoman_product_img( $pid ) : get_the_post_thumbnail_url( $pid, 'thumbnail' );
			$sku   = (string) get_post_meta( $pid, '_ricoman_sku', true );
			$rows .= '<tr><td class="rm-proj-thumb">' . ( $img ? '<img src="' . esc_url( $img ) . '" alt="">' : '' ) . '</td>'
				. '<td class="rm-proj-name"><a href="' . esc_url( get_permalink( $pid ) ) . '">' . esc_html( get_the_title( $pid ) ) . '</a>'
				. ( $sku ? '<span class="rm-proj-sku">' . esc_html( $sku ) . '</span>' : '' ) . '</td>'
				. '<td class="rm-proj-qty"><input type="number" min="1" value="' . $qty . '" data-proj-qty="' . $pid . '"></td>'
				. '<td class="rm-proj-rm"><button type="button" data-proj-remove="' . $pid . '" aria-label="Remove">&times;</button></td></tr>';
		}
	}

	ob_start();
	?>
	<div class="rm-projmgr" data-active="<?php echo esc_attr( $active ); ?>">
		<div class="rm-proj-tabs">
			<?php echo $tabs; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<button type="button" class="rm-proj-new" data-proj-new><?php esc_html_e( '＋ New project', 'ricoman' ); ?></button>
		</div>
		<?php if ( $cur ) : ?>
		<div class="rm-proj-head">
			<div class="rm-proj-titlewrap">
				<input type="text" class="rm-proj-title" value="<?php echo esc_attr( $cur['name'] ); ?>" data-proj-rename aria-label="<?php esc_attr_e( 'Project name', 'ricoman' ); ?>">
				<button type="button" class="rm-proj-edit" data-proj-editname aria-label="<?php esc_attr_e( 'Rename project', 'ricoman' ); ?>" title="<?php esc_attr_e( 'Rename', 'ricoman' ); ?>">&#9998;</button>
			</div>
			<div class="rm-proj-headacts">
				<a class="btn btn-solid" href="<?php echo esc_url( add_query_arg( 'rm_pack', $cur['id'], home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Download project pack', 'ricoman' ); ?> &darr;</a>
				<button type="button" class="btn btn-line-d" data-proj-delete="<?php echo esc_attr( $cur['id'] ); ?>"><?php esc_html_e( 'Delete', 'ricoman' ); ?></button>
			</div>
		</div>
			<?php if ( $rows ) : ?>
			<table class="rm-proj-table"><tbody><?php echo $rows; // phpcs:ignore WordPress.Security.EscapeOutput ?></tbody></table>
			<p class="rm-proj-hint"><?php esc_html_e( 'The pack includes every product’s datasheet, instructions and IES/LDT files, with a product list.', 'ricoman' ); ?></p>
			<?php else : ?>
			<p class="ricoman-mp-empty"><?php esc_html_e( 'This project is empty. Browse products and choose “Add to My Project”.', 'ricoman' ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
		<p class="rm-proj-msg" hidden></p>
	</div>
	<?php
	return (string) ob_get_clean();
}

/* --------------------------------------------------------------------- AJAX */

add_action( 'wp_ajax_rm_proj', function () {
	if ( ! is_user_logged_in() || ! check_ajax_referer( 'rm_proj', 'nonce', false ) ) {
		wp_send_json_error();
	}
	$op = isset( $_POST['op'] ) ? sanitize_key( $_POST['op'] ) : '';

	// Read-only: list the user's projects (for the "add to which project?" picker).
	if ( 'list' === $op ) {
		$out = array();
		foreach ( ricoman_get_projects() as $p ) {
			$n = 0;
			foreach ( $p['items'] as $it ) {
				$n += max( 1, (int) $it['qty'] );
			}
			$out[] = array( 'id' => $p['id'], 'name' => $p['name'], 'count' => $n );
		}
		wp_send_json_success( array( 'projects' => $out, 'active' => ricoman_active_project_id() ) );
	}

	if ( ! in_array( $op, array( 'create', 'rename', 'delete', 'switch', 'add', 'addcustom', 'remove', 'qty' ), true ) ) {
		wp_send_json_error();
	}
	$args = array(
		'name'    => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
		'newname' => isset( $_POST['newname'] ) ? sanitize_text_field( wp_unslash( $_POST['newname'] ) ) : '',
		'pid'     => isset( $_POST['pid'] ) ? sanitize_text_field( wp_unslash( $_POST['pid'] ) ) : '',
		'product' => isset( $_POST['product'] ) ? sanitize_text_field( wp_unslash( $_POST['product'] ) ) : '',
		'qty'     => isset( $_POST['qty'] ) ? (int) $_POST['qty'] : 1,
		'summary' => isset( $_POST['summary'] ) ? sanitize_textarea_field( wp_unslash( $_POST['summary'] ) ) : '',
		'ctype'   => isset( $_POST['ctype'] ) ? sanitize_key( $_POST['ctype'] ) : '',
	);
	ricoman_projects_apply( get_current_user_id(), $op, $args );
	wp_send_json_success( array(
		'html'  => ricoman_render_projects_manager(),
		'count' => ricoman_active_project_count(),
	) );
} );

/* ------------------------------------------------------------------ assets */

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_user_logged_in() ) {
		return;
	}
	$src = get_theme_file_path( 'assets/js/projects.js' );
	wp_enqueue_script( 'ricoman-projects', get_theme_file_uri( 'assets/js/projects.js' ), array(), file_exists( $src ) ? (string) filemtime( $src ) : '1', true );
	wp_localize_script( 'ricoman-projects', 'rmProj', array(
		'ajax'  => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'rm_proj' ),
		'count' => ricoman_active_project_count(),
	) );
} );

/* ------------------------------------------------------------- project pack */

/** A product's downloadable files as label => absolute path. */
function ricoman_product_pack_files( $pid ) {
	$files = array();
	$add   = function ( $label, $val ) use ( &$files ) {
		$id = is_numeric( $val ) ? (int) $val : ( is_array( $val ) && ! empty( $val['ID'] ) ? (int) $val['ID'] : 0 );
		if ( ! $id && is_array( $val ) && ! empty( $val['url'] ) ) {
			$id = attachment_url_to_postid( $val['url'] );
		}
		if ( ! $id && is_string( $val ) && false !== strpos( $val, '://' ) ) {
			$id = attachment_url_to_postid( $val );
		}
		if ( ! $id ) {
			return;
		}
		$path = get_attached_file( $id );
		if ( $path && is_file( $path ) ) {
			$files[] = $path;
		}
	};
	$get = function ( $k ) use ( $pid ) { return function_exists( 'ricoman_pf_get' ) ? ricoman_pf_get( $pid, $k ) : get_post_meta( $pid, $k, true ); };

	$dls = $get( 'download_section' );
	if ( is_array( $dls ) ) {
		foreach ( $dls as $d ) {
			if ( ! is_array( $d ) ) {
				continue;
			}
			$file = null;
			foreach ( $d as $k => $v ) {
				if ( false !== strpos( strtolower( (string) $k ), 'file' ) ) {
					$file = $v;
				}
			}
			if ( $file ) {
				$add( 'doc', $file );
			}
		}
	}
	$add( 'brochure', $get( 'download_led_or_details' ) );
	$add( 'datasheet', $get( 'download_family_datasheet' ) );
	return array_values( array_unique( $files ) );
}

/** Stream a project-pack ZIP for the current user. */
/**
 * Build a ZIP entirely in PHP using the STORE method (no compression) — needs no
 * Zip extension, no zlib, no temp files. Returns the raw ZIP bytes. Each entry:
 * [ 'path' => 'in/zip/name.ext', 'file' => absolute|null, 'data' => string|null ].
 * (Datasheets are PDFs/already-compressed, so storing them is fine.)
 */
function ricoman_build_pack_zip( $entries ) {
	$local   = '';
	$central = '';
	$count   = 0;
	$offset  = 0;
	$dosdate = pack( 'v', 0 ) . pack( 'v', 0x21 ); // time 00:00, date 1980-01-01.
	foreach ( $entries as $e ) {
		if ( ! empty( $e['file'] ) ) {
			if ( ! is_file( $e['file'] ) ) {
				continue;
			}
			$data = file_get_contents( $e['file'] );
			if ( false === $data ) {
				continue;
			}
		} else {
			$data = isset( $e['data'] ) ? $e['data'] : '';
		}
		$name = str_replace( '\\', '/', ltrim( (string) $e['path'], '/' ) );
		$crc  = crc32( $data );
		$len  = strlen( $data );

		$lh      = "PK\x03\x04" . pack( 'v', 20 ) . pack( 'v', 0 ) . pack( 'v', 0 ) . $dosdate
			. pack( 'V', $crc ) . pack( 'V', $len ) . pack( 'V', $len )
			. pack( 'v', strlen( $name ) ) . pack( 'v', 0 ) . $name;
		$local  .= $lh . $data;
		$central .= "PK\x01\x02" . pack( 'v', 20 ) . pack( 'v', 20 ) . pack( 'v', 0 ) . pack( 'v', 0 ) . $dosdate
			. pack( 'V', $crc ) . pack( 'V', $len ) . pack( 'V', $len )
			. pack( 'v', strlen( $name ) ) . pack( 'v', 0 ) . pack( 'v', 0 ) . pack( 'v', 0 ) . pack( 'v', 0 )
			. pack( 'V', 0 ) . pack( 'V', $offset ) . $name;
		$offset += strlen( $lh ) + $len;
		$count++;
	}
	if ( ! $count ) {
		return false;
	}
	$eocd = "PK\x05\x06" . pack( 'v', 0 ) . pack( 'v', 0 ) . pack( 'v', $count ) . pack( 'v', $count )
		. pack( 'V', strlen( $central ) ) . pack( 'V', $offset ) . pack( 'v', 0 );
	return $local . $central . $eocd;
}

add_action( 'template_redirect', function () {
	if ( empty( $_GET['rm_pack'] ) ) {
		return;
	}
	if ( ! is_user_logged_in() ) {
		auth_redirect();
		exit;
	}
	try {
	$want = sanitize_text_field( wp_unslash( $_GET['rm_pack'] ) );
	$proj = null;
	foreach ( ricoman_get_projects() as $p ) {
		if ( $p['id'] === $want ) {
			$proj = $p;
			break;
		}
	}
	if ( ! $proj ) {
		wp_die( esc_html__( 'Project not found.', 'ricoman' ) );
	}

	$entries = array();
	$index   = 'PROJECT: ' . $proj['name'] . "\n" . str_repeat( '=', 50 ) . "\n\n";
	$n       = 0;
	$used    = array();
	$uniq    = function ( $name ) use ( &$used ) {
		$base = $name ? $name : 'item';
		$try  = $base;
		$i    = 2;
		while ( isset( $used[ strtolower( $try ) ] ) ) {
			$try = $base . ' ' . $i;
			$i++;
		}
		$used[ strtolower( $try ) ] = true;
		return $try;
	};

	foreach ( $proj['items'] as $it ) {
		if ( ! empty( $it['custom'] ) ) {
			$n++;
			$cname     = $it['name'] ? $it['name'] : __( 'Custom design', 'ricoman' );
			$qty       = max( 1, (int) $it['qty'] );
			$index    .= sprintf( "%d. %s — qty %d  [custom design]\n\n", $n, $cname, $qty );
			$folder    = $uniq( sanitize_file_name( $cname ) );
			$entries[] = array( 'path' => $folder . '/design-spec.txt', 'data' => $cname . "\n" . str_repeat( '-', 40 ) . "\n\n" . ( isset( $it['summary'] ) ? $it['summary'] : '' ) . "\n" );
			continue;
		}
		$pid = (int) $it['id'];
		if ( 'product' !== get_post_type( $pid ) ) {
			continue;
		}
		$n++;
		$qty    = max( 1, (int) $it['qty'] );
		$title  = get_the_title( $pid );
		$sku    = (string) get_post_meta( $pid, '_ricoman_sku', true );
		$index .= sprintf( "%d. %s%s — qty %d\n   %s\n", $n, $title, $sku ? " ($sku)" : '', $qty, get_permalink( $pid ) );
		$folder = $uniq( sanitize_file_name( ( $sku ? $sku . ' - ' : '' ) . $title ) );
		$files  = ricoman_product_pack_files( $pid );
		if ( $files ) {
			foreach ( $files as $path ) {
				$entries[] = array( 'path' => $folder . '/' . sanitize_file_name( basename( $path ) ), 'file' => $path );
			}
		} else {
			$index .= "   (no documents on file yet)\n";
		}
		$index .= "\n";
	}
	$index    .= "\nGenerated " . date_i18n( 'j M Y H:i' ) . ' — ' . get_bloginfo( 'name' ) . "\n";
	$entries[] = array( 'path' => '00 - Product list.txt', 'data' => $index );

	$zipdata = ricoman_build_pack_zip( $entries );
	if ( ! $zipdata ) {
		wp_die( esc_html__( 'This project has nothing to pack yet — add a product or a saved design first.', 'ricoman' ) );
	}

	// Clear any buffered output so the ZIP isn't corrupted by stray markup.
	while ( ob_get_level() ) {
		ob_end_clean();
	}
	$fname = sanitize_file_name( $proj['name'] ? $proj['name'] : 'project' ) . ' - project pack.zip';
	nocache_headers();
	header( 'Content-Type: application/zip' );
	header( 'Content-Disposition: attachment; filename="' . $fname . '"' );
	header( 'Content-Length: ' . strlen( $zipdata ) );
	echo $zipdata; // phpcs:ignore WordPress.Security.EscapeOutput -- binary ZIP.
	exit;
	} catch ( \Throwable $t ) {
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		$why = current_user_can( 'manage_options' ) ? ' [' . $t->getMessage() . ' @ ' . basename( $t->getFile() ) . ':' . $t->getLine() . ']' : '';
		wp_die( esc_html__( 'Sorry, the project pack could not be built.', 'ricoman' ) . esc_html( $why ) );
	}
} );
