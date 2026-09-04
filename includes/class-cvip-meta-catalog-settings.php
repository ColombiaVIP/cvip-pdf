<?php
/**
 * Admin settings for the Meta catalog CSV.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CVIP_Meta_Catalog_Settings {
	const OPTION_PUBLIC_BASE = 'cvip_meta_catalog_public_base';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'update_option_' . self::OPTION_PUBLIC_BASE, array( __CLASS__, 'rebuild_now' ), 10, 0 );
		add_action( 'add_option_' . self::OPTION_PUBLIC_BASE, array( __CLASS__, 'rebuild_now' ), 10, 0 );
		add_action( 'admin_post_cvip_rebuild_meta_catalog', array( __CLASS__, 'handle_manual_rebuild' ) );
		add_action( 'woocommerce_settings_saved', array( __CLASS__, 'rebuild_after_change' ) );
		add_action( 'update_option_woocommerce_store_address', array( __CLASS__, 'rebuild_after_change' ), 10, 0 );
		add_action( 'update_option_woocommerce_store_city', array( __CLASS__, 'rebuild_after_change' ), 10, 0 );
		add_action( 'update_option_woocommerce_store_address_2', array( __CLASS__, 'rebuild_after_change' ), 10, 0 );
		add_action( 'update_option_woocommerce_store_postcode', array( __CLASS__, 'rebuild_after_change' ), 10, 0 );
		add_action( 'update_option_woocommerce_default_country', array( __CLASS__, 'rebuild_after_change' ), 10, 0 );
	}

	public static function register_menu() {
		$parent = class_exists( 'WooCommerce' ) ? 'woocommerce' : 'options-general.php';

		add_submenu_page(
			$parent,
			'Catálogo Meta CSV',
			'Catálogo Meta CSV',
			'manage_options',
			'cvip-meta-catalog',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings() {
		register_setting(
			'cvip_meta_catalog',
			self::OPTION_PUBLIC_BASE,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_public_base' ),
				'default'           => '',
			)
		);
	}

	public static function sanitize_public_base( $value ) {
		$value = trim( (string) $value );
		if ( $value === '' ) {
			return '';
		}

		$value = esc_url_raw( $value );
		return $value ? untrailingslashit( $value ) : '';
	}

	public static function rebuild_now() {
		if ( class_exists( 'CVIP_Meta_Catalog' ) ) {
			CVIP_Meta_Catalog::instance()->rebuild();
		}
	}

	public static function rebuild_after_change() {
		if ( class_exists( 'CVIP_Meta_Catalog' ) ) {
			CVIP_Meta_Catalog::instance()->schedule_rebuild();
		}
	}

	public static function handle_manual_rebuild() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}

		check_admin_referer( 'cvip_rebuild_meta_catalog' );
		self::rebuild_now();

		$parent_file = class_exists( 'WooCommerce' ) ? 'admin.php' : 'options-general.php';
		$redirect    = add_query_arg(
			array(
				'page'    => 'cvip-meta-catalog',
				'rebuilt' => '1',
			),
			admin_url( $parent_file )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$public_base     = (string) get_option( self::OPTION_PUBLIC_BASE, '' );
		$site_url        = untrailingslashit( home_url() );
		$file_url        = CVIP_Meta_Catalog::get_file_url();
		$dealer          = CVIP_Meta_Catalog::instance()->get_dealer_address_for_admin();
		$effective_base  = CVIP_Meta_Catalog::instance()->get_effective_public_base();
		$wc_general      = admin_url( 'admin.php?page=wc-settings&tab=general' );

		echo '<div class="wrap">';
		echo '<h1>Catálogo Meta CSV</h1>';

		if ( isset( $_GET['rebuilt'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>El archivo CSV se regeneró.</p></div>';
		}

		echo '<p>El archivo físico está en: <code>' . esc_html( $file_url ) . '</code></p>';
		echo '<p>Las URLs de producto e imagen en el CSV usarán: <code>' . esc_html( $effective_base ) . '</code></p>';

		echo '<form action="options.php" method="post">';
		settings_fields( 'cvip_meta_catalog' );

		echo '<h2>URL pública en el CSV</h2>';
		echo '<p>Controla las URLs de producto e imagen que ve Meta. Vacío = usar la URL actual del sitio.</p>';
		echo '<table class="form-table" role="presentation"><tr>';
		echo '<th scope="row"><label for="cvip_meta_catalog_public_base">URL pública</label></th>';
		echo '<td>';
		printf(
			'<input type="url" class="regular-text" id="%1$s" name="%1$s" value="%2$s" placeholder="%3$s" />',
			esc_attr( self::OPTION_PUBLIC_BASE ),
			esc_attr( $public_base ),
			esc_attr( $site_url )
		);
		echo '<p class="description">Déjalo vacío para usar la URL publicada del sitio (<code>' . esc_html( $site_url ) . '</code>). Si este WordPress es local y Meta debe ver producción, escribe <code>https://mobilautos.com</code>.</p>';
		echo '</td></tr></table>';

		submit_button( 'Guardar cambios' );
		echo '</form>';

		echo '<form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post" style="margin:1em 0 2em">';
		echo '<input type="hidden" name="action" value="cvip_rebuild_meta_catalog" />';
		wp_nonce_field( 'cvip_rebuild_meta_catalog' );
		submit_button( 'Regenerar CSV ahora', 'secondary', 'submit', false );
		echo '</form>';

		echo '<h2>Dirección del dealer (WooCommerce)</h2>';
		echo '<p>Ciudad, calle, departamento, país y código postal se leen de <a href="' . esc_url( $wc_general ) . '">WooCommerce → Ajustes → General</a> (dirección de la tienda). No están fijos en el plugin.</p>';
		echo '<table class="widefat striped" style="max-width:640px"><tbody>';
		$labels = array(
			'addr1'       => 'Calle (address 1)',
			'addr2'       => 'Dirección 2',
			'city'        => 'Ciudad',
			'region'      => 'Departamento / región',
			'postal_code' => 'Código postal',
			'country'     => 'País',
		);
		foreach ( $labels as $key => $label ) {
			$value = isset( $dealer[ $key ] ) && $dealer[ $key ] !== '' ? $dealer[ $key ] : '(vacío — complétalo en WooCommerce)';
			echo '<tr><th style="width:220px">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '</div>';
	}
}
