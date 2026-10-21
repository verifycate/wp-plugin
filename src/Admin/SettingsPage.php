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

			<?php if ( $connected ) : ?>
				<div class="verifycate-hero is-connected">
					<div class="verifycate-hero-main">
						<span class="verifycate-pill <?php echo $ready ? 'is-on' : 'is-warn'; ?>">
							<?php echo $ready ? esc_html__( 'Connected', 'verifycate' ) : esc_html__( 'Needs attention', 'verifycate' ); ?>
						</span>
						<h2>
							<?php
							echo wp_kses_post(
								sprintf(
									/* translators: %s: workspace slug. */
									esc_html__( 'Connected to %s', 'verifycate' ),
									'<code>' . esc_html( $workspace ) . '</code>'
								)
							);
							?>
						</h2>
						<p class="verifycate-muted">
							<?php echo esc_html( $this->lastCheckMessage( $last_check ) ); ?>
						</p>
					</div>
					<div class="verifycate-hero-actions">
						<a class="button button-primary" href="<?php echo esc_url( add_query_arg( 'page', 'verifycate-mappings', admin_url( 'admin.php' ) ) ); ?>">
							<?php esc_html_e( 'Map a course', 'verifycate' ); ?>
						</a>
						<a class="button" href="<?php echo esc_url( $app_url ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Open Verifycate', 'verifycate' ); ?>
						</a>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="verifycate-inline-form">
							<input type="hidden" name="action" value="verifycate_test_connection" />
							<?php wp_nonce_field( 'verifycate_test_connection' ); ?>
							<?php submit_button( __( 'Test again', 'verifycate' ), 'link', 'submit', false ); ?>
						</form>
					</div>
				</div>

				<?php if ( '' !== $issue ) : ?>
					<div class="notice notice-warning inline">
						<p><?php echo esc_html( $issue ); ?></p>
					</div>
				<?php endif; ?>

				<div class="verifycate-checklist">
					<div class="verifycate-check is-done">
						<span class="verifycate-check-icon" aria-hidden="true">✓</span>
						<div>
							<strong><?php esc_html_e( 'Connect Verifycate', 'verifycate' ); ?></strong>
							<p class="verifycate-muted"><?php esc_html_e( 'Your workspace API key is stored encrypted.', 'verifycate' ); ?></p>
						</div>
					</div>

					<div class="verifycate-check <?php echo $mapping_count > 0 ? 'is-done' : 'is-next'; ?>">
						<span class="verifycate-check-icon" aria-hidden="true"><?php echo $mapping_count > 0 ? '✓' : '2'; ?></span>
						<div>
							<?php if ( $mapping_count > 0 ) : ?>
								<strong>
									<?php
									printf(
										/* translators: %d: number of mappings. */
										esc_html( _n( '%d course mapped', '%d courses mapped', $mapping_count, 'verifycate' ) ),
										(int) $mapping_count
									);
									?>
								</strong>
								<p class="verifycate-muted">
									<a href="<?php echo esc_url( add_query_arg( 'page', 'verifycate-mappings', admin_url( 'admin.php' ) ) ); ?>">
										<?php esc_html_e( 'Manage mappings', 'verifycate' ); ?>
									</a>
								</p>
							<?php else : ?>
								<strong><?php esc_html_e( 'Map your first course', 'verifycate' ); ?></strong>
								<p class="verifycate-muted"><?php esc_html_e( 'Choose a course and the certificate to issue when it is completed.', 'verifycate' ); ?></p>
								<a class="button button-primary" href="<?php echo esc_url( add_query_arg( 'page', 'verifycate-mappings', admin_url( 'admin.php' ) ) ); ?>#verifycate-mapping-form">
									<?php esc_html_e( 'Map a course', 'verifycate' ); ?>
								</a>
							<?php endif; ?>
						</div>
					</div>

					<div class="verifycate-check is-next">
						<span class="verifycate-check-icon" aria-hidden="true">3</span>
						<div>
							<strong><?php esc_html_e( 'Show certificates to learners', 'verifycate' ); ?></strong>
							<p class="verifycate-muted"><?php esc_html_e( 'Add the wallet shortcode to a page, or insert the Verifycate Wallet block.', 'verifycate' ); ?></p>
							<div class="verifycate-copy-row">
								<code><?php echo esc_html( Links::walletShortcode() ); ?></code>
								<button
									type="button"
									class="button button-small verifycate-copy"
									data-copy="<?php echo esc_attr( Links::walletShortcode() ); ?>"
								>
									<?php esc_html_e( 'Copy shortcode', 'verifycate' ); ?>
								</button>
								<a class="button button-small" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=page' ) ); ?>">
									<?php esc_html_e( 'Add a page', 'verifycate' ); ?>
								</a>
							</div>
						</div>
					</div>
				</div>

				<details class="verifycate-card verifycate-disclosure" id="verifycate-connection">
					<summary><?php esc_html_e( 'Connection details', 'verifycate' ); ?></summary>
					<p class="verifycate-muted">
						<?php esc_html_e( 'Change the workspace, rotate the API key, or point at a different environment.', 'verifycate' ); ?>
					</p>
					<?php $this->renderConnectionForm( true ); ?>
				</details>

				<?php $this->renderCoursePlugins( $active ); ?>
			<?php else : ?>
				<div class="verifycate-grid">
					<div class="verifycate-card">
						<h2><?php esc_html_e( 'Connect Verifycate', 'verifycate' ); ?></h2>

						<ol class="verifycate-steps">
							<li>
								<strong><?php esc_html_e( 'Create an API key', 'verifycate' ); ?></strong><br />
								<a href="<?php echo esc_url( $workspace !== '' ? $api_keys_url : $app_url ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo $workspace !== '' ? esc_html__( 'Open API keys in Verifycate', 'verifycate' ) : esc_html__( 'Open Verifycate', 'verifycate' ); ?>
								</a>
								<span class="verifycate-muted"><?php esc_html_e( 'Sign in and create a key with the certificates:read and certificates:write scopes.', 'verifycate' ); ?></span>
							</li>
							<li>
								<strong><?php esc_html_e( 'Paste it below', 'verifycate' ); ?></strong><br />
								<span class="verifycate-muted"><?php esc_html_e( 'We will detect your workspace and check the key’s permissions automatically.', 'verifycate' ); ?></span>
							</li>
						</ol>

						<?php $this->renderConnectionForm( false ); ?>
					</div>

					<?php $this->renderCoursePlugins( $active ); ?>
				</div>
			<?php endif; ?>
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
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
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

			<?php submit_button( $connected ? __( 'Save & test connection', 'verifycate' ) : __( 'Connect Verifycate', 'verifycate' ) ); ?>
		</form>

		<?php if ( $connected ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="verifycate-inline-form">
				<input type="hidden" name="action" value="verifycate_disconnect" />
				<?php wp_nonce_field( 'verifycate_disconnect' ); ?>
				<?php submit_button( __( 'Disconnect', 'verifycate' ), 'delete', 'submit', false ); ?>
			</form>
		<?php endif; ?>
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
		<div class="verifycate-card">
			<h2><?php esc_html_e( 'Course plugins', 'verifycate' ); ?></h2>
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
