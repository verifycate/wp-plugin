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
use Verifycate\Support\Links;
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
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( (string) $_GET['action'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen state.
		$editing_id       = isset( $_GET['mapping'] ) ? absint( wp_unslash( (string) $_GET['mapping'] ) ) : 0;
		$is_editor        = in_array( $action, array( 'new', 'edit' ), true ) || $editing_id > 0;
		$editing          = $editing_id > 0 ? $this->mappings->find( $editing_id ) : null;
		$catalog          = $this->catalog->all();
		$active_catalog   = array_values(
			array_filter(
				$catalog,
				static function ( array $certificate ): bool {
					return 'active' === $certificate['status'];
				}
			)
		);
		$auto_certificate = ( null === $editing && 1 === count( $active_catalog ) ) ? $active_catalog[0]['id'] : '';
		$trigger_event    = $editing ? $editing->getEvent() : Mapping::EVENT_COURSE_COMPLETED;
		$trigger_event    = in_array( $trigger_event, array( Mapping::EVENT_COURSE_COMPLETED, Mapping::EVENT_QUIZ_PASSED, Mapping::EVENT_LESSON_COMPLETED ), true )
			? $trigger_event
			: Mapping::EVENT_COURSE_COMPLETED;
		$score_min        = $editing ? $editing->getScoreMin() : 0.0;
		$trigger_id       = $editing ? $editing->getTriggerId() : 0;
		$available_lms    = $this->integrations->active();
		$current_lms      = $editing ? $editing->getIntegration() : '';
		$known_lms        = '' !== $current_lms ? $this->integrations->get( $current_lms ) : null;

		if ( null !== $known_lms ) {
			$available_lms[ $current_lms ] = $known_lms;
		}

		if ( '' === $current_lms || ! isset( $available_lms[ $current_lms ] ) ) {
			$current_lms = (string) array_key_first( $available_lms );
		}

		$object_value    = $editing ? (string) $editing->getCourseId() . ':' . (string) $trigger_id : '0:0';
		$mappings_url    = add_query_arg( 'page', 'verifycate-mappings', admin_url( 'admin.php' ) );
		$new_mapping_url = add_query_arg(
			array(
				'page'   => 'verifycate-mappings',
				'action' => 'new',
			),
			admin_url( 'admin.php' )
		);

		$search      = '';
		$paged       = 1;
		$per_page    = 20;
		$total       = 0;
		$total_pages = 1;
		$orderby     = 'id';
		$order       = 'desc';
		$mappings    = array();

		if ( ! $is_editor ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen state.
			$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['s'] ) ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen state.
			$paged = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( (string) $_GET['paged'] ) ) ) : 1;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen state.
			$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( (string) $_GET['orderby'] ) ) : 'id';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen state.
			$order = isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( (string) $_GET['order'] ) ) ) ? 'asc' : 'desc';

			$allowed_orderby = array( 'id', 'course', 'certificate', 'status', 'repeat', 'release' );
			if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
				$orderby = 'id';
			}

			$total       = $this->mappings->count( $search );
			$total_pages = max( 1, (int) ceil( $total / $per_page ) );
			$paged       = min( $paged, $total_pages );
			$mappings    = $this->mappings->paginate( $paged, $per_page, $search, $orderby, $order );
		}

		$attribute_values      = $editing ? $editing->getAttributes() : array();
		$attribute_certificate = $editing ? $editing->getCertificateId() : $auto_certificate;
		$attribute_defs        = '' !== $attribute_certificate ? $this->catalog->attributes( $attribute_certificate ) : array();

		Notices::render();
		?>
		<div class="wrap verifycate-wrap">
			<?php if ( $is_editor ) : ?>
				<h1><?php echo null === $editing ? esc_html__( 'Add Certificate Mapping', 'verifycate' ) : esc_html__( 'Edit Certificate Mapping', 'verifycate' ); ?></h1>
			<?php else : ?>
				<div class="verifycate-heading-row">
					<h1><?php esc_html_e( 'Certificate Mappings', 'verifycate' ); ?></h1>
					<a class="button button-primary verifycate-add-button" href="<?php echo esc_url( $new_mapping_url ); ?>">
						<?php esc_html_e( 'Add Mapping', 'verifycate' ); ?>
						<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
					</a>
				</div>
				<p class="verifycate-lede">
					<?php esc_html_e( 'Choose the LMS, completion trigger and certificate. One mapping can cover an entire catalogue or a group of items with the “Any” options.', 'verifycate' ); ?>
				</p>
			<?php endif; ?>

			<?php if ( ! $this->settings->isConnected() ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<?php
						echo wp_kses_post(
							sprintf(
								/* translators: %s: settings link. */
								esc_html__( 'Connect your workspace before issuing certificates. %s', 'verifycate' ),
								'<a href="' . esc_url( add_query_arg( 'page', 'verifycate', admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Open Get started', 'verifycate' ) . '</a>'
							)
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( ! $is_editor ) : ?>
				<?php if ( $total > 0 || '' !== $search ) : ?>
					<div class="verifycate-table-toolbar">
						<?php if ( $total > 0 ) : ?>
							<div class="verifycate-bulk-actions">
								<label class="screen-reader-text" for="verifycate-bulk-action"><?php esc_html_e( 'Select bulk action', 'verifycate' ); ?></label>
								<select id="verifycate-bulk-action" name="bulk_action" form="verifycate-bulk-form">
									<option value="-1"><?php esc_html_e( 'Bulk actions', 'verifycate' ); ?></option>
									<option value="delete"><?php esc_html_e( 'Delete permanently', 'verifycate' ); ?></option>
								</select>
								<button type="submit" class="button" form="verifycate-bulk-form"><?php esc_html_e( 'Apply', 'verifycate' ); ?></button>
							</div>
						<?php endif; ?>
						<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="verifycate-search-form">
							<input type="hidden" name="page" value="verifycate-mappings" />
							<?php if ( 'id' !== $orderby ) : ?>
								<input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>" />
								<input type="hidden" name="order" value="<?php echo esc_attr( $order ); ?>" />
							<?php endif; ?>
							<label class="screen-reader-text" for="verifycate-mapping-search"><?php esc_html_e( 'Search certificate mappings', 'verifycate' ); ?></label>
							<input type="search" id="verifycate-mapping-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search course or certificate…', 'verifycate' ); ?>" />
							<button type="submit" class="button"><?php esc_html_e( 'Search', 'verifycate' ); ?></button>
							<?php if ( '' !== $search ) : ?>
								<a class="button button-link" href="<?php echo esc_url( $mappings_url ); ?>"><?php esc_html_e( 'Clear', 'verifycate' ); ?></a>
							<?php endif; ?>
						</form>
					</div>
				<?php endif; ?>

				<?php if ( $total === 0 ) : ?>
					<?php if ( '' !== $search ) : ?>
						<div class="verifycate-empty verifycate-empty--search">
							<span class="dashicons dashicons-search" aria-hidden="true"></span>
							<h2><?php esc_html_e( 'No mappings found', 'verifycate' ); ?></h2>
							<p>
								<?php
								printf(
									/* translators: %s: search term. */
									esc_html__( 'No certificate mappings match “%s”.', 'verifycate' ),
									esc_html( $search )
								);
								?>
							</p>
							<a class="button" href="<?php echo esc_url( $mappings_url ); ?>"><?php esc_html_e( 'Clear search', 'verifycate' ); ?></a>
						</div>
					<?php else : ?>
						<div class="verifycate-empty">
							<span class="dashicons dashicons-awards" aria-hidden="true"></span>
							<h2><?php esc_html_e( 'Map your first course', 'verifycate' ); ?></h2>
							<p><?php esc_html_e( 'Choose the LMS, trigger and course, quiz or lesson to issue a certificate for.', 'verifycate' ); ?></p>
							<a class="button button-primary verifycate-add-button" href="<?php echo esc_url( $new_mapping_url ); ?>">
								<?php esc_html_e( 'Add your first mapping', 'verifycate' ); ?>
								<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
							</a>
						</div>
					<?php endif; ?>
				<?php else : ?>
					<form id="verifycate-bulk-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="verifycate_bulk_mappings" />
						<?php wp_nonce_field( 'verifycate_bulk_mappings' ); ?>

						<table class="wp-list-table widefat fixed striped verifycate-table">
							<thead>
								<tr>
									<th scope="col" class="manage-column column-cb check-column">
										<label class="screen-reader-text" for="verifycate-select-all"><?php esc_html_e( 'Select all mappings', 'verifycate' ); ?></label>
										<input id="verifycate-select-all" type="checkbox" />
									</th>
									<?php $this->renderSortableHeader( 'course', __( 'Course', 'verifycate' ), $orderby, $order, $search ); ?>
									<?php $this->renderSortableHeader( 'certificate', __( 'Certificate Name', 'verifycate' ), $orderby, $order, $search ); ?>
									<th scope="col"><?php esc_html_e( 'Trigger', 'verifycate' ); ?></th>
									<?php $this->renderSortableHeader( 'status', __( 'Status', 'verifycate' ), $orderby, $order, $search ); ?>
									<th scope="col"><?php esc_html_e( 'Attributes', 'verifycate' ); ?></th>
									<?php $this->renderSortableHeader( 'repeat', __( 'Repeat completions', 'verifycate' ), $orderby, $order, $search ); ?>
									<?php $this->renderSortableHeader( 'release', __( 'Automatic release', 'verifycate' ), $orderby, $order, $search ); ?>
									<th scope="col"><?php esc_html_e( 'Actions', 'verifycate' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $mappings as $mapping ) : ?>
									<?php
									$mapping_id = $mapping->getId();
									$edit_url   = add_query_arg(
										array(
											'page'    => 'verifycate-mappings',
											'action'  => 'edit',
											'mapping' => $mapping_id,
										),
										admin_url( 'admin.php' )
									);
									?>
									<tr>
										<th scope="row" class="check-column">
											<input
												type="checkbox"
												id="verifycate-mapping-<?php echo esc_attr( (string) $mapping_id ); ?>"
												name="mapping_ids[]"
												value="<?php echo esc_attr( (string) $mapping_id ); ?>"
												aria-label="<?php echo esc_attr( sprintf( /* translators: %s: course label. */ __( 'Select %s', 'verifycate' ), $this->courseLabel( $mapping ) ) ); ?>"
											/>
										</th>
										<td><?php echo esc_html( $this->courseLabel( $mapping ) ); ?></td>
										<td>
											<?php echo esc_html( $this->certificateLabel( $mapping ) ); ?><br />
											<code><?php echo esc_html( $mapping->getCertificateId() ); ?></code>
										</td>
										<td><?php echo esc_html( $this->triggerLabel( $mapping ) ); ?></td>
										<td>
											<span class="verifycate-pill <?php echo $mapping->isEnabled() ? 'is-on' : 'is-off'; ?>">
												<?php echo $mapping->isEnabled() ? esc_html__( 'Enabled', 'verifycate' ) : esc_html__( 'Disabled', 'verifycate' ); ?>
											</span>
										</td>
										<td><?php echo esc_html( (string) count( $mapping->getAttributes() ) ); ?></td>
										<td>
											<?php
											echo Mapping::DUPLICATE_REISSUE === $mapping->getDuplicatePolicy()
												? esc_html__( 'Issue again', 'verifycate' )
												: esc_html__( 'Skip duplicates', 'verifycate' );
											?>
										</td>
										<td><?php echo $mapping->shouldAutoIssue() ? esc_html__( 'Yes', 'verifycate' ) : esc_html__( 'No', 'verifycate' ); ?></td>
										<td class="verifycate-row-actions">
											<a class="button button-small" href="<?php echo esc_url( $edit_url ); ?>">
												<?php esc_html_e( 'Edit', 'verifycate' ); ?>
											</a>
											<button
												type="submit"
												form="verifycate-delete-<?php echo esc_attr( (string) $mapping_id ); ?>"
												class="button button-small verifycate-row-delete"
											>
												<?php esc_html_e( 'Delete', 'verifycate' ); ?>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</form>

					<?php foreach ( $mappings as $mapping ) : ?>
						<form
							id="verifycate-delete-<?php echo esc_attr( (string) $mapping->getId() ); ?>"
							method="post"
							action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
							class="verifycate-delete-mapping verifycate-delete-mapping--hidden"
						>
							<input type="hidden" name="action" value="verifycate_delete_mapping" />
							<input type="hidden" name="mapping_id" value="<?php echo esc_attr( (string) $mapping->getId() ); ?>" />
							<?php wp_nonce_field( 'verifycate_delete_mapping' ); ?>
						</form>
					<?php endforeach; ?>

					<?php if ( $total_pages > 1 ) : ?>
						<div class="tablenav">
							<div class="tablenav-pages">
								<span class="displaying-num">
									<?php
									printf(
										/* translators: %s: mapping count. */
										esc_html( _n( '%s mapping', '%s mappings', $total, 'verifycate' ) ),
										esc_html( number_format_i18n( $total ) )
									);
									?>
								</span>
								<?php
								$pagination_args = array(
									'page'  => 'verifycate-mappings',
									'paged' => '%#%',
								);

								if ( '' !== $search ) {
									$pagination_args['s'] = $search;
								}

								if ( 'id' !== $orderby ) {
									$pagination_args['orderby'] = $orderby;
									$pagination_args['order']   = $order;
								}

								$pagination = paginate_links(
									array(
										'base'      => add_query_arg( $pagination_args, admin_url( 'admin.php' ) ),
										'format'    => '',
										'current'   => $paged,
										'total'     => $total_pages,
										'prev_text' => '‹',
										'next_text' => '›',
										'type'      => 'plain',
									)
								);

								if ( is_string( $pagination ) && '' !== $pagination ) {
									echo wp_kses_post( $pagination );
								}
								?>
							</div>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( $is_editor ) : ?>
			<p class="verifycate-lede">
				<?php esc_html_e( 'Choose the completion and the certificate to issue. Options on the right control repeat completions and release.', 'verifycate' ); ?>
			</p>

			<div class="verifycate-editor-layout">
				<div class="verifycate-editor-main">
					<form id="verifycate-save-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="verifycate-form">
						<input type="hidden" name="action" value="verifycate_save_mapping" />
						<input type="hidden" name="mapping_id" value="<?php echo esc_attr( (string) ( $editing ? $editing->getId() : 0 ) ); ?>" />
						<?php wp_nonce_field( 'verifycate_save_mapping' ); ?>

						<details class="verifycate-panel" open>
							<summary class="verifycate-panel-header">
								<span class="verifycate-panel-title"><?php esc_html_e( 'Issue Criteria', 'verifycate' ); ?></span>
								<span class="verifycate-panel-toggle" aria-hidden="true"></span>
							</summary>
							<div class="verifycate-panel-body">
								<div class="verifycate-field">
									<label class="verifycate-field-label" for="verifycate-lms"><?php esc_html_e( 'LMS plugin', 'verifycate' ); ?></label>
									<div class="verifycate-combobox" data-combobox>
										<select id="verifycate-lms" name="lms" class="regular-text verifycate-combobox__native" data-search-placeholder="<?php esc_attr_e( 'Search LMS plugins…', 'verifycate' ); ?>" data-empty="<?php esc_attr_e( 'No matching plugins.', 'verifycate' ); ?>">
											<?php foreach ( $available_lms as $lms_id => $lms ) : ?>
												<option value="<?php echo esc_attr( (string) $lms_id ); ?>" <?php selected( $current_lms, (string) $lms_id ); ?>>
													<?php echo 'custom' === $lms_id ? esc_html__( 'Custom hook', 'verifycate' ) : esc_html( $lms->getLabel() ); ?>
												</option>
											<?php endforeach; ?>
										</select>
										<div class="verifycate-combobox__control" hidden>
											<input type="text" class="verifycate-combobox__input" role="combobox" aria-expanded="false" aria-autocomplete="list" aria-controls="verifycate-lms-list" autocomplete="off" />
											<button type="button" class="verifycate-combobox__toggle" aria-label="<?php esc_attr_e( 'Show options', 'verifycate' ); ?>">▾</button>
											<ul class="verifycate-combobox__list" id="verifycate-lms-list" role="listbox" hidden></ul>
										</div>
									</div>
									<p class="description">
										<?php esc_html_e( 'Choose the plugin that reports the completion.', 'verifycate' ); ?>
									</p>
								</div>

								<div class="verifycate-custom-fields" <?php echo 'custom' === $current_lms ? '' : 'hidden'; ?>>
									<div class="verifycate-field">
										<label class="verifycate-field-label" for="verifycate-custom-event"><?php esc_html_e( 'Custom event name', 'verifycate' ); ?></label>
										<input
											type="text"
											id="verifycate-custom-event"
											name="custom_event"
											value="<?php echo esc_attr( $editing ? $editing->getEvent() : '' ); ?>"
											placeholder="course_completed"
											class="regular-text"
										/>
									</div>
									<div class="verifycate-field">
										<label class="verifycate-field-label" for="verifycate-custom-course"><?php esc_html_e( 'Course ID', 'verifycate' ); ?></label>
										<input
											type="number"
											id="verifycate-custom-course"
											name="custom_course_id"
											value="<?php echo esc_attr( (string) ( $editing && 'custom' === $editing->getIntegration() ? $editing->getCourseId() : 0 ) ); ?>"
											min="0"
											class="small-text"
										/>
										<p class="description">
											<?php esc_html_e( 'Leave the course ID at 0 to match any custom completion.', 'verifycate' ); ?>
										</p>
									</div>
									<p class="description">
										<?php esc_html_e( 'The event name must match the third argument of do_action( \'verifycate_course_completed\', … ).', 'verifycate' ); ?>
									</p>
								</div>

								<div class="verifycate-trigger-fields" <?php echo 'custom' === $current_lms ? 'hidden' : ''; ?>>
									<div class="verifycate-field">
										<label class="verifycate-field-label" for="verifycate-trigger"><?php esc_html_e( 'Trigger', 'verifycate' ); ?></label>
										<div class="verifycate-combobox" data-combobox>
											<select id="verifycate-trigger" name="trigger" class="regular-text verifycate-combobox__native" data-search-placeholder="<?php esc_attr_e( 'Search triggers…', 'verifycate' ); ?>" data-empty="<?php esc_attr_e( 'No matching triggers.', 'verifycate' ); ?>">
												<option value="<?php echo esc_attr( Mapping::EVENT_COURSE_COMPLETED ); ?>" <?php selected( $trigger_event, Mapping::EVENT_COURSE_COMPLETED ); ?>>
													<?php esc_html_e( 'Course completed', 'verifycate' ); ?>
												</option>
												<option value="<?php echo esc_attr( Mapping::EVENT_QUIZ_PASSED ); ?>" <?php selected( $trigger_event, Mapping::EVENT_QUIZ_PASSED ); ?>>
													<?php esc_html_e( 'Quiz passed', 'verifycate' ); ?>
												</option>
												<option value="<?php echo esc_attr( Mapping::EVENT_LESSON_COMPLETED ); ?>" <?php selected( $trigger_event, Mapping::EVENT_LESSON_COMPLETED ); ?>>
													<?php esc_html_e( 'Lesson completed', 'verifycate' ); ?>
												</option>
											</select>
											<div class="verifycate-combobox__control" hidden>
												<input type="text" class="verifycate-combobox__input" role="combobox" aria-expanded="false" aria-autocomplete="list" aria-controls="verifycate-trigger-list" autocomplete="off" />
												<button type="button" class="verifycate-combobox__toggle" aria-label="<?php esc_attr_e( 'Show options', 'verifycate' ); ?>">▾</button>
												<ul class="verifycate-combobox__list" id="verifycate-trigger-list" role="listbox" hidden></ul>
											</div>
										</div>
									</div>

									<div class="verifycate-field">
										<label class="verifycate-field-label" id="verifycate-object-label" for="verifycate-object"><?php esc_html_e( 'Course', 'verifycate' ); ?></label>
										<div class="verifycate-combobox" data-combobox>
											<select id="verifycate-object" name="object" class="regular-text verifycate-combobox__native" data-selected="<?php echo esc_attr( $object_value ); ?>" data-search-placeholder="<?php esc_attr_e( 'Search objects…', 'verifycate' ); ?>" data-empty="<?php esc_attr_e( 'No matching objects.', 'verifycate' ); ?>">
												<option value=""><?php esc_html_e( 'Loading options…', 'verifycate' ); ?></option>
											</select>
											<div class="verifycate-combobox__control" hidden>
												<input type="text" class="verifycate-combobox__input" role="combobox" aria-expanded="false" aria-autocomplete="list" aria-controls="verifycate-object-list" autocomplete="off" />
												<button type="button" class="verifycate-combobox__toggle" aria-label="<?php esc_attr_e( 'Show options', 'verifycate' ); ?>">▾</button>
												<ul class="verifycate-combobox__list" id="verifycate-object-list" role="listbox" hidden></ul>
											</div>
										</div>
										<p class="description">
											<?php esc_html_e( 'Select the course, quiz or lesson. “Any” options cover multiple items.', 'verifycate' ); ?>
										</p>
									</div>

									<div class="verifycate-field verifycate-trigger-score" <?php echo Mapping::EVENT_QUIZ_PASSED === $trigger_event ? '' : 'hidden'; ?>>
										<label class="verifycate-field-label" for="verifycate-score-min"><?php esc_html_e( 'Minimum quiz score (%)', 'verifycate' ); ?></label>
										<input
											type="number"
											id="verifycate-score-min"
											name="score_min"
											value="<?php echo esc_attr( (string) $score_min ); ?>"
											min="0"
											max="100"
											step="0.01"
											class="small-text"
										/>
										<p class="description">
											<?php esc_html_e( 'Leave 0 to issue on any passing quiz.', 'verifycate' ); ?>
										</p>
									</div>
								</div>
							</div>
						</details>

						<details class="verifycate-panel" open>
							<summary class="verifycate-panel-header">
								<span class="verifycate-panel-title"><?php esc_html_e( 'Certificate', 'verifycate' ); ?></span>
								<span class="verifycate-panel-toggle" aria-hidden="true"></span>
							</summary>
							<div class="verifycate-panel-body">
								<div class="verifycate-field">
									<label class="verifycate-field-label" for="verifycate-certificate"><?php esc_html_e( 'Issue this certificate', 'verifycate' ); ?></label>
									<?php if ( $catalog !== array() && $active_catalog === array() ) : ?>
										<div class="notice notice-warning inline">
											<p><?php esc_html_e( 'No active certificates. Publish one in Verifycate before issuing.', 'verifycate' ); ?></p>
										</div>
									<?php endif; ?>
									<?php if ( $catalog === array() ) : ?>
										<div class="notice notice-warning inline">
											<p><?php esc_html_e( 'The certificate list could not be loaded. Check the Configure page and try again.', 'verifycate' ); ?></p>
										</div>
									<?php endif; ?>
									<div class="verifycate-combobox" data-combobox>
										<select id="verifycate-certificate" name="certificate_id" class="regular-text verifycate-combobox__native" data-search-placeholder="<?php esc_attr_e( 'Search certificates…', 'verifycate' ); ?>" data-empty="<?php esc_attr_e( 'No matching certificates.', 'verifycate' ); ?>" data-required="1" required>
											<option value=""><?php esc_html_e( 'Select a certificate…', 'verifycate' ); ?></option>
											<?php if ( $catalog === array() && $editing && '' !== $editing->getCertificateId() ) : ?>
												<option value="<?php echo esc_attr( $editing->getCertificateId() ); ?>" selected>
													<?php echo esc_html( '' !== $editing->getCertificateName() ? $editing->getCertificateName() : $editing->getCertificateId() ); ?>
												</option>
											<?php endif; ?>
											<?php
											$found_current = false;
											foreach ( $catalog as $certificate ) {
												$current  = $editing && $editing->getCertificateId() === $certificate['id'];
												$selected = $editing ? $current : ( '' !== $auto_certificate && $auto_certificate === $certificate['id'] );
												if ( $current ) {
													$found_current = true;
												}
												?>
												<option value="<?php echo esc_attr( $certificate['id'] ); ?>" <?php selected( $selected ); ?>>
													<?php echo esc_html( $certificate['name'] ); ?>
													(<?php echo esc_html( $certificate['status'] ); ?>)
												</option>
												<?php
											}
											if ( $editing && ! $found_current && $catalog !== array() ) :
												?>
												<option value="<?php echo esc_attr( $editing->getCertificateId() ); ?>" selected>
													<?php echo esc_html( '' !== $editing->getCertificateName() ? $editing->getCertificateName() : $editing->getCertificateId() ); ?>
												</option>
											<?php endif; ?>
										</select>
										<div class="verifycate-combobox__control" hidden>
											<input type="text" class="verifycate-combobox__input" role="combobox" aria-expanded="false" aria-autocomplete="list" aria-controls="verifycate-certificate-list" autocomplete="off" />
											<button type="button" class="verifycate-combobox__toggle" aria-label="<?php esc_attr_e( 'Show options', 'verifycate' ); ?>">▾</button>
											<ul class="verifycate-combobox__list" id="verifycate-certificate-list" role="listbox" hidden></ul>
										</div>
									</div>
									<p class="description">
										<?php esc_html_e( 'Draft certificates queue recipients but cannot be released until the certificate is published in Verifycate.', 'verifycate' ); ?>
									</p><p class="verifycate-link-row">
										<a href="<?php echo esc_url( Links::newCertificateUrl( $this->settings ) ); ?>" target="_blank" rel="noopener noreferrer">
											<?php esc_html_e( 'Create a certificate in Verifycate', 'verifycate' ); ?>
										</a>
									</p>
								</div>
							</div>
						</details>

						<details class="verifycate-panel" open>
							<summary class="verifycate-panel-header">
								<span class="verifycate-panel-title"><?php esc_html_e( 'Certificate attributes', 'verifycate' ); ?></span>
								<span class="verifycate-panel-toggle" aria-hidden="true"></span>
							</summary>
							<div class="verifycate-panel-body">
								<?php $this->renderAttributeField( $attribute_certificate, $attribute_defs, $attribute_values ); ?>
							</div>
						</details>
					</form>
				</div>

				<aside class="verifycate-editor-sidebar" aria-label="<?php esc_attr_e( 'Mapping actions', 'verifycate' ); ?>">
					<div class="verifycate-panel verifycate-publish-panel">
						<div class="verifycate-panel-header verifycate-panel-header--static">
							<span class="verifycate-panel-title"><?php esc_html_e( 'Publish', 'verifycate' ); ?></span>
						</div>
						<div class="verifycate-panel-body">
							<div class="verifycate-sidebar-section">
								<div class="verifycate-field">
									<div class="verifycate-field-label-row">
										<label class="verifycate-field-label" for="verifycate-duplicate-policy"><?php esc_html_e( 'Repeat completions', 'verifycate' ); ?></label>
										<span class="verifycate-info" tabindex="0" aria-describedby="verifycate-repeat-help">
											<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
											<span class="verifycate-tooltip" id="verifycate-repeat-help" role="tooltip"><?php esc_html_e( 'Skip issues once per learner. Issue again supports recertification.', 'verifycate' ); ?></span>
										</span>
									</div>
									<select id="verifycate-duplicate-policy" name="duplicate_policy" form="verifycate-save-form">
										<option value="skip" <?php selected( $editing ? $editing->getDuplicatePolicy() : Mapping::DUPLICATE_SKIP, Mapping::DUPLICATE_SKIP ); ?>>
											<?php esc_html_e( 'Skip duplicates', 'verifycate' ); ?>
										</option>
										<option value="reissue" <?php selected( $editing ? $editing->getDuplicatePolicy() : '', Mapping::DUPLICATE_REISSUE ); ?>>
											<?php esc_html_e( 'Issue again', 'verifycate' ); ?>
										</option>
									</select>
								</div>

								<div class="verifycate-toggle-row">
									<div class="verifycate-toggle-row__label">
										<div class="verifycate-field-label-row">
											<label class="verifycate-field-label" for="verifycate-auto-issue"><?php esc_html_e( 'Automatic release', 'verifycate' ); ?></label>
											<span class="verifycate-info" tabindex="0" aria-describedby="verifycate-release-help">
												<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
												<span class="verifycate-tooltip" id="verifycate-release-help" role="tooltip"><?php esc_html_e( 'When enabled, certificates are issued immediately after a recipient is imported. When disabled, recipients are queued for manual release from Verifycate.', 'verifycate' ); ?></span>
											</span>
										</div>
									</div>
									<label class="verifycate-toggle" for="verifycate-auto-issue">
										<input
											type="checkbox"
											id="verifycate-auto-issue"
											class="verifycate-toggle__input"
											name="auto_issue"
											value="1"
											form="verifycate-save-form"
											<?php checked( $editing ? $editing->shouldAutoIssue() : true ); ?>
										/>
										<span class="verifycate-toggle__track" aria-hidden="true"></span>
									</label>
								</div>

								<div class="verifycate-toggle-row">
									<div class="verifycate-toggle-row__label">
										<div class="verifycate-field-label-row">
											<label class="verifycate-field-label" for="verifycate-enabled"><?php esc_html_e( 'Status', 'verifycate' ); ?></label>
										</div>
									</div>
									<label class="verifycate-toggle" for="verifycate-enabled">
										<input
											type="checkbox"
											id="verifycate-enabled"
											class="verifycate-toggle__input"
											name="enabled"
											value="1"
											form="verifycate-save-form"
											<?php checked( $editing ? $editing->isEnabled() : true ); ?>
										/>
										<span class="verifycate-toggle__track" aria-hidden="true"></span>
									</label>
								</div>
							</div>

							<div class="verifycate-sidebar-section">
								<dl class="verifycate-meta-list">
									<div>
										<dt><?php esc_html_e( 'Mapping ID', 'verifycate' ); ?></dt>
										<dd><?php echo null === $editing ? esc_html__( 'New', 'verifycate' ) : '#' . esc_html( (string) $editing->getId() ); ?></dd>
									</div>
									<?php if ( null !== $editing ) : ?>
										<div>
											<dt><?php esc_html_e( 'Created', 'verifycate' ); ?></dt>
											<dd><?php echo esc_html( $this->formatTimestamp( $editing->getCreatedAt() ) ); ?></dd>
										</div>
										<div>
											<dt><?php esc_html_e( 'Last updated', 'verifycate' ); ?></dt>
											<dd><?php echo esc_html( $this->formatTimestamp( $editing->getUpdatedAt() ) ); ?></dd>
										</div>
									<?php else : ?>
										<div>
											<dt><?php esc_html_e( 'Timestamps', 'verifycate' ); ?></dt>
											<dd><?php esc_html_e( 'Recorded on save', 'verifycate' ); ?></dd>
										</div>
									<?php endif; ?>
								</dl>
							</div>
						</div>

						<div class="verifycate-panel-footer">
							<div class="verifycate-publish-actions">
								<?php if ( null !== $editing ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="verifycate-delete-mapping verifycate-editor-delete">
										<input type="hidden" name="action" value="verifycate_delete_mapping" />
										<input type="hidden" name="mapping_id" value="<?php echo esc_attr( (string) $editing->getId() ); ?>" />
										<?php wp_nonce_field( 'verifycate_delete_mapping' ); ?>
										<button type="submit" class="button-link-delete verifycate-delete-button">
											<?php esc_html_e( 'Delete', 'verifycate' ); ?>
										</button>
									</form>
								<?php endif; ?>
								<button type="submit" form="verifycate-save-form" class="button button-primary">
									<?php echo null === $editing ? esc_html__( 'Add mapping', 'verifycate' ) : esc_html__( 'Save mapping', 'verifycate' ); ?>
								</button>
							</div>
						</div>
					</div>
				</aside>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the primary attribute-mapping field.
	 *
	 * @param string                                        $attribute_certificate Selected certificate id.
	 * @param array<int, array{key: string, label: string}> $attribute_defs Attribute definitions.
	 * @param array<string, string>                         $attribute_values Current values.
	 * @return void
	 */
	private function renderAttributeField( string $attribute_certificate, array $attribute_defs, array $attribute_values ): void {
		?>
		<div class="verifycate-field verifycate-field--attributes">
			<div class="verifycate-field-heading">
				<label class="verifycate-field-label" for="verifycate-attributes"><?php esc_html_e( 'Attribute mapping', 'verifycate' ); ?></label>
				<div class="verifycate-view-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Attribute mapping view', 'verifycate' ); ?>" data-attribute-view-tabs>
					<button type="button" role="tab" id="verifycate-attribute-tab-visual" aria-controls="verifycate-attribute-panel-visual" aria-selected="true" tabindex="0">
						<?php esc_html_e( 'Visual', 'verifycate' ); ?>
					</button>
					<button type="button" role="tab" id="verifycate-attribute-tab-raw" aria-controls="verifycate-attribute-panel-raw" aria-selected="false" tabindex="-1">
						<?php esc_html_e( 'Raw', 'verifycate' ); ?>
					</button>
				</div>
			</div>

			<div id="verifycate-attribute-panel-visual" role="tabpanel" aria-labelledby="verifycate-attribute-tab-visual">
				<div class="verifycate-attribute-rows" data-attribute-rows>
					<?php if ( '' === $attribute_certificate ) : ?>
						<p class="description"><?php esc_html_e( 'Choose a certificate to load its attributes.', 'verifycate' ); ?></p>
					<?php elseif ( $attribute_defs === array() ) : ?>
						<p class="description"><?php esc_html_e( 'This certificate has no declared attributes. Add them in Verifycate, then reload this page.', 'verifycate' ); ?></p>
					<?php else : ?>
						<?php $this->renderAttributeRows( $attribute_defs, $attribute_values ); ?>
					<?php endif; ?>
				</div>
				<p class="description"><?php esc_html_e( 'Only attributes declared on the certificate are sent.', 'verifycate' ); ?></p>
			</div>

			<div id="verifycate-attribute-panel-raw" role="tabpanel" aria-labelledby="verifycate-attribute-tab-raw" hidden>
				<textarea
					id="verifycate-attributes"
					name="attributes"
					rows="6"
					class="large-text code verifycate-raw-attributes"
					placeholder="course_hours = {course_meta:duration}"
				><?php echo esc_textarea( $this->formatAttributes( $attribute_values ) ); ?></textarea>
				<p class="description">
					<?php esc_html_e( 'One attribute per line: key = value. The visual mapper keeps this in sync.', 'verifycate' ); ?>
				</p>
			</div>

			<p class="description">
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: 1: user meta token, 2: course meta token, 3: context token. */
						esc_html__( 'Need a value from metadata? Use %1$s, %2$s or %3$s.', 'verifycate' ),
						'<code>{user_meta:key}</code>',
						'<code>{course_meta:key}</code>',
						'<code>{context:key}</code>'
					)
				);
				?>
			</p>

			<details class="verifycate-token-help">
				<summary><?php esc_html_e( 'Token reference', 'verifycate' ); ?></summary>
				<p><?php esc_html_e( 'Tokens are filled in when the certificate is issued. Unknown tokens are left as-is so typos are visible.', 'verifycate' ); ?></p>
				<ul class="verifycate-token-list">
					<?php foreach ( AttributeMapper::availableTokens() as $token => $label ) : ?>
						<li><code><?php echo esc_html( $token ); ?></code> <?php echo esc_html( $label ); ?></li>
					<?php endforeach; ?>
				</ul>
			</details>
		</div>
		<?php
	}

	/**
	 * Format a stored UTC timestamp for display.
	 *
	 * @param string $timestamp UTC MySQL timestamp.
	 * @return string
	 */
	private function formatTimestamp( string $timestamp ): string {
		if ( '' === $timestamp || 0 === strpos( $timestamp, '0000-00-00' ) ) {
			return '—';
		}

		$format = (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' );
		$date   = get_date_from_gmt( $timestamp, $format );

		return is_string( $date ) && '' !== $date ? $date : '—';
	}
	/**
	 * Render a sortable list-table header.
	 *
	 * @param string $key Sort key.
	 * @param string $label Header label.
	 * @param string $orderby Current sort key.
	 * @param string $order Current sort direction.
	 * @param string $search Current search term.
	 * @return void
	 */
	private function renderSortableHeader( string $key, string $label, string $orderby, string $order, string $search ): void {
		$is_sorted  = $key === $orderby;
		$next_order = $is_sorted && 'asc' === $order ? 'desc' : 'asc';
		$class      = $is_sorted ? 'sorted ' . $order : 'sortable ' . $next_order;
		$args       = array(
			'page'    => 'verifycate-mappings',
			'orderby' => $key,
			'order'   => $next_order,
		);

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$url       = add_query_arg( $args, admin_url( 'admin.php' ) );
		$aria_sort = $is_sorted ? ( 'asc' === $order ? 'ascending' : 'descending' ) : 'none';
		?>
		<th scope="col" class="manage-column <?php echo esc_attr( $class ); ?>" aria-sort="<?php echo esc_attr( $aria_sort ); ?>">
			<a href="<?php echo esc_url( $url ); ?>">
				<span><?php echo esc_html( $label ); ?></span>
				<span class="sorting-indicator" aria-hidden="true"></span>
			</a>
		</th>
		<?php
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
	 * Human-readable trigger label.
	 *
	 * @param Mapping $mapping Mapping.
	 * @return string
	 */
	private function triggerLabel( Mapping $mapping ): string {
		if ( 'custom' === $mapping->getIntegration() ) {
			return sprintf(
				/* translators: %s: custom event name. */
				__( 'Custom: %s', 'verifycate' ),
				$mapping->getEvent()
			);
		}

		switch ( $mapping->getEvent() ) {
			case Mapping::EVENT_QUIZ_PASSED:
				$label = __( 'Quiz passed', 'verifycate' );
				break;
			case Mapping::EVENT_LESSON_COMPLETED:
				$label = __( 'Lesson completed', 'verifycate' );
				break;
			case Mapping::EVENT_COURSE_COMPLETED:
				$label = __( 'Course completed', 'verifycate' );
				break;
			default:
				$label = $mapping->getEvent();
		}

		if ( $mapping->getTriggerId() > 0 ) {
			$title = get_the_title( $mapping->getTriggerId() );
			$title = is_string( $title ) && '' !== $title ? $title : '#' . $mapping->getTriggerId();

			return sprintf(
				/* translators: 1: trigger name, 2: quiz or lesson title. */
				__( '%1$s — %2$s', 'verifycate' ),
				$label,
				$title
			);
		}

		return $label;
	}

	/**
	 * Render one row per declared certificate attribute.
	 *
	 * @param array<int, array{key: string, label: string}> $definitions Attribute definitions.
	 * @param array<string, string>                         $values Current templates.
	 * @return void
	 */
	private function renderAttributeRows( array $definitions, array $values ): void {
		foreach ( $definitions as $definition ) {
			$key   = isset( $definition['key'] ) ? (string) $definition['key'] : '';
			$label = isset( $definition['label'] ) ? (string) $definition['label'] : $key;

			if ( '' === $key ) {
				continue;
			}

			$value = isset( $values[ $key ] ) ? $values[ $key ] : '';
			$id    = 'verifycate-attribute-' . sanitize_html_class( $key );
			?>
			<div class="verifycate-attribute-row" data-attribute-key="<?php echo esc_attr( $key ); ?>">
				<div class="verifycate-attribute-row__head">
					<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
					<code><?php echo esc_html( $key ); ?></code>
					<button type="button" class="button-link verifycate-attribute-row__clear" data-clear-attribute><?php esc_html_e( 'Clear', 'verifycate' ); ?></button>
				</div>

				<input
					type="text"
					id="<?php echo esc_attr( $id ); ?>"
					class="large-text verifycate-attribute-row__input"
					value="<?php echo esc_attr( $value ); ?>"
					placeholder="<?php esc_attr_e( 'e.g. {course_title}', 'verifycate' ); ?>"
					data-attribute-input
				/>

				<?php
				$primary_tokens = array(
					'{user_name}'       => __( 'Learner name', 'verifycate' ),
					'{user_email}'      => __( 'Email', 'verifycate' ),
					'{course_title}'    => __( 'Course title', 'verifycate' ),
					'{completion_date}' => __( 'Date', 'verifycate' ),
					'{score}'           => __( 'Score', 'verifycate' ),
				);
				?>
				<div class="verifycate-attribute-row__tokens">
					<span class="verifycate-attribute-row__tokens-label"><?php esc_html_e( 'Insert token:', 'verifycate' ); ?></span>
					<?php foreach ( $primary_tokens as $token => $token_label ) : ?>
						<button type="button" class="verifycate-token" data-token="<?php echo esc_attr( $token ); ?>" title="<?php echo esc_attr( $token ); ?>">
							<?php echo esc_html( $token_label ); ?>
						</button>
					<?php endforeach; ?>
					<select class="verifycate-token-more" data-token-more aria-label="<?php esc_attr_e( 'More tokens', 'verifycate' ); ?>">
						<option value=""><?php esc_html_e( 'More tokens…', 'verifycate' ); ?></option>
						<?php foreach ( AttributeMapper::availableTokens() as $token => $token_label ) : ?>
							<?php if ( isset( $primary_tokens[ $token ] ) || false !== strpos( $token, ':' ) ) : ?>
								<?php continue; ?>
							<?php endif; ?>
							<option value="<?php echo esc_attr( $token ); ?>"><?php echo esc_html( $token_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<p class="description verifycate-attribute-row__preview">
					<?php esc_html_e( 'Preview:', 'verifycate' ); ?>
					<span data-preview>—</span>
				</p>
			</div>
			<?php
		}
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
