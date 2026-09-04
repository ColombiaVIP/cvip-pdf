<?php
/**
 * Builds a physical Meta vehicle-catalog CSV on disk.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CVIP_Meta_Catalog {
	const CRON_HOOK     = 'cvip_rebuild_meta_catalog';
	const CRON_DEBOUNCE = 'cvip_rebuild_meta_catalog_soon';
	const FOLDER        = 'cvip-feeds';
	const FILENAME      = 'catalog_vehicles.csv';

	/**
	 * @var self|null
	 */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function activate() {
		self::instance()->schedule_cron();
		self::instance()->rebuild();
	}

	public static function deactivate() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}

		wp_clear_scheduled_hook( self::CRON_DEBOUNCE );
	}

	private function __construct() {
		add_action( 'init', array( $this, 'schedule_cron' ) );
		add_action( self::CRON_HOOK, array( $this, 'rebuild' ) );
		add_action( self::CRON_DEBOUNCE, array( $this, 'rebuild' ) );
		add_action( 'init', array( $this, 'maybe_ensure_file' ), 20 );

		add_action( 'woocommerce_new_product', array( $this, 'schedule_rebuild' ) );
		add_action( 'woocommerce_update_product', array( $this, 'schedule_rebuild' ) );
		add_action( 'trashed_post', array( $this, 'maybe_schedule_on_post' ) );
		add_action( 'untrashed_post', array( $this, 'maybe_schedule_on_post' ) );
		add_action( 'deleted_post', array( $this, 'maybe_schedule_on_post' ) );
	}

	public function schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, 'hourly', self::CRON_HOOK );
		}
	}

	public function maybe_ensure_file() {
		$path = $this->get_file_path();
		if ( ! $path ) {
			return;
		}

		if ( ! file_exists( $path ) || $this->file_missing_required_columns( $path ) ) {
			$this->rebuild();
		}
	}

	private function file_missing_required_columns( $path ) {
		$handle = fopen( $path, 'r' );
		if ( ! $handle ) {
			return true;
		}

		$header = (string) fgets( $handle );
		fclose( $handle );

		return strpos( $header, 'street_address' ) === false;
	}

	public function maybe_schedule_on_post( $post_id ) {
		if ( get_post_type( $post_id ) === 'product' ) {
			$this->schedule_rebuild();
		}
	}

	public function schedule_rebuild() {
		if ( ! wp_next_scheduled( self::CRON_DEBOUNCE ) ) {
			wp_schedule_single_event( time() + 30, self::CRON_DEBOUNCE );
		}
	}

	public function get_file_path() {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return '';
		}

		return trailingslashit( $uploads['basedir'] ) . self::FOLDER . '/' . self::FILENAME;
	}

	public static function get_file_url() {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return '';
		}

		return trailingslashit( $uploads['baseurl'] ) . self::FOLDER . '/' . self::FILENAME;
	}

	public function rebuild() {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return false;
		}

		$dir = dirname( $this->get_file_path() );
		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		$index = $dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}

		$path = $this->get_file_path();
		$temp = $path . '.tmp';

		$handle = fopen( $temp, 'w' );
		if ( ! $handle ) {
			return false;
		}

		$headers = $this->get_headers();
		fputcsv( $handle, $headers );

		$products = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => -1,
			)
		);

		foreach ( $products as $product ) {
			$row = $this->map_product_row( $product, $headers );
			$row = apply_filters( 'cvip_meta_catalog_row', $row, $product );
			fputcsv( $handle, $row );
		}

		fclose( $handle );

		$replaced = rename( $temp, $path );
		if ( ! $replaced ) {
			@unlink( $path );
			$replaced = rename( $temp, $path );
		}

		return $replaced;
	}

	private function get_headers() {
		return array(
			'vehicle_id',
			'title',
			'description',
			'availability',
			'condition',
			'price',
			'sale_price',
			'image[0].url',
			'image[0].tag[0]',
			'video[0].url',
			'video[0].tag[0]',
			'url',
			'dealer_communication_channel',
			'previous_price',
			'id',
			'brand',
			'link',
			'image_link',
			'street_address',
			'city',
			'region',
			'country',
			'postal_code',
			'zip',
			'address.addr1',
			'address.addr2',
			'address.addr3',
			'address.city',
			'address.city_id',
			'address.region',
			'address.postal_code',
			'address.country',
			'address.unit_number',
			'latitude',
			'longitude',
			'neighborhood[0]',
			'make',
			'model',
			'features[0].value',
			'features[0].type',
			'features[1].value',
			'features[1].type',
			'features[2].value',
			'features[2].type',
			'vehicle_specifications[0].type',
			'vehicle_specifications[0].units',
			'vehicle_specifications[0].value',
			'custom_label_0',
			'custom_label_1',
			'custom_label_2',
			'custom_label_3',
			'custom_label_4',
			'custom_number_0',
			'custom_number_1',
			'custom_number_2',
			'custom_number_3',
			'custom_number_4',
			'msrp',
			'availability_circle_radius',
			'availability_circle_radius_unit',
			'applink.android_app_name',
			'applink.android_package',
			'applink.android_url',
			'applink.ios_app_name',
			'applink.ios_app_store_id',
			'applink.ios_url',
			'applink.ipad_app_name',
			'applink.ipad_app_store_id',
			'applink.ipad_url',
			'applink.iphone_app_name',
			'applink.iphone_app_store_id',
			'applink.iphone_url',
			'applink.windows_phone_app_id',
			'applink.windows_phone_app_name',
			'applink.windows_phone_url',
			'year',
			'fuel_type',
			'drivetrain',
			'transmission',
			'body_style',
			'exterior_color',
			'interior_color',
			'trim',
			'vin',
			'state_of_vehicle',
			'dealer_id',
			'dealer_name',
			'mileage.unit',
			'mileage.value',
			'vehicle_type',
			'dealer_privacy_policy_url',
			'dealer_url',
			'days_on_lot',
			'carfax_dealership_id',
			'engine_size',
			'horse_power',
			'stock_number',
			'vehicle_registration_plate',
			'legal_disclosure_impressum_url',
			'energy_efficiency_class_eu',
			'vehicle_finance_types[0]',
			'product_tags[0]',
			'product_tags[1]',
			'product_priority_0',
			'product_priority_1',
			'product_priority_2',
			'product_priority_3',
			'product_priority_4',
		);
	}

	private function map_product_row( $product, $headers ) {
		$row = array_fill_keys( $headers, '' );

		$product_id = $product->get_id();
		$dealer     = $this->get_dealer_address();
		$placa      = $this->get_meta( $product_id, 'placa' );
		$ciudad     = $this->get_meta( $product_id, 'ciudad' );
		$created    = $product->get_date_created();

		$regular = $product->get_regular_price();
		$sale    = $product->get_sale_price();
		$price   = $regular !== '' ? $regular : $product->get_price();

		$image_id  = $product->get_image_id();
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';

		$description = $product->get_description();
		if ( $description === '' ) {
			$description = $product->get_short_description();
		}

		$row['vehicle_id']                 = (string) $product_id;
		$row['title']                      = $product->get_name();
		$row['description']                = $this->plain_text( $description );
		$row['availability']               = $product->is_in_stock() ? 'in stock' : 'out of stock';
		$row['condition']                  = 'used';
		$row['price']                      = $this->format_price( $price );
		$row['sale_price']                 = $product->is_on_sale() ? $this->format_price( $sale ) : '';
		$row['image[0].url']               = $this->public_catalog_url( $image_url );
		$row['url']                        = $this->public_catalog_url( $product->get_permalink() );
		$row['id']                         = (string) $product_id;
		$row['brand']                      = $this->get_meta( $product_id, 'marca' );
		$row['link']                       = $row['url'];
		$row['image_link']                 = $row['image[0].url'];
		$row['street_address']             = $dealer['addr1'];
		$row['city']                       = $dealer['city'];
		$row['region']                     = $dealer['region'];
		$row['country']                    = $dealer['country'];
		$row['postal_code']                = $dealer['postal_code'];
		$row['zip']                        = $dealer['postal_code'];
		$row['address.addr1']              = $dealer['addr1'];
		$row['address.addr2']              = $dealer['addr2'];
		$row['address.city']               = $dealer['city'];
		$row['address.region']             = $dealer['region'];
		$row['address.postal_code']        = $dealer['postal_code'];
		$row['address.country']            = $dealer['country'];
		$row['make']                       = $this->get_meta( $product_id, 'marca' );
		$row['model']                      = $this->get_meta( $product_id, 'modelo' );
		$row['custom_label_0']             = $ciudad;
		$row['custom_label_1']             = $this->get_meta( $product_id, 'marca' );
		$row['year']                       = $this->get_meta( $product_id, 'ano' );
		$row['fuel_type']                  = $this->map_fuel_type( $this->get_meta( $product_id, 'tipo_combustible' ) );
		$row['drivetrain']                 = $this->map_drivetrain( $this->get_meta( $product_id, 'tipo_transmision' ) );
		$row['transmission']               = $this->map_transmission( $this->get_meta( $product_id, 'transmision' ) );
		$row['body_style']                 = $this->map_body_style( $this->get_meta( $product_id, 'tipo' ) );
		$row['exterior_color']             = $this->get_meta( $product_id, 'color' );
		$row['trim']                       = $this->get_meta( $product_id, 'version' );
		$row['vin']                        = $placa;
		$row['state_of_vehicle']           = 'USED';
		$row['dealer_name']                = $dealer['name'];
		$row['mileage.unit']               = 'KM';
		$row['mileage.value']              = $this->digits_only( $this->get_meta( $product_id, 'kilometros' ) );
		$row['vehicle_type']               = 'CAR_TRUCK';
		$row['dealer_privacy_policy_url']  = $this->public_catalog_url( $dealer['privacy_url'] );
		$row['dealer_url']                 = $this->public_catalog_url( $dealer['url'] );
		$row['days_on_lot']                = $created ? (string) max( 0, (int) ( ( time() - $created->getTimestamp() ) / DAY_IN_SECONDS ) ) : '';
		$row['engine_size']                = $this->get_meta( $product_id, 'cilindrada' );
		$row['stock_number']               = $product->get_sku() !== '' ? $product->get_sku() : (string) $product_id;
		$row['vehicle_registration_plate'] = $placa;

		return array_map(
			function ( $header ) use ( $row ) {
				return isset( $row[ $header ] ) ? $row[ $header ] : '';
			},
			$headers
		);
	}

	public function get_dealer_address_for_admin() {
		return $this->get_dealer_address();
	}

	public function get_effective_public_base() {
		$configured = trim( (string) get_option( CVIP_Meta_Catalog_Settings::OPTION_PUBLIC_BASE, '' ) );
		$configured = apply_filters( 'cvip_meta_catalog_public_base', $configured );
		$configured = untrailingslashit( trim( $configured ) );

		if ( $configured !== '' ) {
			return $configured;
		}

		return untrailingslashit( home_url() );
	}

	private function get_dealer_address() {
		$country     = '';
		$region_code = '';
		$addr1       = (string) get_option( 'woocommerce_store_address', '' );
		$addr2       = (string) get_option( 'woocommerce_store_address_2', '' );
		$city        = (string) get_option( 'woocommerce_store_city', '' );
		$postal      = (string) get_option( 'woocommerce_store_postcode', '' );

		if ( function_exists( 'WC' ) && WC()->countries ) {
			$countries   = WC()->countries;
			$addr1       = (string) $countries->get_base_address();
			$addr2       = (string) $countries->get_base_address_2();
			$city        = (string) $countries->get_base_city();
			$postal      = (string) $countries->get_base_postcode();
			$country     = (string) $countries->get_base_country();
			$region_code = (string) $countries->get_base_state();
		} else {
			$country_state = (string) get_option( 'woocommerce_default_country', '' );
			$parts         = explode( ':', $country_state );
			$country       = isset( $parts[0] ) ? $parts[0] : '';
			$region_code   = isset( $parts[1] ) ? $parts[1] : '';
		}

		$dealer = array(
			'addr1'       => $addr1,
			'addr2'       => $addr2,
			'city'        => $city,
			'region'      => $this->get_store_region_name( $country, $region_code ),
			'postal_code' => $postal,
			'country'     => $country,
			'name'        => get_bloginfo( 'name' ),
			'url'         => home_url( '/' ),
			'privacy_url' => get_privacy_policy_url(),
		);

		return apply_filters( 'cvip_meta_catalog_dealer', $dealer );
	}

	private function get_store_region_name( $country, $region_code ) {
		if ( $region_code === '' ) {
			return '';
		}

		if ( function_exists( 'WC' ) && WC()->countries ) {
			$states = WC()->countries->get_states( $country );
			if ( is_array( $states ) && isset( $states[ $region_code ] ) ) {
				return $this->plain_text( $states[ $region_code ] );
			}
		}

		return $region_code;
	}

	private function public_catalog_url( $url ) {
		$url = trim( (string) $url );
		if ( $url === '' ) {
			return '';
		}

		$public_base = $this->get_effective_public_base();
		$site_base   = untrailingslashit( home_url() );

		if ( $public_base === '' || $public_base === $site_base ) {
			return esc_url_raw( $url );
		}

		$replacements = array_unique(
			array(
				$site_base,
				untrailingslashit( site_url() ),
			)
		);

		$url = str_replace( $replacements, $public_base, $url );

		return esc_url_raw( $url );
	}

	private function get_meta( $product_id, $meta_key ) {
		$value = get_post_meta( $product_id, $meta_key, true );

		if ( is_array( $value ) ) {
			$value = implode( ', ', $value );
		}

		return $this->plain_text( $value );
	}

	private function plain_text( $value ) {
		$value = wp_strip_all_tags( (string) $value );
		$value = html_entity_decode( $value, ENT_QUOTES, 'UTF-8' );
		$value = preg_replace( '/\s+/u', ' ', $value );

		return trim( (string) $value );
	}

	private function format_price( $amount ) {
		if ( $amount === '' || $amount === null ) {
			return '';
		}

		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'COP';
		$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 0;
		$number   = number_format( (float) $amount, $decimals, '.', '' );

		return $number . ' ' . $currency;
	}

	private function digits_only( $value ) {
		$value = str_replace( array( '.', ',', ' ' ), '', (string) $value );

		return preg_replace( '/\D+/', '', $value );
	}

	private function map_fuel_type( $value ) {
		$normalized = $this->normalize_token( $value );
		if ( $normalized === '' ) {
			return '';
		}

		$map = array(
			'gasolina'       => 'GASOLINE',
			'gasoline'       => 'GASOLINE',
			'petrol'         => 'PETROL',
			'nafta'          => 'GASOLINE',
			'diesel'         => 'DIESEL',
			'acpm'           => 'DIESEL',
			'electrico'      => 'ELECTRIC',
			'electric'       => 'ELECTRIC',
			'hibrido'        => 'HYBRID',
			'hybrid'         => 'HYBRID',
			'pluginhybrid'   => 'PLUGIN_HYBRID',
			'plugin'         => 'PLUGIN_HYBRID',
			'flex'           => 'FLEX',
			'gnv'            => 'OTHER',
			'gas'            => 'GASOLINE',
		);

		return isset( $map[ $normalized ] ) ? $map[ $normalized ] : 'OTHER';
	}

	private function map_drivetrain( $value ) {
		$normalized = $this->normalize_token( $value );
		if ( $normalized === '' ) {
			return '';
		}

		if ( preg_match( '/4x4|4wd|fourwd/', $normalized ) ) {
			return 'FOUR_WD';
		}
		if ( preg_match( '/awd|integral/', $normalized ) ) {
			return 'AWD';
		}
		if ( preg_match( '/fwd|delantera/', $normalized ) ) {
			return 'FWD';
		}
		if ( preg_match( '/rwd|trasera/', $normalized ) ) {
			return 'RWD';
		}
		if ( preg_match( '/2wd|4x2|twowd/', $normalized ) ) {
			return 'TWO_WD';
		}

		$map = array(
			'4x4'        => 'FOUR_WD',
			'4wd'        => 'FOUR_WD',
			'awd'        => 'AWD',
			'fwd'        => 'FWD',
			'rwd'        => 'RWD',
			'twowd'      => 'TWO_WD',
		);

		return isset( $map[ $normalized ] ) ? $map[ $normalized ] : 'OTHER';
	}

	private function map_transmission( $value ) {
		$normalized = $this->normalize_token( $value );
		if ( $normalized === '' ) {
			return '';
		}

		if ( preg_match( '/auto|cvt|tiptronic/', $normalized ) ) {
			return 'AUTOMATIC';
		}
		if ( preg_match( '/manual|mecanica/', $normalized ) ) {
			return 'MANUAL';
		}

		return 'OTHER';
	}

	private function map_body_style( $value ) {
		$normalized = $this->normalize_token( $value );
		if ( $normalized === '' ) {
			return '';
		}

		$map = array(
			'sedan'       => 'SEDAN',
			'suv'         => 'SUV',
			'camioneta'   => 'SUV',
			'hatchback'   => 'HATCHBACK',
			'hatch'       => 'HATCHBACK',
			'coupe'       => 'COUPE',
			'cupe'        => 'COUPE',
			'convertible' => 'CONVERTIBLE',
			'cabrio'      => 'CONVERTIBLE',
			'van'         => 'VAN',
			'furgon'      => 'VAN',
			'minivan'     => 'MINIVAN',
			'truck'       => 'TRUCK',
			'pickup'      => 'TRUCK',
			'camion'      => 'TRUCK',
			'wagon'       => 'WAGON',
			'station'     => 'WAGON',
			'crossover'   => 'CROSSOVER',
		);

		return isset( $map[ $normalized ] ) ? $map[ $normalized ] : 'OTHER';
	}

	private function normalize_token( $value ) {
		$value = $this->plain_text( $value );
		$value = remove_accents( $value );
		$value = strtolower( $value );
		$value = preg_replace( '/[^a-z0-9]+/', '', $value );

		return $value;
	}
}
