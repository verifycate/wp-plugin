<?php
/**
 * Plugin bootstrap.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate;

use Verifycate\Admin\Admin;
use Verifycate\Api\CertificateCatalog;
use Verifycate\Api\ClientFactory;
use Verifycate\Frontend\Wallet;
use Verifycate\Integrations\Registry;
use Verifycate\Issuance\AttributeMapper;
use Verifycate\Issuance\IssuanceService;
use Verifycate\Issuance\MappingRepository;
use Verifycate\Issuance\Queue;
use Verifycate\Issuance\Worker;
use Verifycate\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates plugin bootstrapping and hook registration.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * API client factory.
	 *
	 * @var ClientFactory
	 */
	private ClientFactory $factory;

	/**
	 * Certificate catalogue.
	 *
	 * @var CertificateCatalog
	 */
	private CertificateCatalog $catalog;

	/**
	 * Mapping repository.
	 *
	 * @var MappingRepository
	 */
	private MappingRepository $mappings;

	/**
	 * Issuance queue.
	 *
	 * @var Queue
	 */
	private Queue $queue;

	/**
	 * Queue worker.
	 *
	 * @var Worker
	 */
	private Worker $worker;

	/**
	 * Issuance routing.
	 *
	 * @var IssuanceService
	 */
	private IssuanceService $issuance;

	/**
	 * Integration registry.
	 *
	 * @var Registry
	 */
	private Registry $integrations;

	/**
	 * Whether hooks have been registered.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Retrieve the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Wire services and register hooks.
	 */
	private function __construct() {
		$this->settings     = new Settings();
		$this->factory      = new ClientFactory( $this->settings );
		$this->catalog      = new CertificateCatalog( $this->factory, $this->settings );
		$this->mappings     = new MappingRepository();
		$this->queue        = new Queue();
		$this->worker       = new Worker( $this->factory, $this->queue, new AttributeMapper() );
		$this->issuance     = new IssuanceService( $this->mappings, $this->queue );
		$this->integrations = new Registry();
	}

	/**
	 * Prevent cloning of the singleton.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Boot the plugin: register hooks and load translations.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( Install::CRON_HOOK, array( $this->worker, 'run' ) );

		( new Admin( $this->settings, $this->factory, $this->catalog, $this->mappings, $this->queue, $this->integrations ) )->register();
		( new Wallet( $this->settings, $this->factory ) )->register();

		$this->integrations->registerAll( array( $this->issuance, 'handle' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'verifycate', new Cli\Commands( $this ) );
		}
	}

	/**
	 * Load the plugin text domain.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'verifycate',
			false,
			dirname( VERIFYCATE_BASENAME ) . '/languages'
		);
	}

	/**
	 * Settings.
	 *
	 * @return Settings
	 */
	public function settings(): Settings {
		return $this->settings;
	}

	/**
	 * API client factory.
	 *
	 * @return ClientFactory
	 */
	public function factory(): ClientFactory {
		return $this->factory;
	}

	/**
	 * Certificate catalogue.
	 *
	 * @return CertificateCatalog
	 */
	public function catalog(): CertificateCatalog {
		return $this->catalog;
	}

	/**
	 * Mapping repository.
	 *
	 * @return MappingRepository
	 */
	public function mappings(): MappingRepository {
		return $this->mappings;
	}

	/**
	 * Issuance queue.
	 *
	 * @return Queue
	 */
	public function queue(): Queue {
		return $this->queue;
	}

	/**
	 * Queue worker.
	 *
	 * @return Worker
	 */
	public function worker(): Worker {
		return $this->worker;
	}

	/**
	 * Issuance routing.
	 *
	 * @return IssuanceService
	 */
	public function issuance(): IssuanceService {
		return $this->issuance;
	}

	/**
	 * Integration registry.
	 *
	 * @return Registry
	 */
	public function integrations(): Registry {
		return $this->integrations;
	}
}
