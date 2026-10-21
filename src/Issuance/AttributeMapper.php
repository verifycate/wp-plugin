<?php
/**
 * Attribute template rendering.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Issuance;

defined( 'ABSPATH' ) || exit;

/**
 * Turns admin-authored templates into recipient attribute values.
 */
final class AttributeMapper {

	/**
	 * Render every template for one event.
	 *
	 * @param array<string, string> $templates Attribute templates.
	 * @param CompletionEvent       $event Event.
	 * @return array<string, string>
	 */
	public function render( array $templates, CompletionEvent $event ): array {
		$values = array();

		foreach ( $templates as $key => $template ) {
			$values[ (string) $key ] = $this->renderTemplate( (string) $template, $event );
		}

		return $values;
	}

	/**
	 * Render one template.
	 *
	 * @param string          $template Template.
	 * @param CompletionEvent $event Event.
	 * @return string
	 */
	public function renderTemplate( string $template, CompletionEvent $event ): string {
		$user = $event->getUser();

		$template = preg_replace_callback(
			'/\{(user_meta|course_meta|context):([a-zA-Z0-9_\-]+)\}/',
			function ( array $matches ) use ( $event, $user ): string {
				$key = $matches[2];

				if ( 'context' === $matches[1] ) {
					$context = $event->getContext();
					$value   = $context[ $key ] ?? '';
				} elseif ( 'user_meta' === $matches[1] ) {
					$value = null === $user ? '' : get_user_meta( (int) $user->ID, $key, true );
				} else {
					$value = $event->getCourseId() > 0 ? get_post_meta( $event->getCourseId(), $key, true ) : '';
				}

				return $this->stringify( $value );
			},
			$template
		);

		if ( ! is_string( $template ) ) {
			return '';
		}

		$score = $event->getScore();

		$tokens = array(
			'{user_name}'           => null === $user ? '' : (string) $user->display_name,
			'{user_email}'          => null === $user ? '' : (string) $user->user_email,
			'{user_login}'          => null === $user ? '' : (string) $user->user_login,
			'{user_id}'             => (string) $event->getUserId(),
			'{course_title}'        => $event->getCourseTitle(),
			'{course_id}'           => (string) $event->getCourseId(),
			'{completion_date}'     => date_i18n( (string) get_option( 'date_format' ), $event->getCompletedAt()->getTimestamp() ),
			'{completion_date_iso}' => $event->getCompletedAt()->format( 'Y-m-d' ),
			'{score}'               => null === $score ? '' : (string) $score,
			'{site_name}'           => (string) get_bloginfo( 'name' ),
			'{site_url}'            => home_url( '/' ),
		);

		$rendered = strtr( $template, $tokens );

		return sanitize_text_field( $rendered );
	}

	/**
	 * Tokens shown in the mapping editor.
	 *
	 * @return array<string, string>
	 */
	public static function availableTokens(): array {
		return array(
			'{user_name}'           => __( 'Learner display name', 'verifycate' ),
			'{user_email}'          => __( 'Learner email address', 'verifycate' ),
			'{user_login}'          => __( 'Learner username', 'verifycate' ),
			'{user_id}'             => __( 'WordPress user ID', 'verifycate' ),
			'{course_title}'        => __( 'Course title', 'verifycate' ),
			'{course_id}'           => __( 'Course ID', 'verifycate' ),
			'{completion_date}'     => __( 'Completion date (site format)', 'verifycate' ),
			'{completion_date_iso}' => __( 'Completion date (YYYY-MM-DD)', 'verifycate' ),
			'{score}'               => __( 'Score, when the LMS provides one', 'verifycate' ),
			'{site_name}'           => __( 'Site name', 'verifycate' ),
			'{site_url}'            => __( 'Site URL', 'verifycate' ),
			'{user_meta:key}'       => __( 'Any user meta value', 'verifycate' ),
			'{course_meta:key}'     => __( 'Any course meta value', 'verifycate' ),
			'{context:key}'         => __( 'A value passed by a custom hook', 'verifycate' ),
		);
	}

	/**
	 * Convert arbitrary meta values into strings.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private function stringify( $value ): string {
		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		if ( is_array( $value ) || is_object( $value ) ) {
			$encoded = wp_json_encode( $value );

			return is_string( $encoded ) ? $encoded : '';
		}

		return '';
	}
}
