<?php
/**
 * Normalized completion event.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Issuance;

defined( 'ABSPATH' ) || exit;

/**
 * A course, lesson or custom completion normalized across LMS plugins.
 */
final class CompletionEvent {

	/**
	 * Integration id, e.g. learndash or custom.
	 *
	 * @var string
	 */
	private string $integration;

	/**
	 * Event name, e.g. course_completed.
	 *
	 * @var string
	 */
	private string $event;

	/**
	 * WordPress user id.
	 *
	 * @var int
	 */
	private int $userId;

	/**
	 * Course or object id.
	 *
	 * @var int
	 */
	private int $courseId;

	/**
	 * Course title at completion time.
	 *
	 * @var string
	 */
	private string $courseTitle;

	/**
	 * Optional score.
	 *
	 * @var float|null
	 */
	private ?float $score;

	/**
	 * Completion timestamp (UTC).
	 *
	 * @var \DateTimeImmutable
	 */
	private \DateTimeImmutable $completedAt;

	/**
	 * Extra adapter-specific data.
	 *
	 * @var array<string, mixed>
	 */
	private array $context;

	/**
	 * Trigger object id: quiz, lesson or other source object.
	 *
	 * @var int
	 */
	private int $triggerId;

	/**
	 * Constructor.
	 *
	 * @param string               $integration Integration id.
	 * @param string               $event Event name.
	 * @param int                  $userId User id.
	 * @param int                  $courseId Course id.
	 * @param string               $courseTitle Course title.
	 * @param float|null           $score Optional score.
	 * @param \DateTimeImmutable   $completedAt Completion time.
	 * @param array<string, mixed> $context Extra data.
	 * @param int                  $triggerId Trigger object id.
	 */
	public function __construct(
		string $integration,
		string $event,
		int $userId,
		int $courseId,
		string $courseTitle = '',
		?float $score = null,
		?\DateTimeImmutable $completedAt = null,
		array $context = array(),
		int $triggerId = 0
	) {
		$this->integration = $integration;
		$this->event       = '' === $event ? 'course_completed' : $event;
		$this->userId      = $userId;
		$this->courseId    = $courseId;
		$this->courseTitle = $courseTitle;
		$this->score       = $score;
		$this->completedAt = $completedAt ?? new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
		$this->context     = $context;
		$this->triggerId   = max( 0, $triggerId );
	}

	/**
	 * Create an event from an adapter.
	 *
	 * @param string               $integration Integration id.
	 * @param string               $event Event name.
	 * @param int                  $userId User id.
	 * @param int                  $courseId Course id.
	 * @param string               $courseTitle Course title.
	 * @param float|null           $score Optional score.
	 * @param array<string, mixed> $context Extra data.
	 * @param int                  $triggerId Trigger object id.
	 * @return self
	 */
	public static function create(
		string $integration,
		string $event,
		int $userId,
		int $courseId,
		string $courseTitle = '',
		?float $score = null,
		array $context = array(),
		int $triggerId = 0
	): self {
		return new self( $integration, $event, $userId, $courseId, $courseTitle, $score, null, $context, $triggerId );
	}

	/**
	 * Rehydrate a queued event.
	 *
	 * @param array<string, mixed> $data Serialized event.
	 * @return self
	 */
	public static function fromArray( array $data ): self {
		$completed = isset( $data['completed_at'] ) && is_string( $data['completed_at'] )
			? new \DateTimeImmutable( $data['completed_at'] )
			: new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );

		$context = isset( $data['context'] ) && is_array( $data['context'] ) ? $data['context'] : array();

		return new self(
			isset( $data['integration'] ) && is_scalar( $data['integration'] ) ? (string) $data['integration'] : '',
			isset( $data['event'] ) && is_scalar( $data['event'] ) ? (string) $data['event'] : '',
			isset( $data['user_id'] ) && is_numeric( $data['user_id'] ) ? (int) $data['user_id'] : 0,
			isset( $data['course_id'] ) && is_numeric( $data['course_id'] ) ? (int) $data['course_id'] : 0,
			isset( $data['course_title'] ) && is_scalar( $data['course_title'] ) ? (string) $data['course_title'] : '',
			isset( $data['score'] ) && is_numeric( $data['score'] ) ? (float) $data['score'] : null,
			$completed,
			$context,
			isset( $data['trigger_id'] ) && is_numeric( $data['trigger_id'] ) ? (int) $data['trigger_id'] : 0
		);
	}

	/**
	 * Serialize for the queue.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'integration'  => $this->integration,
			'event'        => $this->event,
			'trigger_id'   => $this->triggerId,
			'user_id'      => $this->userId,
			'course_id'    => $this->courseId,
			'course_title' => $this->courseTitle,
			'score'        => $this->score,
			'completed_at' => $this->completedAt->format( DATE_ATOM ),
			'context'      => $this->context,
		);
	}

	/**
	 * Stable key for duplicate suppression.
	 *
	 * @return string
	 */
	public function getDedupeKey(): string {
		return md5( implode( '|', array( $this->integration, $this->event, (string) $this->userId, (string) $this->courseId ) ) );
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
	 * WordPress user id.
	 *
	 * @return int
	 */
	public function getUserId(): int {
		return $this->userId;
	}

	/**
	 * Course or object id.
	 *
	 * @return int
	 */
	public function getCourseId(): int {
		return $this->courseId;
	}

	/**
	 * Course title.
	 *
	 * @return string
	 */
	public function getCourseTitle(): string {
		return $this->courseTitle;
	}

	/**
	 * Optional score.
	 *
	 * @return float|null
	 */
	public function getScore(): ?float {
		return $this->score;
	}

	/**
	 * Completion timestamp.
	 *
	 * @return \DateTimeImmutable
	 */
	public function getCompletedAt(): \DateTimeImmutable {
		return $this->completedAt;
	}

	/**
	 * Adapter-specific context.
	 *
	 * @return array<string, mixed>
	 */
	public function getContext(): array {
		return $this->context;
	}

	/**
	 * WordPress user, when the id still resolves.
	 *
	 * @return \WP_User|null
	 */
	public function getUser(): ?\WP_User {
		if ( $this->userId <= 0 ) {
			return null;
		}

		$user = get_user_by( 'id', $this->userId );

		return $user instanceof \WP_User ? $user : null;
	}

	/**
	 * Recipient email.
	 *
	 * @return string
	 */
	public function getEmail(): string {
		$user = $this->getUser();

		return null === $user ? '' : (string) $user->user_email;
	}

	/**
	 * Recipient display name.
	 *
	 * @return string
	 */
	public function getName(): string {
		$user = $this->getUser();

		return null === $user ? '' : (string) $user->display_name;
	}
}
