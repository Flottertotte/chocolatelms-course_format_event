<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * event course format.  Display the whole course as "event" made of modules.
 *
 * @package   format_event
 * @copyright emeneo {@link http://emeneo.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/completionlib.php');

// Horrible backwards compatible parameter aliasing..
if ($topic = optional_param('topic', 0, PARAM_INT)) {
    $url = $PAGE->url;
    $url->param('section', $topic);
    debugging('Outdated topic param passed to course/view.php', DEBUG_DEVELOPER);
    redirect($url);
}
// End backwards-compatible aliasing..

$context = context_course::instance($course->id);
// Retrieve course format option fields and add them to the $course object.
$course = course_get_format($course)->get_course();

if (($marker >=0) && has_capability('moodle/course:setcurrentsection', $context) && confirm_sesskey()) {
    $course->marker = $marker;
    course_set_marker($course->id, $marker);
}

// Make sure section 0 is created.
course_create_sections_if_missing($course, 0);

$renderer = $PAGE->get_renderer('format_event');

if (!empty($displaysection)) {
    $renderer->print_single_section_page($course, null, null, null, null, $displaysection);
} else {
    $renderer->print_multiple_section_page($course, null, null, null, null);
}

$editurl=new moodle_url('/course/edit.php', array('id' => $course->id));
$subTitle = '<div><span style="margin-right:15px;"><i class="icon fa fa-calendar"></i>'.date('Y-m-d',$course->startdate).'</span><span style="margin-right:15px;"><i class="icon fa fa-clock-o"></i>'.date('H:i',$course->startdate).'-'.date('H:i',$course->enddate).'</span><span><i class="icon fa fa-map-marker"></i>'.$course->location.'</span>';
$button='<div class="enrol_event_edit_course"><a href="'.$editurl.'" class="btn btn-secondary" role="">'. get_string('edit').'</a></div>';
echo '<script src="https://apps.bdimg.com/libs/jquery/2.1.4/jquery.min.js"></script>';
echo "<script>$('.page-header-headings').append('".$subTitle."')</script>";
echo "<script>$('.mr-auto').after('".$button."')</script>";
//echo $subTitle;
// Include course format js module
$PAGE->requires->js('/course/format/event/format.js');


