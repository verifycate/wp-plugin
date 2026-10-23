<?php
/**
 * Completion-to-mapping routing.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Issuance;

use Verifycate\Support\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Matches completion events to mappings and queues issuance.
 */
final class IssuanceService {

	/**
	 * Mapping repository.
	 *
	 * @var MappingRepository
	 */
	private MappingRepository $mappings;

	/**
	 * Queue.
	 *
	 * @var Queue
	 */
	private Queue $queue;

	/**
	 * Constructor.
	 *
	 * @param MappingRepository $mappings Mapping repository.
	 * @param Queue             $queue Queue.
	 */
	public function __construct( MappingRepository $mappings, Queue $queue ) {
		$this->mappings = $mappings;
		$this->queue    = $queue;
	}

	/**
	 * Handle a completion event from an LMS adapter.
	 *
	 * @param CompletionEvent $event Event.
	 * @return void
	 */
	public function handle( CompletionEvent $event ): void {
		if ( '' === $event->getEmail() ) {
			Logger::warning(
				'Completion ignored because the learner has no email address.',
				array(
					'integration' => $event->getIntegration(),
					'user'        => $event->getUserId(),
				)
			);

			return;
		}

		$mappings = $this->mappings->matching( $event->getIntegration(), $event->getCourseId(), $event->getEvent(), $event->getTriggerId() );

		foreach ( $mappings as $mapping ) {
			if ( $mapping->getScoreMin() > 0 ) {
				$score = $event->getScore();
				if ( null === $score || $score < $mapping->getScoreMin() ) {
					Logger::info(
						'Completion skipped because the quiz score is below the mapping threshold.',
						array(
							'mapping'   => $mapping->getId(),
							'user'      => $event->getUserId(),
							'course'    => $event->getCourseId(),
							'score'     => $score,
							'score_min' => $mapping->getScoreMin(),
						)
					);
					continue;
				}
			}

			/**
			 * Filter whether a mapping should issue for this completion.
			 *
			 * @param bool            $should_issue Whether to queue the mapping.
			 * @param Mapping         $mapping Mapping.
			 * @param CompletionEvent $event Completion event.
			 */
			if ( ! apply_filters( 'verifycate_should_issue', true, $mapping, $event ) ) {
				continue;
			}

			$dedupe = Queue::dedupeKey( $mapping, $event );

			if ( Mapping::DUPLICATE_SKIP === $mapping->getDuplicatePolicy() ) {
				if ( $this->queue->hasCompleted( $dedupe ) || $this->queue->hasOpen( $dedupe ) ) {
					Logger::info(
						'Completion skipped because this learner already has the certificate or a job is queued.',
						array(
							'mapping' => $mapping->getId(),
							'user'    => $event->getUserId(),
							'course'  => $event->getCourseId(),
						)
					);
					continue;
				}
			} elseif ( $this->queue->hasOpen( $dedupe ) ) {
				Logger::info(
					'Completion skipped because a job is already queued for this learner and certificate.',
					array(
						'mapping' => $mapping->getId(),
						'user'    => $event->getUserId(),
					)
				);
				continue;
			}

			$job_id = $this->queue->enqueue( $mapping, $event );

			if ( $job_id > 0 ) {
				/**
				 * Fires after a completion is queued.
				 *
				 * @param int             $job_id Job id.
				 * @param Mapping         $mapping Mapping.
				 * @param CompletionEvent $event Completion event.
				 */
				do_action( 'verifycate_issue_queued', $job_id, $mapping, $event );
			}
		}
	}
}
