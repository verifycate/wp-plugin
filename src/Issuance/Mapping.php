<?php
/**
 * A course-to-certificate mapping.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Issuance;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable mapping value object.
 */
final class Mapping {

	public const DUPLICATE_SKIP = 'skip';

	public const DUPLICATE_REISSUE = 'reissue';

	public const EVENT_COURSE_COMPLETED = 'course_completed';

	public const EVENT_QUIZ_PASSED = 'quiz_passed';

	public const EVENT_LESSON_COMPLETED = 'lesson_completed';

	/**
	 * Row id.
	 *
	 * @var int
	 */
	private int $id;

	/**
	 * Integration id.
	 *
	 * @var string
	 */
	private string $integration;

	/**
	 * Custom event name; empty matches any event.
	 *
	 * @var string
	 */
	private string $event;

	/**
	 * Quiz, lesson or other trigger object id; 0 matches any object.
	 *
	 * @var int
	 */
	private int $triggerId;

	/**
	 * Minimum quiz score percentage; 0 means any passing score.
	 *
	 * @var float
	 */
	private float $scoreMin;

	/**
	 * Course id; 0 matches any course in the integration.
	 *
	 * @var int
	 */
	private int $courseId;

	/**
	 * Verifycate certificate id.
	 *
	 * @var string
	 */
	private string $certificateId;

	/**
	 * Certificate name at save time.
	 *
	 * @var string
	 */
	private string $certificateName;

	/**
	 * Attribute templates keyed by attribute key.
	 *
	 * @var array<string, string>
	 */
	private array $attributes;

	/**
	 * Whether to release the issue immediately.
	 *
	 * @var bool
	 */
	private bool $autoIssue;

	/**
	 * Duplicate policy.
	 *
	 * @var string
	 */
	private string $duplicatePolicy;

	/**
	 * Whether the mapping is active.
	 *
	 * @var bool
	 */
	private bool $enabled;

	/**
	 * Created timestamp.
	 *
	 * @var string
	 */
	private string $createdAt;

	/**
	 * Updated timestamp.
	 *
	 * @var string
	 */
	private string $updatedAt;

	/**
	 * Constructor.
	 *
	 * @param int                   $id Row id.
	 * @param string                $integration Integration id.
	 * @param string                $event Event name.
	 * @param int                   $courseId Course id.
	 * @param string                $certificateId Certificate id.
	 * @param string                $certificateName Certificate name.
	 * @param array<string, string> $attributes Attribute templates.
	 * @param bool                  $autoIssue Auto-issue.
	 * @param string                $duplicatePolicy Duplicate policy.
	 * @param bool                  $enabled Enabled.
	 * @param string                $createdAt Created timestamp.
	 * @param string                $updatedAt Updated timestamp.
	 * @param int                   $triggerId Trigger object id.
	 * @param float                 $scoreMin Minimum quiz score.
	 */
	public function __construct(
		int $id,
		string $integration,
		string $event,
		int $courseId,
		string $certificateId,
		string $certificateName = '',
		array $attributes = array(),
		bool $autoIssue = true,
		string $duplicatePolicy = self::DUPLICATE_SKIP,
		bool $enabled = true,
		string $createdAt = '',
		string $updatedAt = '',
		int $triggerId = 0,
		float $scoreMin = 0.0
	) {
		$this->id              = $id;
		$this->integration     = $integration;
		$this->event           = $event;
		$this->triggerId       = max( 0, $triggerId );
		$this->scoreMin        = max( 0.0, min( 100.0, $scoreMin ) );
		$this->courseId        = $courseId;
		$this->certificateId   = $certificateId;
		$this->certificateName = $certificateName;
		$this->attributes      = $attributes;
		$this->autoIssue       = $autoIssue;
		$this->duplicatePolicy = in_array( $duplicatePolicy, array( self::DUPLICATE_SKIP, self::DUPLICATE_REISSUE ), true )
			? $duplicatePolicy
			: self::DUPLICATE_SKIP;
		$this->enabled         = $enabled;
		$this->createdAt       = $createdAt;
		$this->updatedAt       = $updatedAt;
	}

	/**
	 * Build from a database row.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return self
	 */
	public static function fromRow( array $row ): self {
		return self::fromArray( $row );
	}

	/**
	 * Build from a row or a queue snapshot.
	 *
	 * @param array<string, mixed> $row Row or snapshot.
	 * @return self
	 */
	public static function fromArray( array $row ): self {
		$attributes = array();
		if ( isset( $row['attributes'] ) && is_array( $row['attributes'] ) ) {
			foreach ( $row['attributes'] as $key => $value ) {
				if ( is_scalar( $value ) ) {
					$attributes[ (string) $key ] = (string) $value;
				}
			}
		} elseif ( isset( $row['attributes'] ) && is_string( $row['attributes'] ) && '' !== $row['attributes'] ) {
			$decoded = json_decode( $row['attributes'], true );
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $key => $value ) {
					if ( is_scalar( $value ) ) {
						$attributes[ (string) $key ] = (string) $value;
					}
				}
			}
		}

		return new self(
			isset( $row['id'] ) ? (int) $row['id'] : 0,
			isset( $row['integration'] ) && is_scalar( $row['integration'] ) ? (string) $row['integration'] : '',
			isset( $row['event'] ) && is_scalar( $row['event'] ) ? (string) $row['event'] : '',
			isset( $row['course_id'] ) ? (int) $row['course_id'] : 0,
			isset( $row['certificate_id'] ) && is_scalar( $row['certificate_id'] ) ? (string) $row['certificate_id'] : '',
			isset( $row['certificate_name'] ) && is_scalar( $row['certificate_name'] ) ? (string) $row['certificate_name'] : '',
			$attributes,
			! empty( $row['auto_issue'] ),
			isset( $row['duplicate_policy'] ) && is_scalar( $row['duplicate_policy'] ) ? (string) $row['duplicate_policy'] : self::DUPLICATE_SKIP,
			! empty( $row['enabled'] ),
			isset( $row['created_at'] ) && is_scalar( $row['created_at'] ) ? (string) $row['created_at'] : '',
			isset( $row['updated_at'] ) && is_scalar( $row['updated_at'] ) ? (string) $row['updated_at'] : '',
			isset( $row['trigger_id'] ) ? (int) $row['trigger_id'] : 0,
			isset( $row['score_min'] ) ? (float) $row['score_min'] : 0.0
		);
	}

	/**
	 * Serialize for the queue snapshot.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'id'               => $this->id,
			'integration'      => $this->integration,
			'event'            => $this->event,
			'trigger_id'       => $this->triggerId,
			'score_min'        => $this->scoreMin,
			'course_id'        => $this->courseId,
			'certificate_id'   => $this->certificateId,
			'certificate_name' => $this->certificateName,
			'attributes'       => $this->attributes,
			'auto_issue'       => $this->autoIssue,
			'duplicate_policy' => $this->duplicatePolicy,
			'enabled'          => $this->enabled,
			'created_at'       => $this->createdAt,
			'updated_at'       => $this->updatedAt,
		);
	}

	/**
	 * Whether this mapping applies to an event.
	 *
	 * @param string $integration Integration id.
	 * @param int    $courseId Course id.
	 * @param string $event Event name.
	 * @param int    $triggerId Trigger object id.
	 * @return bool
	 */
	public function matches( string $integration, int $courseId, string $event, int $triggerId = 0 ): bool {
		if ( $this->integration !== $integration ) {
			return false;
		}

		if ( 0 !== $this->courseId && $this->courseId !== $courseId ) {
			return false;
		}

		if ( '' !== $this->event && $this->event !== $event ) {
			return false;
		}

		if ( 0 !== $this->triggerId && $this->triggerId !== $triggerId ) {
			return false;
		}

		return true;
	}

	/**
	 * Row id.
	 *
	 * @return int
	 */
	public function getId(): int {
		return $this->id;
	}

	/**
	 * Integration id.
	 *
	 * @return string
	 */
	public function getIntegration(): string {
		return $this->integration;
	}

	/**
	 * Event name.
	 *
	 * @return string
	 */
	public function getEvent(): string {
		return $this->event;
	}

	/**
	 * Trigger object id.
	 *
	 * @return int
	 */
	public function getTriggerId(): int {
		return $this->triggerId;
	}

	/**
	 * Minimum quiz score percentage.
	 *
	 * @return float
	 */
	public function getScoreMin(): float {
		return $this->scoreMin;
	}

	/**
	 * Course id.
	 *
	 * @return int
	 */
	public function getCourseId(): int {
		return $this->courseId;
	}

	/**
	 * Certificate id.
	 *
	 * @return string
	 */
	public function getCertificateId(): string {
		return $this->certificateId;
	}

	/**
	 * Certificate name.
	 *
	 * @return string
	 */
	public function getCertificateName(): string {
		return $this->certificateName;
	}

	/**
	 * Attribute templates.
	 *
	 * @return array<string, string>
	 */
	public function getAttributes(): array {
		return $this->attributes;
	}

	/**
	 * Whether to release the issue immediately.
	 *
	 * @return bool
	 */
	public function shouldAutoIssue(): bool {
		return $this->autoIssue;
	}

	/**
	 * Duplicate policy.
	 *
	 * @return string
	 */
	public function getDuplicatePolicy(): string {
		return $this->duplicatePolicy;
	}

	/**
	 * Whether the mapping is active.
	 *
	 * @return bool
	 */
	public function isEnabled(): bool {
		return $this->enabled;
	}

	/**
	 * Created timestamp.
	 *
	 * @return string
	 */
	public function getCreatedAt(): string {
		return $this->createdAt;
	}

	/**
	 * Updated timestamp.
	 *
	 * @return string
	 */
	public function getUpdatedAt(): string {
		return $this->updatedAt;
	}
}
