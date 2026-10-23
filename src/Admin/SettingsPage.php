<?php
/**
 * Get started screen.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Admin;

use Verifycate\Api\ClientFactory;
use Verifycate\Integrations\Registry;
use Verifycate\Issuance\MappingRepository;
use Verifycate\Support\Crypto;
use Verifycate\Support\Links;
use Verifycate\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the connection setup, onboarding checklist and integration status.
 */
final class SettingsPage {

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Client factory.
	 *
	 * @var ClientFactory
	 */
	private ClientFactory $factory;

	/**
	 * Mapping repository.
	 *
	 * @var MappingRepository
	 */
	private MappingRepository $mappings;

	/**
	 * Integrations.
	 *
	 * @var Registry
	 */
	private Registry $integrations;

	/**
	 * Constructor.
	 *
	 * @param Settings          $settings Settings.
	 * @param ClientFactory     $factory Client factory.
	 * @param MappingRepository $mappings Mappings.
	 * @param Registry          $integrations Integrations.
	 */
	public function __construct(
		Settings $settings,
		ClientFactory $factory,
		MappingRepository $mappings,
		Registry $integrations
	) {
		$this->settings     = $settings;
		$this->factory      = $factory;
		$this->mappings     = $mappings;
		$this->integrations = $integrations;
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render(): void {
		$connected     = $this->factory->isConfigured();
		$ready         = $this->settings->isReadyToIssue();
		$issue         = $this->settings->connectionIssue();
		$last_check    = $this->settings->getLastCheck();
		$mapping_count = $this->mappings->count();
		$active        = count( $this->integrations->active() );
		$workspace     = $this->settings->getWorkspace();
		$api_keys_url  = Links::apiKeysUrl( $this->settings );
		$app_url       = Links::appUrl( $this->settings );
		$mappings_url  = add_query_arg( 'page', 'verifycate-mappings', admin_url( 'admin.php' ) );
		$configure_url = add_query_arg( 'page', 'verifycate-configure', admin_url( 'admin.php' ) );
		$map_url       = $mapping_count > 0
			? $mappings_url
			: add_query_arg(
				array(
					'page'   => 'verifycate-mappings',
					'action' => 'new',
				),
				admin_url( 'admin.php' )
			);

		Notices::render();
		?>
		<div class="wrap verifycate-wrap">
			<h1>
				<?php esc_html_e( 'Get started with Verifycate', 'verifycate' ); ?>
				<span class="verifycate-version"><?php echo esc_html( VERIFYCATE_VERSION ); ?></span>
			</h1>
			<p class="verifycate-lede">
				<?php esc_html_e( 'Connect your workspace, map a course, and Verifycate issues the certificate when a learner completes it.', 'verifycate' ); ?>
			</p>

			<?php if ( ! Crypto::available() ) : ?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e( 'This server has neither the sodium nor the openssl PHP extension, so the API key cannot be encrypted at rest. Ask your host to enable one of them.', 'verifycate' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( $connected && '' !== $issue ) : ?>
				<div class="notice notice-warning inline">
					<p><?php echo esc_html( $issue ); ?></p>
				</div>
			<?php endif; ?>

			<div class="verifycate-getstarted-layout">
				<div class="verifycate-getstarted-main">
					<?php if ( $connected ) : ?>
						<div class="verifycate-checklist">
							<div class="verifycate-check is-done">
								<span class="verifycate-check-icon" aria-hidden="true">✓</span>
								<div>
									<strong><?php esc_html_e( 'Connect Verifycate', 'verifycate' ); ?></strong>
									<p class="verifycate-muted"><?php esc_html_e( 'Your workspace API key is stored encrypted.', 'verifycate' ); ?></p>
								</div>
								<a class="button button-small verifycate-step-action" href="<?php echo esc_url( $configure_url ); ?>">
									<?php esc_html_e( 'Configure', 'verifycate' ); ?>
								</a>
							</div>

							<div class="verifycate-check <?php echo $mapping_count > 0 ? 'is-done' : 'is-next'; ?>">
								<span class="verifycate-check-icon" aria-hidden="true"><?php echo $mapping_count > 0 ? '✓' : '2'; ?></span>
								<div>
									<?php if ( $mapping_count > 0 ) : ?>
										<strong>
											<?php
											printf(
												/* translators: %d: number of mappings. */
												esc_html( _n( '%d certificate mapping', '%d certificate mappings', $mapping_count, 'verifycate' ) ),
												(int) $mapping_count
											);
											?>
										</strong>
										<p class="verifycate-muted"><?php esc_html_e( 'Manage your certificate mappings.', 'verifycate' ); ?></p>
									<?php else : ?>
										<strong><?php esc_html_e( 'Map your first course', 'verifycate' ); ?></strong>
										<p class="verifycate-muted"><?php esc_html_e( 'Choose a course and the certificate to issue when it is completed.', 'verifycate' ); ?></p>
										<a class="button button-primary" href="<?php echo esc_url( $map_url ); ?>">
											<?php esc_html_e( 'Map a course', 'verifycate' ); ?>
										</a>
									<?php endif; ?>
								</div>
								<?php if ( $mapping_count > 0 ) : ?>
									<a class="button button-small verifycate-step-action" href="<?php echo esc_url( $mappings_url ); ?>">
										<?php esc_html_e( 'Certificate Mappings', 'verifycate' ); ?>
									</a>
								<?php endif; ?>
							</div>

							<div class="verifycate-check">
								<span class="verifycate-check-icon" aria-hidden="true">
									<span class="dashicons dashicons-email-alt"></span>
								</span>
								<div>
									<strong><?php esc_html_e( 'How learners receive certificates', 'verifycate' ); ?></strong>
									<p class="verifycate-muted"><?php esc_html_e( 'Verifycate sends certificates by email, and every certificate gets its own public verification page.', 'verifycate' ); ?></p>
									<p class="verifycate-muted"><?php esc_html_e( 'If you want to embed or show certificates anywhere on your site — pages, widgets, or other templates — you can use the shortcodes below. This is optional.', 'verifycate' ); ?></p>
									<table class="widefat striped verifycate-shortcodes-table">
										<thead>
											<tr>
												<th scope="col"><?php esc_html_e( 'Shortcode', 'verifycate' ); ?></th>
												<th scope="col"><?php esc_html_e( 'Description', 'verifycate' ); ?></th>
											</tr>
										</thead>
										<tbody>
											<?php
											$this->renderShortcodeRow(
												Links::walletShortcode(),
												sprintf(
													/* translators: 1: title attribute, 2: empty_text attribute, 3: limit attribute. */
													__( 'Shows the logged-in learner’s certificates from their Verifycate recipient record, including status, issue date and a View certificate link. Use it on a “My Certificates” page or learner dashboard. Optional attributes: %1$s, %2$s and %3$s.', 'verifycate' ),
													'<code>title</code>',
													'<code>empty_text</code>',
													'<code>limit</code>'
												)
											);
											$this->renderShortcodeRow(
												Links::certificateShortcode(),
												__( 'Embeds one certificate card by public ID. Use it on a certificate landing page or a page linked from an email.', 'verifycate' )
											);
											$this->renderShortcodeRow(
												Links::verifyShortcode(),
												__( 'Shows a public form for looking up a certificate by public ID. Use it on a “Verify a certificate” page.', 'verifycate' )
											);
											?>
										</tbody>
									</table>
								</div>
							</div>
						</div>
					<?php else : ?>
						<div class="verifycate-checklist">
							<div class="verifycate-check is-next">
								<span class="verifycate-check-icon" aria-hidden="true">1</span>
								<div>
									<strong><?php esc_html_e( 'Create an API key', 'verifycate' ); ?></strong>
									<p class="verifycate-muted"><?php esc_html_e( 'Sign in and create a key with the certificates:read and certificates:write scopes.', 'verifycate' ); ?></p>
									<p>
										<a href="<?php echo esc_url( '' !== $workspace ? $api_keys_url : $app_url ); ?>" target="_blank" rel="noopener noreferrer">
											<?php echo '' !== $workspace ? esc_html__( 'Open API keys in Verifycate', 'verifycate' ) : esc_html__( 'Open Verifycate', 'verifycate' ); ?>
										</a>
									</p>
								</div>
							</div>

							<div class="verifycate-check">
								<span class="verifycate-check-icon" aria-hidden="true">2</span>
								<div>
									<strong><?php esc_html_e( 'Paste it in the connection panel', 'verifycate' ); ?></strong>
									<p class="verifycate-muted"><?php esc_html_e( 'We will detect your workspace and check the key’s permissions automatically.', 'verifycate' ); ?></p>
								</div>
							</div>

							<div class="verifycate-check">
								<span class="verifycate-check-icon" aria-hidden="true">3</span>
								<div>
									<strong><?php esc_html_e( 'Map a course', 'verifycate' ); ?></strong>
									<p class="verifycate-muted"><?php esc_html_e( 'Choose a course and the certificate to issue when it is completed.', 'verifycate' ); ?></p>
								</div>
							</div>
						</div>
					<?php endif; ?>
				</div>

				<aside class="verifycate-getstarted-sidebar">
					<?php if ( $connected ) : ?>
						<div class="verifycate-panel verifycate-connection-panel">
							<div class="verifycate-panel-header verifycate-panel-header--static">
								<span class="verifycate-panel-title"><?php esc_html_e( 'Connection', 'verifycate' ); ?></span>
								<div class="verifycate-connection-status">
									<span class="verifycate-pill <?php echo $ready ? 'is-on' : 'is-warn'; ?>">
										<?php echo $ready ? esc_html__( 'Connected', 'verifycate' ) : esc_html__( 'Needs attention', 'verifycate' ); ?>
									</span>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="verifycate-connection-refresh">
										<input type="hidden" name="action" value="verifycate_test_connection" />
										<?php wp_nonce_field( 'verifycate_test_connection' ); ?>
										<button type="submit" class="verifycate-refresh" aria-label="<?php esc_attr_e( 'Test connection again', 'verifycate' ); ?>" title="<?php esc_attr_e( 'Test connection again', 'verifycate' ); ?>">
											<span class="dashicons dashicons-update" aria-hidden="true"></span>
										</button>
									</form>
								</div>
							</div>
							<div class="verifycate-panel-body">
								<h2 class="verifycate-connection-title">
									<?php
									if ( '' !== $workspace ) {
										printf(
											/* translators: %s: workspace slug. */
											esc_html__( 'Connected to %s', 'verifycate' ),
											'<code>' . esc_html( $workspace ) . '</code>'
										);
									} else {
										esc_html_e( 'Connected', 'verifycate' );
									}
									?>
								</h2>
								<p class="verifycate-muted"><?php echo esc_html( $this->lastCheckMessage( $last_check ) ); ?></p>
								<div class="verifycate-connection-actions">
									<a class="button button-primary" href="<?php echo esc_url( $map_url ); ?>">
										<?php esc_html_e( 'Map a course', 'verifycate' ); ?>
									</a>
									<a class="button" href="<?php echo esc_url( $app_url ); ?>" target="_blank" rel="noopener noreferrer">
										<?php esc_html_e( 'Open Verifycate', 'verifycate' ); ?>
									</a>
								</div>
							</div>
						</div>

						<?php $this->renderCoursePlugins( $active ); ?>
					<?php else : ?>
						<div class="verifycate-panel">
							<div class="verifycate-panel-header verifycate-panel-header--static">
								<span class="verifycate-panel-title"><?php esc_html_e( 'Connect Verifycate', 'verifycate' ); ?></span>
							</div>
							<div class="verifycate-panel-body">
								<p class="verifycate-muted"><?php esc_html_e( 'Paste a workspace API key. We will detect the workspace and check its permissions automatically.', 'verifycate' ); ?></p>
								<?php $this->renderConnectionForm( false ); ?>
							</div>
						</div>

						<?php $this->renderCoursePlugins( $active ); ?>
					<?php endif; ?>
				</aside>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the configuration screen.
	 *
	 * @return void
	 */
	public function renderConfigure(): void {
		$connected = $this->factory->isConfigured();
		$ready     = $this->settings->isReadyToIssue();
		$issue     = $this->settings->connectionIssue();
		$workspace = $this->settings->getWorkspace();

		Notices::render();
		?>
		<div class="wrap verifycate-wrap">
			<h1><?php esc_html_e( 'Configure Verifycate', 'verifycate' ); ?></h1>
			<p class="verifycate-lede">
				<?php esc_html_e( 'Manage the workspace connection, API key and environment settings.', 'verifycate' ); ?>
			</p>

			<?php if ( ! Crypto::available() ) : ?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e( 'This server has neither the sodium nor the openssl PHP extension, so the API key cannot be encrypted at rest. Ask your host to enable one of them.', 'verifycate' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( $connected && '' !== $issue ) : ?>
				<div class="notice notice-warning inline">
					<p><?php echo esc_html( $issue ); ?></p>
				</div>
			<?php endif; ?>

			<div class="verifycate-configure-wrap">
				<div class="verifycate-panel">
					<div class="verifycate-panel-header verifycate-panel-header--static">
						<span class="verifycate-panel-title"><?php esc_html_e( 'Connection', 'verifycate' ); ?></span>
						<?php if ( $connected ) : ?>
							<span class="verifycate-pill <?php echo $ready ? 'is-on' : 'is-warn'; ?>">
								<?php echo $ready ? esc_html__( 'Connected', 'verifycate' ) : esc_html__( 'Needs attention', 'verifycate' ); ?>
							</span>
						<?php endif; ?>
					</div>
					<div class="verifycate-panel-body">
						<?php if ( $connected && '' !== $workspace ) : ?>
							<p class="verifycate-muted">
								<?php esc_html_e( 'Workspace', 'verifycate' ); ?>
								<code><?php echo esc_html( $workspace ); ?></code>
							</p>
						<?php endif; ?>
						<?php $this->renderConnectionForm( $connected ); ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the connection form.
	 *
	 * @param bool $connected Whether the site is connected.
	 * @return void
	 */
	private function renderConnectionForm( bool $connected ): void {
		$masked = $this->settings->getMaskedApiKey();
		?>
		<form id="verifycate-connection-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="verifycate_save_settings" />
			<?php wp_nonce_field( 'verifycate_save_settings' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="verifycate-api-key"><?php esc_html_e( 'API key', 'verifycate' ); ?></label>
					</th>
					<td>
						<input
							type="password"
							id="verifycate-api-key"
							name="api_key"
							value=""
							placeholder="<?php echo esc_attr( '' !== $masked ? $masked : 'vc_...' ); ?>"
							class="regular-text"
							autocomplete="new-password"
						/>
						<p class="description">
							<?php
							echo wp_kses_post(
								sprintf(
									/* translators: %s: API keys link. */
									esc_html__( 'Paste a key with the certificates:read and certificates:write scopes. We will find your workspace automatically. %s', 'verifycate' ),
									'<a href="' . esc_url( Links::apiKeysUrl( $this->settings ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Create an API key', 'verifycate' ) . '</a>'
								)
							);
							?>
							<?php if ( $connected ) : ?>
								<br /><?php esc_html_e( 'Leave this blank to keep the stored key.', 'verifycate' ); ?>
							<?php endif; ?>
						</p>
					</td>
				</tr>
			</table>

			<details class="verifycate-advanced">
				<summary><?php esc_html_e( 'Advanced settings', 'verifycate' ); ?></summary>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="verifycate-workspace"><?php esc_html_e( 'Workspace override', 'verifycate' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="verifycate-workspace"
								name="workspace_override"
								value=""
								class="regular-text"
								placeholder="<?php echo esc_attr( $this->settings->getWorkspace() ); ?>"
								autocomplete="off"
							/>
							<p class="description">
								<?php esc_html_e( 'Leave blank to detect the workspace from the API key. Paste a slug or dashboard URL only if you need to override it.', 'verifycate' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="verifycate-base-url"><?php esc_html_e( 'API base URL', 'verifycate' ); ?></label>
						</th>
						<td>
							<input
								type="url"
								id="verifycate-base-url"
								name="base_url"
								value="<?php echo esc_attr( $this->settings->getBaseUrl() ); ?>"
								class="regular-text"
							/>
							<p class="description"><?php esc_html_e( 'Only change this if Verifycate support asked you to use another environment.', 'verifycate' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="verifycate-portal-url"><?php esc_html_e( 'Verification page base URL', 'verifycate' ); ?></label>
						</th>
						<td>
							<input
								type="url"
								id="verifycate-portal-url"
								name="portal_url"
								value="<?php echo esc_attr( $this->settings->getPortalUrl() ); ?>"
								class="regular-text"
							/>
							<p class="description"><?php esc_html_e( 'Used for “View certificate” links in the wallet.', 'verifycate' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Uninstall', 'verifycate' ); ?></th>
						<td>
							<label for="verifycate-delete-data">
								<input
									type="checkbox"
									id="verifycate-delete-data"
									name="delete_data"
									value="1"
									<?php checked( $this->settings->shouldDeleteDataOnUninstall() ); ?>
								/>
								<?php esc_html_e( 'Delete settings, mappings, queue history and logs when the plugin is uninstalled.', 'verifycate' ); ?>
							</label>
						</td>
					</tr>
				</table>
			</details>
		</form>

		<div class="verifycate-config-actions">
			<?php if ( $connected ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="verifycate-inline-form">
					<input type="hidden" name="action" value="verifycate_disconnect" />
					<?php wp_nonce_field( 'verifycate_disconnect' ); ?>
					<button type="submit" class="button verifycate-disconnect">
						<?php esc_html_e( 'Disconnect', 'verifycate' ); ?>
					</button>
				</form>
			<?php endif; ?>
			<button type="submit" form="verifycate-connection-form" class="button button-primary">
				<?php echo $connected ? esc_html__( 'Save & test connection', 'verifycate' ) : esc_html__( 'Connect Verifycate', 'verifycate' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Render a documented shortcode row.
	 *
	 * @param string $shortcode Shortcode text.
	 * @param string $description Description HTML.
	 * @return void
	 */
	private function renderShortcodeRow( string $shortcode, string $description ): void {
		$label = __( 'Copy shortcode', 'verifycate' );
		?>
		<tr>
			<td>
				<code><?php echo esc_html( $shortcode ); ?></code>
				<button
					type="button"
					class="button-link verifycate-copy verifycate-copy-icon"
					data-copy="<?php echo esc_attr( $shortcode ); ?>"
					data-copy-label="<?php echo esc_attr( $label ); ?>"
					aria-label="<?php echo esc_attr( $label ); ?>"
					title="<?php echo esc_attr( $label ); ?>"
				>
					<svg class="verifycate-copy-icon__glyph" viewBox="0 0 20 20" width="16" height="16" aria-hidden="true" focusable="false">
						<rect x="7" y="7" width="9" height="10" rx="1.5"></rect>
						<path d="M13 7V5.5A1.5 1.5 0 0 0 11.5 4h-6A1.5 1.5 0 0 0 4 5.5v8A1.5 1.5 0 0 0 5.5 15H7"></path>
					</svg>
				</button>
			</td>
			<td><?php echo wp_kses_post( $description ); ?></td>
		</tr>
		<?php
	}

	/**
	 * Render the course-plugin status card.
	 *
	 * @param int $active Number of active integrations.
	 * @return void
	 */
	private function renderCoursePlugins( int $active ): void {
		?>
		<div class="verifycate-panel">
			<div class="verifycate-panel-header verifycate-panel-header--static">
				<span class="verifycate-panel-title"><?php esc_html_e( 'Course plugins', 'verifycate' ); ?></span>
			</div>
			<div class="verifycate-panel-body">
				<p class="verifycate-muted">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of active integrations. */
							_n( '%d integration is active.', '%d integrations are active.', $active, 'verifycate' ),
							$active
						)
					);
					?>
				</p>

				<ul class="verifycate-integrations">
					<?php foreach ( $this->integrations->all() as $integration ) : ?>
						<li>
							<span class="verifycate-pill <?php echo $integration->isActive() ? 'is-on' : 'is-off'; ?>">
								<?php echo $integration->isActive() ? esc_html__( 'Active', 'verifycate' ) : esc_html__( 'Not active', 'verifycate' ); ?>
							</span>
							<?php echo esc_html( $integration->getLabel() ); ?>
						</li>
					<?php endforeach; ?>
				</ul>

				<details class="verifycate-advanced">
					<summary><?php esc_html_e( 'Using another plugin?', 'verifycate' ); ?></summary>
					<p class="verifycate-muted"><?php esc_html_e( 'Call the custom hook from your own code, then create a mapping for “Custom hook”.', 'verifycate' ); ?></p>
					<pre class="verifycate-code"><code>do_action( 'verifycate_course_completed', $user_id, $course_id );</code></pre>
					<p class="verifycate-muted"><?php esc_html_e( 'Pass a third argument to name the event, and a fourth array for course_title, score or extra context tokens.', 'verifycate' ); ?></p>
				</details>
			</div>
		</div>
		<?php
	}

	/**
	 * Last connection result, with a sensible fallback.
	 *
	 * @param array<string, mixed> $last_check Last check.
	 * @return string
	 */
	private function lastCheckMessage( array $last_check ): string {
		if ( ! empty( $last_check['message'] ) && is_scalar( $last_check['message'] ) ) {
			$message = (string) $last_check['message'];

			if ( ! empty( $last_check['time'] ) && is_numeric( $last_check['time'] ) ) {
				$message .= ' ' . sprintf(
					/* translators: %s: date and time. */
					__( 'Checked %s.', 'verifycate' ),
					wp_date( 'Y-m-d H:i', (int) $last_check['time'] )
				);
			}

			return $message;
		}

		return __( 'Run a test to confirm the connection.', 'verifycate' );
	}
}
