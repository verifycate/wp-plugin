<?php
/**
 * Integration registry.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Discovers active LMS adapters and registers their hooks.
 */
final class Registry {

	/**
	 * Integrations keyed by id.
	 *
	 * @var array<string, IntegrationInterface>
	 */
	private array $integrations = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->add( new LearnDash() );
		$this->add( new TutorLms() );
		$this->add( new LearnPress() );
		$this->add( new LifterLms() );
		$this->add( new Sensei() );
		$this->add( new CustomHook() );
	}

	/**
	 * Register the completion hooks of every active integration.
	 *
	 * @param callable $listener Receives a CompletionEvent.
	 * @return void
	 */
	public function registerAll( callable $listener ): void {
		foreach ( $this->active() as $integration ) {
			$integration->register( $listener );
		}
	}

	/**
	 * Active integrations.
	 *
	 * @return array<string, IntegrationInterface>
	 */
	public function active(): array {
		return array_filter(
			$this->integrations,
			static function ( IntegrationInterface $integration ): bool {
				return $integration->isActive();
			}
		);
	}

	/**
	 * All known integrations.
	 *
	 * @return array<string, IntegrationInterface>
	 */
	public function all(): array {
		return $this->integrations;
	}

	/**
	 * Get one integration.
	 *
	 * @param string $id Integration id.
	 * @return IntegrationInterface|null
	 */
	public function get( string $id ): ?IntegrationInterface {
		return $this->integrations[ $id ] ?? null;
	}

	/**
	 * Active LMS integrations with their courses, for the mapping picker.
	 *
	 * @return array<int, array{id: string, label: string, courses: array<int, string>}>
	 */
	public function courseGroups(): array {
		$groups = array();

		foreach ( $this->active() as $integration ) {
			if ( 'custom' === $integration->getId() ) {
				continue;
			}

			$groups[] = array(
				'id'      => $integration->getId(),
				'label'   => $integration->getLabel(),
				'courses' => $integration->getCourses(),
			);
		}

		return $groups;
	}

	/**
	 * Active integration labels.
	 *
	 * @return array<string, string>
	 */
	public function labels(): array {
		$labels = array();

		foreach ( $this->active() as $id => $integration ) {
			$labels[ $id ] = $integration->getLabel();
		}

		return $labels;
	}

	/**
	 * Add an integration.
	 *
	 * @param IntegrationInterface $integration Integration.
	 * @return void
	 */
	private function add( IntegrationInterface $integration ): void {
		$this->integrations[ $integration->getId() ] = $integration;
	}
}
