<?php
/**
 * Issuance queue worker.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Issuance;

use Verifycate\Api\ClientFactory;
use Verifycate\Sdk\Exception\ApiException;
use Verifycate\Sdk\Exception\TransportException;
use Verifycate\Support\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Processes queued issuance jobs.
 */
final class Worker {

	/**
	 * Transient used to keep concurrent cron runs apart.
	 */
	private const LOCK = 'verifycate_queue_lock';

	/**
	 * Client factory.
	 *
	 * @var ClientFactory
	 */
	private ClientFactory $factory;

	/**
	 * Queue.
	 *
	 * @var Queue
	 */
	private Queue $queue;

	/**
	 * Attribute mapper.
	 *
	 * @var AttributeMapper
	 */
	private AttributeMapper $mapper;

	/**
	 * Constructor.
	 *
	 * @param ClientFactory   $factory Client factory.
	 * @param Queue           $queue Queue.
	 * @param AttributeMapper $mapper Attribute mapper.
	 */
	public function __construct( ClientFactory $factory, Queue $queue, AttributeMapper $mapper ) {
		$this->factory = $factory;
		$this->queue   = $queue;
		$this->mapper  = $mapper;
	}

	/**
	 * Process due jobs.
	 *
	 * @param int $limit Maximum jobs.
	 * @return void
	 */
	public function run( int $limit = 10 ): void {
		if ( get_transient( self::LOCK ) ) {
			return;
		}

		set_transient( self::LOCK, 1, 2 * MINUTE_IN_SECONDS );

		try {
			$jobs = $this->queue->claim( $limit );

			foreach ( $jobs as $job ) {
				$this->process( $job );
			}

			$counts = $this->queue->counts();
			if ( $counts['pending'] > 0 || $counts['processing'] > 0 ) {
				$this->queue->scheduleSoon();
			}
		} finally {
			delete_transient( self::LOCK );
		}
	}

	/**
	 * Process one claimed job.
	 *
	 * @param array<string, mixed> $job Job row.
	 * @return void
	 */
	public function process( array $job ): void {
		$id      = isset( $job['id'] ) ? (int) $job['id'] : 0;
		$payload = isset( $job['payload'] ) && is_string( $job['payload'] ) ? json_decode( $job['payload'], true ) : null;

		if ( $id <= 0 || ! is_array( $payload ) ) {
			if ( $id > 0 ) {
				$this->queue->fail( $id, __( 'The queued job payload is invalid.', 'verifycate' ), false );
			}

			return;
		}

		$mapping_data = isset( $payload['mapping'] ) && is_array( $payload['mapping'] ) ? $payload['mapping'] : array();
		$event_data   = isset( $payload['event'] ) && is_array( $payload['event'] ) ? $payload['event'] : array();
		$mapping      = Mapping::fromArray( $mapping_data );
		$event        = CompletionEvent::fromArray( $event_data );

		if ( '' === $mapping->getCertificateId() ) {
			$this->queue->fail( $id, __( 'The mapping has no certificate.', 'verifycate' ), false );

			return;
		}

		if ( '' === $event->getEmail() ) {
			$this->queue->fail( $id, __( 'The learner no longer has a valid email address.', 'verifycate' ), false );

			return;
		}

		if ( ! $this->factory->isConfigured() ) {
			$this->queue->fail( $id, __( 'Verifycate is not connected. Add a workspace API key.', 'verifycate' ), false );

			return;
		}

		try {
			$client = $this->factory->make();
			$issue  = $this->resolveIssue( $client, $mapping, $event );

			if ( null === $issue ) {
				Logger::info(
					'Recipient already holds the certificate; nothing to issue.',
					array(
						'mapping' => $mapping->getId(),
						'email'   => $event->getEmail(),
					)
				);
				$this->queue->complete( $id );

				return;
			}

			$this->queue->complete(
				$id,
				array(
					'issue_id'  => $issue->getId(),
					'public_id' => $issue->getPublicId(),
				)
			);

			Logger::info(
				'Certificate issued from a queued completion.',
				array(
					'mapping' => $mapping->getId(),
					'issue'   => $issue->getId(),
					'public'  => $issue->getPublicId(),
					'status'  => $issue->getStatus(),
					'email'   => $event->getEmail(),
					'course'  => $event->getCourseId(),
				)
			);
		} catch ( ApiException $exception ) {
			$retryable = $exception->isRetryable() || in_array( $exception->getStatusCode(), array( 401, 402, 403 ), true );
			$this->queue->fail( $id, $this->describeApiError( $exception ), $retryable, $exception->getRetryAfter() );

			Logger::error(
				'Certificate issuance failed.',
				array(
					'mapping' => $mapping->getId(),
					'status'  => $exception->getStatusCode(),
					'code'    => $exception->getErrorCode(),
					'error'   => $exception->getMessage(),
					'retry'   => $retryable,
				)
			);
		} catch ( TransportException $exception ) {
			$this->queue->fail( $id, $exception->getMessage(), true );

			Logger::warning(
				'Certificate issuance could not reach the API.',
				array(
					'mapping' => $mapping->getId(),
					'error'   => $exception->getMessage(),
				)
			);
		} catch ( \Throwable $exception ) {
			$this->queue->fail( $id, $exception->getMessage(), false );

			Logger::error(
				'Unexpected certificate issuance failure.',
				array(
					'mapping' => $mapping->getId(),
					'error'   => $exception->getMessage(),
				)
			);
		}
	}

	/**
	 * Resolve the issue for a job, creating or reissuing as the mapping asks.
	 *
	 * @param \Verifycate\Sdk\Client $client API client.
	 * @param Mapping                $mapping Mapping snapshot.
	 * @param CompletionEvent        $event Event.
	 * @return \Verifycate\Sdk\Dto\CertificateIssue|null Null when the recipient already holds the certificate.
	 * @throws \RuntimeException When the API accepts an import without returning an issue.
	 */
	private function resolveIssue( \Verifycate\Sdk\Client $client, Mapping $mapping, CompletionEvent $event ): ?\Verifycate\Sdk\Dto\CertificateIssue {
		$existing = null;

		// Recertification needs the existing issue so it can be reissued.
		if ( Mapping::DUPLICATE_REISSUE === $mapping->getDuplicatePolicy() ) {
			$existing = $this->findIssue( $client, $mapping->getCertificateId(), $event->getEmail() );
		}

		if ( null !== $existing ) {
			return $this->releaseExisting( $client, $mapping, $existing );
		}

		$attributes = $this->mapper->render( $mapping->getAttributes(), $event );
		$recipient  = array(
			'email' => $event->getEmail(),
			'name'  => $event->getName(),
		);

		if ( $attributes !== array() ) {
			$recipient['attributes'] = $attributes;
		}

		$result = $client->issues()->import( $mapping->getCertificateId(), array( $recipient ) );
		$issue  = $result->getFirstIssue();

		if ( null === $issue && $result->hasRejections() ) {
			// A previous attempt may have created the issue after a failed release.
			$existing = $this->findIssue( $client, $mapping->getCertificateId(), $event->getEmail() );

			return null === $existing ? null : $this->releaseExisting( $client, $mapping, $existing );
		}

		if ( null === $issue ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not rendered as HTML.
			throw new \RuntimeException( __( 'The API accepted the import but returned no issue.', 'verifycate' ) );
		}

		if ( $mapping->shouldAutoIssue() && $issue->isPending() ) {
			return $client->issues()->issue( $issue->getId() );
		}

		return $issue;
	}

	/**
	 * Release an existing issue.
	 *
	 * @param \Verifycate\Sdk\Client               $client API client.
	 * @param Mapping                              $mapping Mapping snapshot.
	 * @param \Verifycate\Sdk\Dto\CertificateIssue $issue Existing issue.
	 * @return \Verifycate\Sdk\Dto\CertificateIssue|null
	 */
	private function releaseExisting(
		\Verifycate\Sdk\Client $client,
		Mapping $mapping,
		\Verifycate\Sdk\Dto\CertificateIssue $issue
	): ?\Verifycate\Sdk\Dto\CertificateIssue {
		if ( Mapping::DUPLICATE_REISSUE === $mapping->getDuplicatePolicy() ) {
			return $issue->isPending()
				? $client->issues()->issue( $issue->getId() )
				: $client->issues()->reissue( $issue->getId() );
		}

		if ( $issue->isPending() && $mapping->shouldAutoIssue() ) {
			return $client->issues()->issue( $issue->getId() );
		}

		return null;
	}

	/**
	 * Find a recipient's issue for a certificate.
	 *
	 * @param \Verifycate\Sdk\Client $client API client.
	 * @param string                 $certificate_id Certificate id.
	 * @param string                 $email Recipient email.
	 * @return \Verifycate\Sdk\Dto\CertificateIssue|null
	 */
	private function findIssue( \Verifycate\Sdk\Client $client, string $certificate_id, string $email ): ?\Verifycate\Sdk\Dto\CertificateIssue {
		$page = $client->issues()->listForCertificate( $certificate_id, array( 'search' => $email ), 50 );

		foreach ( $page->getItems() as $candidate ) {
			if ( 0 === strcasecmp( $candidate->getRecipientEmail(), $email ) ) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * Turn a draft-certificate rejection into an actionable message.
	 *
	 * @param ApiException $exception API error.
	 * @return string
	 */
	private function describeApiError( ApiException $exception ): string {
		$detail = $exception->getDetail();

		if ( $exception->isPaymentRequired() ) {
			return __( 'The workspace needs an active plan before certificates can be issued.', 'verifycate' );
		}

		if ( $exception->isUnauthorized() ) {
			return __( 'The workspace API key was rejected. Reconnect Verifycate in Settings.', 'verifycate' );
		}

		if ( $exception->isForbidden() ) {
			return __( 'The API key does not have permission to issue certificates.', 'verifycate' );
		}

		if ( '' !== $detail ) {
			return $detail;
		}

		return $exception->getMessage();
	}
}
