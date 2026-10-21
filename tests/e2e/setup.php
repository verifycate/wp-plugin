<?php
/**
 * Seed a local WordPress for the Verifycate end-to-end test.
 *
 * Run with:
 *   npx wp-env run cli wp eval-file wp-content/plugins/wp-plugin/tests/e2e/setup.php
 *
 * Creates a learner, one course per active LMS, and one mapping per integration
 * pointing at the mock API's cert-1.
 *
 * @package Verifycate
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$email = 'ada@example.com';
$user  = get_user_by( 'email', $email );

if ( ! $user ) {
	$user_id = wp_create_user( 'ada', 'password', $email );
	if ( is_wp_error( $user_id ) ) {
		echo wp_json_encode( array( 'error' => $user_id->get_error_message() ) );
		exit( 1 );
	}
	$user = get_user_by( 'id', $user_id );
}

$user_id = (int) $user->ID;
wp_update_user(
	array(
		'ID'           => $user_id,
		'display_name' => 'Ada Lovelace',
		'first_name'   => 'Ada',
		'last_name'    => 'Lovelace',
	)
);

$courses = array();
$definitions = array(
	'tutor'      => array( 'post_type' => 'courses', 'title' => 'Tutor Leadership 101' ),
	'learnpress' => array( 'post_type' => 'lp_course', 'title' => 'LearnPress Leadership 101' ),
	'lifterlms'  => array( 'post_type' => 'course', 'title' => 'LifterLMS Leadership 101' ),
	'sensei'     => array( 'post_type' => 'course', 'title' => 'Sensei Leadership 101' ),
);

foreach ( $definitions as $integration => $definition ) {
	$existing = get_posts(
		array(
			'post_type'   => $definition['post_type'],
			'post_status' => 'any',
			'numberposts' => 1,
			'title'       => $definition['title'],
		)
	);

	if ( $existing !== array() ) {
		$courses[ $integration ] = (int) $existing[0]->ID;
		continue;
	}

	$course_id = wp_insert_post(
		array(
			'post_type'   => $definition['post_type'],
			'post_title'  => $definition['title'],
			'post_status' => 'publish',
			'post_author' => $user_id,
		),
		true
	);

	if ( is_wp_error( $course_id ) ) {
		$courses[ $integration ] = 0;
		continue;
	}

	$courses[ $integration ] = (int) $course_id;
}

$repo = new Verifycate\Issuance\MappingRepository();

foreach ( $repo->all() as $mapping ) {
	$repo->delete( $mapping->getId() );
}

$mappings = array();

$mappings['custom'] = $repo->save(
	array(
		'integration'      => 'custom',
		'event'            => 'course_completed',
		'course_id'        => 0,
		'certificate_id'   => 'cert-1',
		'certificate_name' => 'Leadership Essentials',
		'attributes'       => array(
			'course_hours' => '{course_id}',
			'learner_name' => '{user_name}',
		),
		'auto_issue'       => true,
		'duplicate_policy' => 'skip',
		'enabled'          => true,
	)
);

$certificates = array(
	'tutor'      => array( 'cert-tutor', 'Tutor Leadership Certificate' ),
	'learnpress' => array( 'cert-learnpress', 'LearnPress Leadership Certificate' ),
	'lifterlms'  => array( 'cert-lifterlms', 'LifterLMS Leadership Certificate' ),
	'sensei'     => array( 'cert-sensei', 'Sensei Leadership Certificate' ),
);

foreach ( $certificates as $integration => $certificate ) {
	$mappings[ $integration ] = $repo->save(
		array(
			'integration'      => $integration,
			'event'            => '',
			'course_id'        => 0,
			'certificate_id'   => $certificate[0],
			'certificate_name' => $certificate[1],
			'attributes'       => array(
				'course_hours' => '{course_id}',
				'learner_name' => '{user_name}',
			),
			'auto_issue'       => true,
			'duplicate_policy' => 'skip',
			'enabled'          => true,
		)
	);
}

update_option(
	'verifycate_e2e',
	array(
		'user_id'  => $user_id,
		'courses'  => $courses,
		'mappings' => $mappings,
	),
	false
);

echo wp_json_encode(
	array(
		'user_id'  => $user_id,
		'courses'  => $courses,
		'mappings' => $mappings,
	)
);
