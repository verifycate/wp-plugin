<?php
/**
 * Setup screen.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Admin;

use Verifycate\Api\ClientFactory;
use Verifycate\Integrations\Registry;
use Verifycate\Support\Crypto;
use Verifycate\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the connection settings and integration status.
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
	 * Integrations.
	 *
	 * @var Registry
	 */
	private Registry $integrations;

	/**
	 * Constructor.
	 *
	 * @param Settings      $settings Settings.
	 * @param ClientFactory $factory Client factory.
	 * @param Registry      $integrations Integrations.
	 */
	public function __construct( Settings $settings, ClientFactory $factory, Registry $integrations ) {
		$this->settings     = $settings;
		$this->factory      = $factory;
		$this->integrations = $integrations;
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render(): void {
		$connected  = $this->factory->isConfigured();
		$last_check = $this->settings->getLastCheck();
		$mapped     = count( $this->integrations->active() );

		Notices::render();
		?>
		<div class="wrap verifycate-wrap">
			<h1>
				<?php esc_html_e( 'Verifycate', 'verifycate' ); ?>
				<span class="verifycate-version"><?php echo esc_html( VERIFYCATE_VERSION ); ?></span>
			</h1>
			<p class="verifycate-lede">
				<?php esc_html_e( 'Connect this site to your Verifycate workspace, then map a course to a certificate. Issuance happens automatically when a learner completes the course.', 'verifycate' ); ?>
			</p>

			<?php if ( ! Crypto::available() ) : ?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e( 'This server has neither the sodium nor the openssl PHP extension, so the API key cannot be encrypted at rest. Ask your host to enable one of them.', 'verifycate' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="verifycate-grid">
				<div class="verifycate-card">
					<h2><?php esc_html_e( 'Connection', 'verifycate' ); ?></h2>
					<p>
						<span class="verifycate-pill <?php echo $connected ? 'is-on' : 'is-off'; ?>">
							<?php echo $connected ? esc_html__( 'Connected', 'verifycate' ) : esc_html__( 'Not connected', 'verifycate' ); ?>
						</span>
						<?php if ( '' !== $this->settings->getWorkspace() ) : ?>
							<code><?php echo esc_html( $this->settings->getWorkspace() ); ?></code>
						<?php endif; ?>
					</p>

					<?php if ( $last_check !== array() ) : ?>
						<p class="verifycate-muted">
							<?php
							if ( ! empty( $last_check['time'] ) ) {
								echo esc_html(
									sprintf(
										/* translators: %s: date and time. */
										__( 'Last checked %s.', 'verifycate' ),
										wp_date( 'Y-m-d H:i', (int) $last_check['time'] )
									)
								);
							}
							?>
							<?php if ( ! empty( $last_check['message'] ) ) : ?>
								<br />
								<?php echo esc_html( (string) $last_check['message'] ); ?>
							<?php endif; ?>
						</p>
					<?php endif; ?>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="verifycate_save_settings" />
						<?php wp_nonce_field( 'verifycate_save_settings' ); ?>

						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">
									<label for="verifycate-workspace"><?php esc_html_e( 'Workspace slug', 'verifycate' ); ?></label>
								</th>
								<td>
									<input
										type="text"
										id="verifycate-workspace"
										name="workspace"
										value="<?php echo esc_attr( $this->settings->getWorkspace() ); ?>"
										class="regular-text"
										autocomplete="off"
										required
									/>
									<p class="description">
										<?php esc_html_e( 'The slug shown in your Verifycate workspace URL, for example acme.', 'verifycate' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="verifycate-api-key"><?php esc_html_e( 'Workspace API key', 'verifycate' ); ?></label>
								</th>
								<td>
									<input
										type="password"
										id="verifycate-api-key"
										name="api_key"
										value=""
										placeholder="<?php echo esc_attr( $this->settings->getMaskedApiKey() ); ?>"
										class="regular-text"
										autocomplete="new-password"
									/>
									<p class="description">
										<?php esc_html_e( 'Create a key in Verifycate under Workspace settings → API keys. It needs the certificates:read and certificates:write scopes. Leave this blank to keep the stored key.', 'verifycate' ); ?>
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
									<p class="description">
										<?php esc_html_e( 'Leave the default unless Verifycate support asked you to use a different environment.', 'verifycate' ); ?>
									</p>
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
									<p class="description">
										<?php esc_html_e( 'Used for “View certificate” links in the wallet. Defaults to https://verifycate.com.', 'verifycate' ); ?>
									</p>
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

						<?php submit_button( __( 'Save connection', 'verifycate' ) ); ?>
					</form>

					<div class="verifycate-actions">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="verifycate_test_connection" />
							<?php wp_nonce_field( 'verifycate_test_connection' ); ?>
							<?php submit_button( __( 'Test connection', 'verifycate' ), 'secondary', 'submit', false ); ?>
						</form>

						<?php if ( $connected ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="verifycate-inline-form">
								<input type="hidden" name="action" value="verifycate_disconnect" />
								<?php wp_nonce_field( 'verifycate_disconnect' ); ?>
								<?php submit_button( __( 'Disconnect', 'verifycate' ), 'delete', 'submit', false ); ?>
							</form>
						<?php endif; ?>
					</div>
				</div>

				<div class="verifycate-card">
					<h2><?php esc_html_e( 'Course plugins', 'verifycate' ); ?></h2>
					<p class="verifycate-muted">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: number of active integrations. */
								_n( '%d integration is active.', '%d integrations are active.', $mapped, 'verifycate' ),
								$mapped
							)
						);
						?>
					</p>

					<ul class="verifycate-integrations">
						<?php foreach ( $this->integrations->all() as $integration ) : ?>
							<li>
								<span class="verifycate-pill <?php echo $integration->isActive() ? 'is-on' : 'is-off'; ?>">
									<?php echo $integration->isActive() ? esc_html__( 'Active', 'verifycate' ) : esc_html__( 'Not detected', 'verifycate' ); ?>
								</span>
								<?php echo esc_html( $integration->getLabel() ); ?>
							</li>
						<?php endforeach; ?>
					</ul>

					<p>
						<?php
						echo wp_kses_post(
							sprintf(
								/* translators: %s: custom hook name. */
								esc_html__( 'Other plugins can call %s with a user id and optional course id.', 'verifycate' ),
								'<code>do_action( \'verifycate_course_completed\', $user_id, $course_id )</code>'
							)
						);
						?>
					</p>

					<p>
						<a class="button" href="<?php echo esc_url( add_query_arg( 'page', 'verifycate-mappings', admin_url( 'admin.php' ) ) ); ?>">
							<?php esc_html_e( 'Go to mappings', 'verifycate' ); ?>
						</a>
					</p>
				</div>
			</div>
		</div>
		<?php
	}
}
