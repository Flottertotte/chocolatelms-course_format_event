<?php
// List of observers.
$observers = array(
    array(
        'eventname' => '\core\event\calendar_event_updated',
        'callback'  => 'format_event_observer::course_updated',
    ),

    array(
        'eventname' => '\core\event\calendar_event_deleted',
        'callback'  => 'format_event_observer::course_deleted',
    ),

    array(
    	'eventname' => '\core\event\course_created',
        'callback'  => 'format_event_observer::course_created',
    ),

    array(
        'eventname' => '\core\event\user_enrolment_created',
        'callback'  => 'format_event_observer::user_enrolled',
    ),

    array(
        'eventname' => '\core\event\user_enrolment_deleted',
        'callback'  => 'format_event_observer::user_unenrolled',
    )
);