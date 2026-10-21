<?php
/**
 * Mapping screen.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Admin;

use Verifycate\Api\CertificateCatalog;
use Verifycate\Integrations\Registry;
use Verifycate\Issuance\AttributeMapper;
use Verifycate\Issuance\Mapping;
use Verifycate\Issuance\MappingRepository;
use Verifycate\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the course-to-certificate mapping editor.
 */
final class MappingsPage {

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

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
	 * Integrations.
	 *
	 * @var Registry
	 */
	private Registry $integrations;

	/**
	 * Constructor.
	 *
	 * @param Settings           $settings Settings.
	 * @param CertificateCatalog $catalog Catalogue.
	 * @param MappingRepository  $mappings Mappings.
	 * @param Registry           $integrations Integrations.
	 */
	public function __construct(
		Settings $settings,
		CertificateCatalog $catalog,
		MappingRepository $mappings,
		Registry $integrations
	) {
		$this->settings     = $settings;
		$this->catalog      = $catalog;
		$this->mappings     = $mappings;
		$this->integrations = $integrations;
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen state.
		$editing_id = isset( $_GET['mapping'] ) ? absint( wp_unslash( (string) $_GET['mapping'] ) ) : 0;
		$editing    = $editing_id > 0 ? $this->mappings->find( $editing_id ) : null;
		$mappings   = $this->mappings->all();
		$catalog    = $this->catalog->all();

		Notices::render();
		?>
		<div class="wrap verifycate-wrap">
			<h1><?php esc_html_e( 'Mappings', 'verifycate' ); ?></h1>
			<p class="verifycate-lede">
				<?php esc_html_e( 'Choose a course and the certificate to issue when a learner completes it. One certificate can cover an entire catalogue with the “Any course” option.', 'verifycate' ); ?>
			</p>

			<?php if ( ! $this->settings->isConnected() ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<?php
						echo wp_kses_post(
							sprintf(
								/* translators: %s: settings link. */
								esc_html__( 'Connect your workspace before issuing certificates. %s', 'verifycate' ),
								'<a href="' . esc_url( add_query_arg( 'page', 'verifycate', admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Open Setup', 'verifycate' ) . '</a>'
							)
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Saved mappings', 'verifycate' ); ?></h2>

			<?php if ( $mappings === array() ) : ?>
				<p><?php esc_html_e( 'No mappings yet. Add the first one below.', 'verifycate' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped verifycate-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Status', 'verifycate' ); ?></th>
							<th><?php esc_html_e( 'Course', 'verifycate' ); ?></th>
							<th><?php esc_html_e( 'Certificate', 'verifycate' ); ?></th>
							<th><?php esc_html_e( 'Attributes', 'verifycate' ); ?></th>
							<th><?php esc_html_e( 'Repeat completions', 'verifycate' ); ?></th>
							<th><?php esc_html_e( 'Release', 'verifycate' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'verifycate' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $mappings as $mapping ) : ?>
							<tr>
								<td>
									<span class="verifycate-pill <?php echo $mapping->isEnabled() ? 'is-on' : 'is-off'; ?>">
										<?php echo $mapping->isEnabled() ? esc_html__( 'Enabled', 'verifycate' ) : esc_html__( 'Disabled', 'verifycate' ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $this->courseLabel( $mapping ) ); ?></td>
								<td>
									<?php echo esc_html( $this->certificateLabel( $mapping ) ); ?><br />
									<code><?php echo esc_html( $mapping->getCertificateId() ); ?></code>
								</td>
								<td><?php echo esc_html( (string) count( $mapping->getAttributes() ) ); ?></td>
								<td>
									<?php
									echo Mapping::DUPLICATE_REISSUE === $mapping->getDuplicatePolicy()
										? esc_html__( 'Issue again', 'verifycate' )
										: esc_html__( 'Skip duplicates', 'verifycate' );
									?>
								</td>
								<td><?php echo $mapping->shouldAutoIssue() ? esc_html__( 'Immediately', 'verifycate' ) : esc_html__( 'Leave pending', 'verifycate' ); ?></td>
								<td class="verifycate-row-actions">
									<a class="button button-small" href="
									<?php
									echo esc_url(
										add_query_arg(
											array(
												'page'    => 'verifycate-mappings',
												'mapping' => $mapping->getId(),
											),
											admin_url( 'admin.php' )
										)
									);
									?>
																			#verifycate-mapping-form">
										<?php esc_html_e( 'Edit', 'verifycate' ); ?>
									</a>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="verifycate-delete-mapping">
										<input type="hidden" name="action" value="verifycate_delete_mapping" />
										<input type="hidden" name="mapping_id" value="<?php echo esc_attr( (string) $mapping->getId() ); ?>" />
										<?php wp_nonce_field( 'verifycate_delete_mapping' ); ?>
										<button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Delete', 'verifycate' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<hr />

			<h2 id="verifycate-mapping-form">
				<?php echo null === $editing ? esc_html__( 'Add a mapping', 'verifycate' ) : esc_html__( 'Edit mapping', 'verifycate' ); ?>
			</h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="verifycate-form">
				<input type="hidden" name="action" value="verifycate_save_mapping" />
				<input type="hidden" name="mapping_id" value="<?php echo esc_attr( (string) ( $editing ? $editing->getId() : 0 ) ); ?>" />
				<?php wp_nonce_field( 'verifycate_save_mapping' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="verifycate-source"><?php esc_html_e( 'When this is completed', 'verifycate' ); ?></label>
						</th>
						<td>
							<select id="verifycate-source" name="source" class="regular-text" required>
								<option value=""><?php esc_html_e( 'Select a course…', 'verifycate' ); ?></option>
								<?php foreach ( $this->integrations->courseGroups() as $group ) : ?>
									<optgroup label="<?php echo esc_attr( $group['label'] ); ?>">
										<option value="<?php echo esc_attr( $group['id'] . ':0' ); ?>" <?php selected( $this->sourceValue( $editing ), $group['id'] . ':0' ); ?>>
											<?php
											printf(
												/* translators: %s: LMS name. */
												esc_html__( 'Any %s course', 'verifycate' ),
												esc_html( $group['label'] )
											);
											?>
										</option>
										<?php foreach ( $group['courses'] as $course_id => $title ) : ?>
											<option value="<?php echo esc_attr( $group['id'] . ':' . $course_id ); ?>" <?php selected( $this->sourceValue( $editing ), $group['id'] . ':' . $course_id ); ?>>
												<?php echo esc_html( $title ); ?> (#<?php echo esc_html( (string) $course_id ); ?>)
											</option>
										<?php endforeach; ?>
									</optgroup>
								<?php endforeach; ?>
								<optgroup label="<?php esc_attr_e( 'Other', 'verifycate' ); ?>">
									<option value="custom:0" <?php selected( $editing && 'custom' === $editing->getIntegration(), true ); ?>>
										<?php esc_html_e( 'Custom hook', 'verifycate' ); ?>
									</option>
								</optgroup>
							</select>
							<p class="description">
								<?php esc_html_e( 'Course lists come from active LMS plugins. Use the custom hook for membership, e-commerce and in-house course plugins.', 'verifycate' ); ?>
							</p>
						</td>
					</tr>
					<tr class="verifycate-custom-fields" <?php echo $editing && 'custom' === $editing->getIntegration() ? '' : 'hidden'; ?>>
						<th scope="row">
							<label for="verifycate-custom-event"><?php esc_html_e( 'Custom event name', 'verifycate' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="verifycate-custom-event"
								name="custom_event"
								value="<?php echo esc_attr( $editing ? $editing->getEvent() : '' ); ?>"
								placeholder="course_completed"
								class="regular-text"
							/>
							<label for="verifycate-custom-course"><?php esc_html_e( 'Course ID', 'verifycate' ); ?></label>
							<input
								type="number"
								id="verifycate-custom-course"
								name="custom_course_id"
								value="<?php echo esc_attr( (string) ( $editing && 'custom' === $editing->getIntegration() ? $editing->getCourseId() : 0 ) ); ?>"
								min="0"
								class="small-text"
							/>
							<p class="description">
								<?php esc_html_e( 'The event name must match the third argument of do_action( \'verifycate_course_completed\', … ). Leave the course ID at 0 to match any custom completion.', 'verifycate' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="verifycate-certificate"><?php esc_html_e( 'Issue this certificate', 'verifycate' ); ?></label>
						</th>
						<td>
							<?php if ( $catalog === array() ) : ?>
								<input
									type="text"
									id="verifycate-certificate"
									name="certificate_id"
									value="<?php echo esc_attr( $editing ? $editing->getCertificateId() : '' ); ?>"
									placeholder="00000000-0000-0000-0000-000000000000"
									class="regular-text"
									required
								/>
								<input
									type="text"
									name="certificate_name"
									value="<?php echo esc_attr( $editing ? $editing->getCertificateName() : '' ); ?>"
									placeholder="<?php esc_attr_e( 'Certificate name (optional)', 'verifycate' ); ?>"
									class="regular-text"
								/>
								<p class="description">
									<?php esc_html_e( 'The certificate list could not be loaded. Paste the certificate ID from the Verifycate dashboard, or connect the workspace and reload.', 'verifycate' ); ?>
								</p>
							<?php else : ?>
								<select id="verifycate-certificate" name="certificate_id" class="regular-text" required>
									<option value=""><?php esc_html_e( 'Select a certificate…', 'verifycate' ); ?></option>
									<?php
									$found_current = false;
									foreach ( $catalog as $certificate ) {
										$current = $editing && $editing->getCertificateId() === $certificate['id'];
										if ( $current ) {
											$found_current = true;
										}
										?>
										<option value="<?php echo esc_attr( $certificate['id'] ); ?>" <?php selected( $current ); ?>>
											<?php echo esc_html( $certificate['name'] ); ?>
											(<?php echo esc_html( $certificate['status'] ); ?>)
										</option>
										<?php
									}
									if ( $editing && ! $found_current ) :
										?>
										<option value="<?php echo esc_attr( $editing->getCertificateId() ); ?>" selected>
											<?php echo esc_html( '' !== $editing->getCertificateName() ? $editing->getCertificateName() : $editing->getCertificateId() ); ?>
										</option>
									<?php endif; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'Draft certificates queue recipients but cannot be released until the certificate is published in Verifycate.', 'verifycate' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="verifycate-attributes"><?php esc_html_e( 'Attribute values', 'verifycate' ); ?></label>
						</th>
						<td>
							<textarea
								id="verifycate-attributes"
								name="attributes"
								rows="6"
								class="large-text code"
								placeholder="course_hours = {course_meta:duration}"
							><?php echo esc_textarea( $this->formatAttributes( $editing ? $editing->getAttributes() : array() ) ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'One attribute per line: key = template. Only keys declared on the certificate are sent.', 'verifycate' ); ?>
							</p>
							<div class="verifycate-attribute-keys" data-target="#verifycate-certificate"></div>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Repeat completions', 'verifycate' ); ?></th>
						<td>
							<select name="duplicate_policy">
								<option value="skip" <?php selected( $editing ? $editing->getDuplicatePolicy() : Mapping::DUPLICATE_SKIP, Mapping::DUPLICATE_SKIP ); ?>>
									<?php esc_html_e( 'Skip — issue once per learner', 'verifycate' ); ?>
								</option>
								<option value="reissue" <?php selected( $editing ? $editing->getDuplicatePolicy() : '', Mapping::DUPLICATE_REISSUE ); ?>>
									<?php esc_html_e( 'Issue again — recertification', 'verifycate' ); ?>
								</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Release', 'verifycate' ); ?></th>
						<td>
							<label for="verifycate-auto-issue">
								<input
									type="checkbox"
									id="verifycate-auto-issue"
									name="auto_issue"
									value="1"
									<?php checked( $editing ? $editing->shouldAutoIssue() : true ); ?>
								/>
								<?php esc_html_e( 'Issue the certificate immediately after importing the recipient.', 'verifycate' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Turn this off to queue recipients and release them from the Verifycate dashboard.', 'verifycate' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Status', 'verifycate' ); ?></th>
						<td>
							<label for="verifycate-enabled">
								<input
									type="checkbox"
									id="verifycate-enabled"
									name="enabled"
									value="1"
									<?php checked( $editing ? $editing->isEnabled() : true ); ?>
								/>
								<?php esc_html_e( 'Enable this mapping.', 'verifycate' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button( null === $editing ? __( 'Add mapping', 'verifycate' ) : __( 'Save mapping', 'verifycate' ) ); ?>

				<?php if ( null !== $editing ) : ?>
					<a class="button button-link" href="<?php echo esc_url( add_query_arg( 'page', 'verifycate-mappings', admin_url( 'admin.php' ) ) ); ?>">
						<?php esc_html_e( 'Cancel editing', 'verifycate' ); ?>
					</a>
				<?php endif; ?>
			</form>

			<div class="verifycate-card verifycate-help">
				<h2><?php esc_html_e( 'Template tokens', 'verifycate' ); ?></h2>
				<p><?php esc_html_e( 'Templates are filled in when the certificate is issued. Unknown tokens are left as-is so typos are visible.', 'verifycate' ); ?></p>
				<ul class="verifycate-token-list">
					<?php foreach ( AttributeMapper::availableTokens() as $token => $label ) : ?>
						<li><code><?php echo esc_html( $token ); ?></code> <?php echo esc_html( $label ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Source select value for a mapping.
	 *
	 * @param Mapping|null $mapping Mapping.
	 * @return string
	 */
	private function sourceValue( ?Mapping $mapping ): string {
		if ( null === $mapping ) {
			return '';
		}

		return $mapping->getIntegration() . ':' . $mapping->getCourseId();
	}

	/**
	 * Human-readable course label.
	 *
	 * @param Mapping $mapping Mapping.
	 * @return string
	 */
	private function courseLabel( Mapping $mapping ): string {
		$integration = $this->integrations->get( $mapping->getIntegration() );
		$name        = null === $integration ? $mapping->getIntegration() : $integration->getLabel();

		if ( 'custom' === $mapping->getIntegration() ) {
			$event = '' === $mapping->getEvent() ? 'course_completed' : $mapping->getEvent();

			return sprintf(
				/* translators: 1: event name, 2: course id or "any". */
				__( 'Custom hook: %1$s (course %2$s)', 'verifycate' ),
				$event,
				$mapping->getCourseId() > 0 ? (string) $mapping->getCourseId() : __( 'any', 'verifycate' )
			);
		}

		if ( 0 === $mapping->getCourseId() ) {
			return sprintf(
				/* translators: %s: LMS name. */
				__( 'Any %s course', 'verifycate' ),
				$name
			);
		}

		$title = get_the_title( $mapping->getCourseId() );
		$title = is_string( $title ) && '' !== $title ? $title : '#' . $mapping->getCourseId();

		return sprintf(
			/* translators: 1: LMS name, 2: course title. */
			__( '%1$s: %2$s', 'verifycate' ),
			$name,
			$title
		);
	}

	/**
	 * Human-readable certificate label.
	 *
	 * @param Mapping $mapping Mapping.
	 * @return string
	 */
	private function certificateLabel( Mapping $mapping ): string {
		return '' !== $mapping->getCertificateName() ? $mapping->getCertificateName() : $mapping->getCertificateId();
	}

	/**
	 * Format attribute templates for the textarea.
	 *
	 * @param array<string, string> $attributes Attributes.
	 * @return string
	 */
	private function formatAttributes( array $attributes ): string {
		$lines = array();

		foreach ( $attributes as $key => $template ) {
			$lines[] = $key . ' = ' . $template;
		}

		return implode( "\n", $lines );
	}
}
