<?php
/**
 * Tests for attribute templates.
 *
 * @package Verifycate
 */

declare( strict_types=1 );

namespace Verifycate\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Verifycate\Issuance\AttributeMapper;
use Verifycate\Issuance\CompletionEvent;

/**
 * @covers \Verifycate\Issuance\AttributeMapper
 */
final class AttributeMapperTest extends TestCase {

	/**
	 * Set up WordPress function mocks.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'get_user_by' )->justReturn( new \WP_User( 7, 'ada@example.com', 'Ada Lovelace', 'ada' ) );
		Functions\when( 'get_user_meta' )->justReturn( 'Gold' );
		Functions\when( 'get_post_meta' )->justReturn( '40 hours' );
		Functions\when( 'get_option' )->justReturn( 'Y-m-d' );
		Functions\when( 'date_i18n' )->alias(
			static function ( string $format, int $timestamp ): string {
				return gmdate( $format, $timestamp );
			}
		);
		Functions\when( 'get_bloginfo' )->justReturn( 'Acme Academy' );
		Functions\when( 'home_url' )->justReturn( 'https://acme.test' );
		Functions\when( 'sanitize_text_field' )->alias(
			static function ( string $value ): string {
				return trim( $value );
			}
		);
	}

	/**
	 * Tear down mocks.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Renders the standard token set.
	 */
	public function test_renders_tokens(): void {
		$event = new CompletionEvent(
			'learndash',
			'course_completed',
			7,
			42,
			'Leadership Essentials',
			92.5,
			new \DateTimeImmutable( '2026-05-01T10:00:00Z' ),
			array( 'cohort' => 'Spring 2026' )
		);

		$mapper = new AttributeMapper();
		$values = $mapper->render(
			array(
				'name'    => '{user_name}',
				'email'   => '{user_email}',
				'login'   => '{user_login}',
				'course'  => '{course_title}',
				'hours'   => '{course_meta:duration}',
				'level'   => '{user_meta:level}',
				'cohort'  => '{context:cohort}',
				'score'   => '{score}',
				'date'    => '{completion_date_iso}',
				'site'    => '{site_name}',
				'url'     => '{site_url}',
				'unknown' => '{not_a_token}',
			),
			$event
		);

		$this->assertSame( 'Ada Lovelace', $values['name'] );
		$this->assertSame( 'ada@example.com', $values['email'] );
		$this->assertSame( 'ada', $values['login'] );
		$this->assertSame( 'Leadership Essentials', $values['course'] );
		$this->assertSame( '40 hours', $values['hours'] );
		$this->assertSame( 'Gold', $values['level'] );
		$this->assertSame( 'Spring 2026', $values['cohort'] );
		$this->assertSame( '92.5', $values['score'] );
		$this->assertSame( '2026-05-01', $values['date'] );
		$this->assertSame( 'Acme Academy', $values['site'] );
		$this->assertSame( 'https://acme.test', $values['url'] );
		$this->assertSame( '{not_a_token}', $values['unknown'] );
	}

	/**
	 * Missing scores become empty strings.
	 */
	public function test_missing_score_is_empty(): void {
		$event = CompletionEvent::create( 'custom', 'course_completed', 7, 0, 'Course' );

		$values = ( new AttributeMapper() )->render( array( 'score' => 'Score: {score}' ), $event );

		$this->assertSame( 'Score:', $values['score'] );
	}
}
