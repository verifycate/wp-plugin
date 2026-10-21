<?php
/**
 * Fire each LMS completion hook with the seeded course and learner.
 *
 * Run after setup.php:
 *   npx wp-env run cli wp eval-file wp-content/plugins/wp-plugin/tests/e2e/fire-lms.php
 *
 * @package Verifycate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$state   = get_option( 'verifycate_e2e' );
$user_id = isset( $state['user_id'] ) ? (int) $state['user_id'] : 0;
$courses = isset( $state['courses'] ) && is_array( $state['courses'] ) ? $state['courses'] : array();

if ( $user_id <= 0 || $courses === array() ) {
	echo "Run setup.php first.\n";
	exit( 1 );
}

// Tutor LMS.
do_action( 'tutor_course_complete_after', (int) $courses['tutor'], $user_id );
echo "fired tutor\n";

// LearnPress.
do_action( 'learn-press/user-course-finished', (int) $courses['learnpress'], $user_id, true );
echo "fired learnpress\n";

// LifterLMS.
do_action( 'lifterlms_course_completed', $user_id, (int) $courses['lifterlms'] );
echo "fired lifterlms\n";

// Sensei.
do_action( 'sensei_user_course_end', $user_id, (int) $courses['sensei'] );
echo "fired sensei\n";
