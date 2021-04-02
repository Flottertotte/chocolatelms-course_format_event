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
 * This file contains main class for the course format Topic
 *
 * @package   format_event
 * @copyright emeneo {@link http://emeneo.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot. '/course/format/lib.php');

/**
 * Main class for the event course format
 *
 * @package    format_event
 * @copyright  2012 Marina Glancy
 * @copyright  2019 emeneo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_event extends format_base {

    /**
     * Returns true if this course format uses sections
     *
     * @return bool
     */
    public function uses_sections() {
        return true;
    }

    /**
     * Returns the display name of the given section that the course prefers.
     *
     * Use section name is specified by user. Otherwise use default ("Topic #")
     *
     * @param int|stdClass $section Section object from database or just field section.section
     * @return string Display name that the course format prefers, e.g. "Topic 2"
     */
    public function get_section_name($section) {
        $section = $this->get_section($section);
        if ((string)$section->name !== '') {
            return format_string($section->name, true,
                    array('context' => context_course::instance($this->courseid)));
        } else {
            return $this->get_default_section_name($section);
        }
    }

    /**
     * Returns the default section name for the event course format.
     *
     * If the section number is 0, it will use the string with key = section0name from the course format's lang file.
     * If the section number is not 0, the base implementation of format_base::get_default_section_name which uses
     * the string with the key = 'sectionname' from the course format's lang file + the section number will be used.
     *
     * @param stdClass $section Section object from database or just field course_sections section
     * @return string The default value for the section name.
     */
    public function get_default_section_name($section) {
        if ($section->section == 0) {
            // Return the general section.
            return get_string('section0name', 'format_event');
        } else {
            // Use format_base::get_default_section_name implementation which
            // will display the section name in "Topic n" format.
            return parent::get_default_section_name($section);
        }
    }

    /**
     * The URL to use for the specified course (with section)
     *
     * @param int|stdClass $section Section object from database or just field course_sections.section
     *     if omitted the course view page is returned
     * @param array $options options for view URL. At the moment core uses:
     *     'navigation' (bool) if true and section has no separate page, the function returns null
     *     'sr' (int) used by multipage formats to specify to which section to return
     * @return null|moodle_url
     */
    public function get_view_url($section, $options = array()) {
        global $CFG;
        $course = $this->get_course();
        $url = new moodle_url('/course/view.php', array('id' => $course->id));

        $sr = null;
        if (array_key_exists('sr', $options)) {
            $sr = $options['sr'];
        }
        if (is_object($section)) {
            $sectionno = $section->section;
        } else {
            $sectionno = $section;
        }
        if ($sectionno !== null) {
            if ($sr !== null) {
                if ($sr) {
                    $usercoursedisplay = COURSE_DISPLAY_MULTIPAGE;
                    $sectionno = $sr;
                } else {
                    $usercoursedisplay = COURSE_DISPLAY_SINGLEPAGE;
                }
            } else {
                $usercoursedisplay = $course->coursedisplay;
            }
            if ($sectionno != 0 && $usercoursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                $url->param('section', $sectionno);
            } else {
                if (empty($CFG->linkcoursesections) && !empty($options['navigation'])) {
                    return null;
                }
                $url->set_anchor('section-'.$sectionno);
            }
        }
        return $url;
    }

    /**
     * Returns the information about the ajax support in the given source format
     *
     * The returned object's property (boolean)capable indicates that
     * the course format supports Moodle course ajax features.
     *
     * @return stdClass
     */
    public function supports_ajax() {
        $ajaxsupport = new stdClass();
        $ajaxsupport->capable = true;
        return $ajaxsupport;
    }

    /**
     * Loads all of the course sections into the navigation
     *
     * @param global_navigation $navigation
     * @param navigation_node $node The course node within the navigation
     */
    public function extend_course_navigation($navigation, navigation_node $node) {
        global $PAGE;
        // if section is specified in course/view.php, make sure it is expanded in navigation
        if ($navigation->includesectionnum === false) {
            $selectedsection = optional_param('section', null, PARAM_INT);
            if ($selectedsection !== null && (!defined('AJAX_SCRIPT') || AJAX_SCRIPT == '0') &&
                    $PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)) {
                $navigation->includesectionnum = $selectedsection;
            }
        }

        // check if there are callbacks to extend course navigation
        parent::extend_course_navigation($navigation, $node);

        // We want to remove the general section if it is empty.
        $modinfo = get_fast_modinfo($this->get_course());
        $sections = $modinfo->get_sections();
        if (!isset($sections[0])) {
            // The general section is empty to find the navigation node for it we need to get its ID.
            $section = $modinfo->get_section_info(0);
            $generalsection = $node->get($section->id, navigation_node::TYPE_SECTION);
            if ($generalsection) {
                // We found the node - now remove it.
                $generalsection->remove();
            }
        }
    }

    /**
     * Custom action after section has been moved in AJAX mode
     *
     * Used in course/rest.php
     *
     * @return array This will be passed in ajax respose
     */
    function ajax_section_move() {
        global $PAGE;
        $titles = array();
        $course = $this->get_course();
        $modinfo = get_fast_modinfo($course);
        $renderer = $this->get_renderer($PAGE);
        if ($renderer && ($sections = $modinfo->get_section_info_all())) {
            foreach ($sections as $number => $section) {
                $titles[$number] = $renderer->section_title($section, $course);
            }
        }
        return array('sectiontitles' => $titles, 'action' => 'move');
    }

    /**
     * Returns the list of blocks to be automatically added for the newly created course
     *
     * @return array of default blocks, must contain two keys BLOCK_POS_LEFT and BLOCK_POS_RIGHT
     *     each of values is an array of block names (for left and right side columns)
     */
    public function get_default_blocks() {
        return array(
            BLOCK_POS_LEFT => array(),
            BLOCK_POS_RIGHT => array()
        );
    }

    /**
     * Definitions of the additional options that this course format uses for course
     *
     * event format uses the following options:
     * - coursedisplay
     * - hiddensections
     *
     * @param bool $foreditform
     * @return array of options
     */
    public function course_format_options($foreditform = false) {
        static $courseformatoptions = false;

        $config = get_config('format_event');
        $locations = $config->locations;
        $arr_locations = explode(";", $locations);
        $sel_locations = array();
        $send_mail_options = array(1=>'Yes',0=>'No');
        foreach ($arr_locations as $location) {
            $sel_locations[$location] = $location;
        }

        if ($courseformatoptions === false) {
            $courseconfig = get_config('moodlecourse');
            $courseformatoptions = array(
                'hiddensections' => array(
                    'default' => $courseconfig->hiddensections,
                    'type' => PARAM_INT,
                ),
                'coursedisplay' => array(
                    'default' => $courseconfig->coursedisplay,
                    'type' => PARAM_INT,
                ),
                'location' => array(
                    'default' => '',
                    'type' => PARAM_TEXT,
                ),
                'sendemailuponenrollment' => array(
                    'default' => 0,
                    'type' => PARAM_INT,
                ),
                'sendemailuponunenrollment' => array(
                    'default' => 0,
                    'type' => PARAM_INT,
                ),
                'sendemailuponcourseupdate' => array(
                    'default' => 0,
                    'type' => PARAM_INT,
                ),
            );
        }
        if ($foreditform && !isset($courseformatoptions['coursedisplay']['label'])) {
            $courseformatoptionsedit = array(
                'hiddensections' => array(
                    'label' => new lang_string('hiddensections'),
                    'help' => 'hiddensections',
                    'help_component' => 'moodle',
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            0 => new lang_string('hiddensectionscollapsed'),
                            1 => new lang_string('hiddensectionsinvisible')
                        )
                    ),
                ),
                'coursedisplay' => array(
                    'label' => new lang_string('coursedisplay'),
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            COURSE_DISPLAY_SINGLEPAGE => new lang_string('coursedisplay_single'),
                            COURSE_DISPLAY_MULTIPAGE => new lang_string('coursedisplay_multi')
                        )
                    ),
                    'help' => 'coursedisplay',
                    'help_component' => 'moodle',
                ),
                'location' => array(
                    'label' => new lang_string('location', 'format_event'),
                    'help' => 'location',
                    'help_component' => 'format_event',
                    'element_type' => 'select',
                    'element_attributes' => array($sel_locations),
                ),
                'sendemailuponenrollment' => array(
                    'label' => new lang_string('send_email_upon_enrollment', 'format_event'),
                    'help' => 'sendemailuponenrollment_help',
                    'help_component' => 'format_event',
                    'element_type' => 'select',
                    'element_attributes' => array($send_mail_options),
                ),
                'sendemailuponunenrollment' => array(
                    'label' => new lang_string('send_email_upon_unenrollment', 'format_event'),
                    'help' => 'sendemailuponunenrollment_help',
                    'help_component' => 'format_event',
                    'element_type' => 'select',
                    'element_attributes' => array($send_mail_options),
                ),
                'sendemailuponcourseupdate' => array(
                    'label' => new lang_string('send_email_upon_course_update', 'format_event'),
                    'help' => 'sendemailuponcourseupdate_help',
                    'help_component' => 'format_event',
                    'element_type' => 'select',
                    'element_attributes' => array($send_mail_options),
                ),
            );
            //echo "<pre>";
            //print_r($courseformatoptions);print_r($courseformatoptionsedit);exit;
            $courseformatoptions = array_merge_recursive($courseformatoptions, $courseformatoptionsedit);
        }
        return $courseformatoptions;
    }

    /**
     * Adds format options elements to the course/section edit form.
     *
     * This function is called from {@link course_edit_form::definition_after_data()}.
     *
     * @param MoodleQuickForm $mform form the elements are added to.
     * @param bool $forsection 'true' if this is a section edit form, 'false' if this is course edit form.
     * @return array array of references to the added form elements.
     */
    public function create_edit_form_elements(&$mform, $forsection = false) {
        global $COURSE;
        $elements = parent::create_edit_form_elements($mform, $forsection);
        if (!$forsection && (empty($COURSE->id) || $COURSE->id == SITEID)) {
            // Add "numsections" element to the create course form - it will force new course to be prepopulated
            // with empty sections.
            // The "Number of sections" option is no longer available when editing course, instead teachers should
            // delete and add sections when needed.
            $courseconfig = get_config('moodlecourse');
            $max = (int)$courseconfig->maxsections;
            $element = $mform->addElement('select', 'numsections', get_string('numberweeks'), range(0, $max ?: 52));
            $mform->setType('numsections', PARAM_INT);
            if (is_null($mform->getElementValue('numsections'))) {
                $mform->setDefault('numsections', $courseconfig->numsections);
            }
            array_unshift($elements, $element);
        }

        return $elements;
    }

    /**
     * Updates format options for a course
     *
     * In case if course format was changed to 'event', we try to copy options
     * 'coursedisplay' and 'hiddensections' from the previous format.
     *
     * @param stdClass|array $data return value from {@link moodleform::get_data()} or array with data
     * @param stdClass $oldcourse if this function is called from {@link update_course()}
     *     this object contains information about the course before update
     * @return bool whether there were any changes to the options values
     */
    public function update_course_format_options($data, $oldcourse = null) {
        $data = (array)$data;
        if ($oldcourse !== null) {
            $oldcourse = (array)$oldcourse;
            $options = $this->course_format_options();
            foreach ($options as $key => $unused) {
                if (!array_key_exists($key, $data)) {
                    if (array_key_exists($key, $oldcourse)) {
                        $data[$key] = $oldcourse[$key];
                    }
                }
            }
        }

        global $CFG,$DB,$USER;

        require_once($CFG->dirroot.'/calendar/externallib.php');
        
        if(!$data['id']){
            return;
        }

        $courseids = array($data['id']);
        $cur_course = get_course($data['id']);
        $paramevents = array ('courseids' => $courseids);
        $listevents = core_calendar_external::get_calendar_events($paramevents);
        
        //remove events
        if(!empty($listevents['events'])){
            foreach ($listevents['events'] as $event) {
                $remove_events[] = array('eventid' => $event['id'],'repeat' => 0);
            }

            core_calendar_external::delete_calendar_events($remove_events);
        }
        
        $params = array("name" => $data['fullname'],
                        "timestart" => $data['startdate'],
                        "eventtype" => "course",
                        "courseid" => $data['id'],
                        "description" => $data['summary'],
                        "timedurationuntil" => $data['enddate']);
        $duration = $data['enddate'] - $data['startdate'];
        //$events = array(array('name' => $data['fullname'], 'courseid' => $data['id'], "timestart" => $data['startdate'], "timeduration" => $duration, 'eventtype' => 'course', 'description'=>$data['summary'] , 'repeats' => 0),);
        $events = array(array('name' => $data['fullname'], 'courseid' => $data['id'], "timestart" => $data['startdate'], "timeduration" => $duration, 'eventtype' => 'course', 'description'=>'' , 'repeats' => 0),);

        $eventsret = core_calendar_external::create_calendar_events($events);
        $eventsret = external_api::clean_returnvalue(core_calendar_external::create_calendar_events_returns(), $eventsret);
        
        $format_options = $this->get_config_for_external();
        if($format_options['sendemailuponcourseupdate'] == 1){
            //change mail sent
            $format_event_config = get_config('format_event');
            $mail_content = $format_event_config->change_email_content;

            $mail_content = str_replace('[coursename]', $cur_course->fullname, $mail_content);
            $mail_content = str_replace('[startdate]', date('Y-m-d',$data['startdate']), $mail_content);
            $mail_content = str_replace('[enddate]', date('Y-m-d',$data['enddate']), $mail_content);
            $mail_content = str_replace('[starttime]', date('H:i',$data['startdate']), $mail_content);
            $mail_content = str_replace('[endtime]', date('H:i',$data['enddate']), $mail_content);
            $mail_content = str_replace('[location]', $format_options['location'], $mail_content);
            $mail_content = str_replace('[description]', '', $mail_content);
            //$mail_content = str_replace('[description]', $cur_course->summary, $mail_content);

            $mail_content.= '<br><a href="'.$CFG->wwwroot.'/course/view.php?id='.$cur_course->id.'">更多信息/More info: '.$cur_course->fullname.'</a>';

            $context = context_course::instance($cur_course->id);
            $enrolled = get_enrolled_users($context, null, 0);
            $CFG->allowattachments = true;

            if(!file_exists($CFG->dataroot.'/temp/files')){
                mkdir($CFG->dataroot.'/temp/files');
            }
            
            foreach ($enrolled as $enrol) {
                $mail_subject = get_mail_subject($format_event_config->change_email_subject,$enrol,$USER);
                $mail_subject = str_replace('[coursename]', $cur_course->fullname, $mail_subject);
                $mail_content = str_replace('[firstname]', $enrol->firstname, $mail_content);
                $mail_content = str_replace('[lastname]', $enrol->lastname, $mail_content);
                
                $attachname_file = $attachname_filename = '';
                //if($format_event_config->email_attachment_setting == 2){
                    $cur_course->startdate = $data['startdate'];
                    $cur_course->enddate = $data['enddate'];
                    $ics_content = get_ical_attachment('update',$cur_course,$enrol,$format_options['location']);
                    $usercontext = context_user::instance($enrol->id);
                    $fs = get_file_storage();
                    $filerecord = array(
                            'contextid' => $usercontext->id,
                            'component' => 'user',
                            'filearea'  => 'private',
                            'itemid'    => 99999,
                            'filepath'  => '/',
                            'filename'  => md5(time().$enrol->id).'.ics'
                    );
                    $file = $fs->create_file_from_string($filerecord, $ics_content);
                    $attachname = $cur_course->fullname.'.ics';
                    $message->attachment = $file;
                    $message->attachname = $attachname;
                    $attachname_file = $CFG->dataroot.'/temp/files/'.md5(time().mt_rand(1,10000));
                    file_put_contents($attachname_file, $ics_content);
                    $attachname_filename = $attachname;
                //}

                $mail_content = str_replace('[linktoics]', $CFG->wwwroot."/course/format/event/download.php?attach=".base64_encode($attachname_file)."&name=".base64_encode($cur_course->fullname), $mail_content);
                if($format_event_config->email_attachment_setting == 2){
                    $messageid = email_to_user($enrol, $USER, $mail_subject, $mail_content,$mail_content, $attachname_file, $attachname_filename);
                }else{
                    $messageid = email_to_user($enrol, $USER, $mail_subject, $mail_content,$mail_content);
                }
                /*
                if(empty($attachname_file)){
                    $messageid = email_to_user($enrol, $USER, $mail_subject, $mail_content,$mail_content);
                }else{
                    $messageid = email_to_user($enrol, $USER, $mail_subject, $mail_content,$mail_content, $attachname_file, $attachname_filename);
                }
                */
            }
        }
        return $this->update_format_options($data);
    }

    /**
     * Whether this format allows to delete sections
     *
     * Do not call this function directly, instead use {@link course_can_delete_section()}
     *
     * @param int|stdClass|section_info $section
     * @return bool
     */
    public function can_delete_section($section) {
        return true;
    }

    /**
     * Prepares the templateable object to display section name
     *
     * @param \section_info|\stdClass $section
     * @param bool $linkifneeded
     * @param bool $editable
     * @param null|lang_string|string $edithint
     * @param null|lang_string|string $editlabel
     * @return \core\output\inplace_editable
     */
    public function inplace_editable_render_section_name($section, $linkifneeded = true,
                                                         $editable = null, $edithint = null, $editlabel = null) {
        if (empty($edithint)) {
            $edithint = new lang_string('editsectionname', 'format_event');
        }
        if (empty($editlabel)) {
            $title = get_section_name($section->course, $section);
            $editlabel = new lang_string('newsectionname', 'format_event', $title);
        }
        return parent::inplace_editable_render_section_name($section, $linkifneeded, $editable, $edithint, $editlabel);
    }

    /**
     * Indicates whether the course format supports the creation of a news forum.
     *
     * @return bool
     */
    public function supports_news() {
        return true;
    }

    /**
     * Returns whether this course format allows the activity to
     * have "triple visibility state" - visible always, hidden on course page but available, hidden.
     *
     * @param stdClass|cm_info $cm course module (may be null if we are displaying a form for adding a module)
     * @param stdClass|section_info $section section where this module is located or will be added to
     * @return bool
     */
    public function allow_stealth_module_visibility($cm, $section) {
        // Allow the third visibility state inside visible sections or in section 0.
        return !$section->section || $section->visible;
    }

    public function section_action($section, $action, $sr) {
        global $PAGE;

        if ($section->section && ($action === 'setmarker' || $action === 'removemarker')) {
            // Format 'event' allows to set and remove markers in addition to common section actions.
            require_capability('moodle/course:setcurrentsection', context_course::instance($this->courseid));
            course_set_marker($this->courseid, ($action === 'setmarker') ? $section->section : 0);
            return null;
        }

        // For show/hide actions call the parent method and return the new content for .section_availability element.
        $rv = parent::section_action($section, $action, $sr);
        $renderer = $PAGE->get_renderer('format_event');
        $rv['section_availability'] = $renderer->section_availability($this->get_section($section));
        return $rv;
    }

    /**
     * Return the plugin configs for external functions.
     *
     * @return array the list of configuration settings
     * @since Moodle 3.5
     */
    public function get_config_for_external() {
        // Return everything (nothing to hide).
        return $this->get_format_options();
    }
}

/**
 * Implements callback inplace_editable() allowing to edit values in-place
 *
 * @param string $itemtype
 * @param int $itemid
 * @param mixed $newvalue
 * @return \core\output\inplace_editable
 */
function format_event_inplace_editable($itemtype, $itemid, $newvalue) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/lib.php');
    if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
        $section = $DB->get_record_sql(
            'SELECT s.* FROM {course_sections} s JOIN {course} c ON s.course = c.id WHERE s.id = ? AND c.format = ?',
            array($itemid, 'event'), MUST_EXIST);
        return course_get_format($section->course)->inplace_editable_update_section_name($section, $itemtype, $newvalue);
    }
}

function ical_generate_timestamp($timestamp) {
    return gmdate('Ymd', $timestamp) . 'T' . gmdate('His', $timestamp) . 'Z';
}


function ical_escape($text, $converthtml=false) {
    if (empty($text)) {
        return '';
    }

    if ($converthtml) {
        $text = html_to_text($text);
    }

    $text = str_replace(
        array('\\',   "\n", ';',  ','),
        array('\\\\', '\n', '\;', '\,'),
        $text
    );

    // Text should be wordwrapped at 75 octets, and there should be one whitespace after the newline that does the wrapping.
    $text = wordwrap($text, 75, "\n ", true);

    return $text;
}

function get_ical_attachment($method, $course, $user, $locationstring='') {
    global $CFG, $DB;

    // First, generate all the VEVENT blocks.
    $VEVENTS = '';
        //$DTSTAMP = ical_generate_timestamp($course->startdate);
        $DTSTAMP = ical_generate_timestamp(time());
        // UIDs should be globally unique.
        //$UID = $DTSTAMP.'-'.substr(md5($CFG->siteidentifier.$course->id), -8).'@yunacademy';
        $UID = substr(md5($CFG->siteidentifier.$course->id), -8).'@yunacademy';
        $DTSTART = ical_generate_timestamp($course->startdate);
        $DTEND   = ical_generate_timestamp($course->enddate);

        // FIXME: currently we are not sending updates if the times of the session are changed. This is not ideal!
        //if($method == 'update'){
        //$SEQUENCE = time();
        //}else{
        //    ($method == 'cancel')?$SEQUENCE = 1:$SEQUENCE = 0;
        //}
        ($method == 'cancel')?$SEQUENCE = 1:$SEQUENCE = 0;
        
        $SUMMARY     = ical_escape($course->fullname);
        $DESCRIPTION = ical_escape($course->summary, true);
        $DESCRIPTION = str_replace('\n', '', $DESCRIPTION);   

        // Get the location data from custom fields if they exist.
        $LOCATION = str_replace('\n', '\, ', ical_escape($locationstring));
        //$ORGANISEREMAIL = 'chocolatelms@emeneo.com';
        $mail_smtpuser = $DB->get_record('config',array('name'=>'smtpuser'));
        $ORGANISEREMAIL = $mail_smtpuser->value;

        $ROLE = 'REQ-PARTICIPANT';
        $CANCELSTATUS = '';
        if ($method == 'cancel') {
            $ROLE = 'NON-PARTICIPANT';
            $CANCELSTATUS = "\nSTATUS:CANCELLED";
        }

        switch ($method) {
            case 'invite':$icalmethod = 'REQUEST';break;
            case 'cancel':$icalmethod = 'CANCEL';break;
            case 'update':$icalmethod = 'REQUEST';break;
        }
        //($method == 'invite')?$icalmethod = 'REQUEST':$icalmethod = 'CANCEL';

        // FIXME: if the user has input their name in another language, we need to set the LANGUAGE property parameter here.
        $USERNAME = fullname($user);
        //$MAILTO   = $user->email;
        $MAILTO = $ORGANISEREMAIL;

        // The extra newline at the bottom is so multiple events start on their own lines. The very last one is trimmed outside the loop.
        $VEVENTS .= <<<EOF
BEGIN:VEVENT
UID:{$UID}
DTSTAMP:{$DTSTAMP}
DTSTART:{$DTSTART}
DTEND:{$DTEND}
SEQUENCE:{$SEQUENCE}
SUMMARY:{$SUMMARY}
LOCATION:{$LOCATION}
DESCRIPTION:{$DESCRIPTION}
CLASS:PRIVATE
TRANSP:OPAQUE{$CANCELSTATUS}
ORGANIZER;CN={$ORGANISEREMAIL}:MAILTO:{$MAILTO}
ATTENDEE;CUTYPE=INDIVIDUAL;ROLE={$ROLE};PARTSTAT=NEEDS-ACTION;
 RSVP=FALSE;CN={$USERNAME};LANGUAGE=en:MAILTO:{$MAILTO}
END:VEVENT

EOF;
    $VEVENTS = trim($VEVENTS);

    // TODO: remove the hard-coded timezone!.
    $template = <<<EOF
BEGIN:VCALENDAR
CALSCALE:GREGORIAN
PRODID:-//chocolateLMS//NONSGML Event//EN
VERSION:2.0
METHOD:{$icalmethod}
BEGIN:VTIMEZONE
TZID:/softwarestudio.org/Tzfile/Pacific/Auckland
X-LIC-LOCATION:Pacific/Auckland
BEGIN:STANDARD
TZNAME:NZST
DTSTART:19700405T020000
RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=1SU;BYMONTH=4
TZOFFSETFROM:+1300
TZOFFSETTO:+1200
END:STANDARD
BEGIN:DAYLIGHT
TZNAME:NZDT
DTSTART:19700928T030000
RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=-1SU;BYMONTH=9
TZOFFSETFROM:+1200
TZOFFSETTO:+1300
END:DAYLIGHT
END:VTIMEZONE
{$VEVENTS}
END:VCALENDAR
EOF;

    $tempfilename = md5($template);
    $tempfilepathname = $CFG->dataroot . '/' . $tempfilename;
    //file_put_contents($tempfilepathname, $template);
    //return $tempfilename;
    return $template;
}

function get_mail_subject($subject,$user, $from){
    $subject = str_replace(get_string('placeholder:firstname', 'format_event'), $user->firstname, $subject);
    $subject = str_replace(get_string('placeholder:lastname', 'format_event'), $user->lastname, $subject);
    return $subject;
}
/*
function send_mail($message){
    global $CFG;

    require_once($CFG->dirroot.'/course/format/event/classes/mail/PHPMailer.php');
    require_once($CFG->dirroot.'/course/format/event/classes/mail/SMTP.php');

    $mail = new PHPMailer();
    $mail->SMTPDebug = 0;
    $mail->isSMTP();
    $mail->SMTPAuth = true;
    $mail->Host = 'mail.emeneo.com';
    $mail->SMTPSecure = 'ssl';
    $mail->Port = 2525;
    $mail->CharSet = 'UTF-8';
    $mail->FromName = $message['fromusername'];
    $mail->Username = 'chocolatelms@emeneo.com';
    $mail->Password = 'Lecker123!';
    $mail->From = $message['fromemail'];
    $mail->isHTML(true);
    $mail->addAddress($message['toemail']);
    $mail->Subject = $message['subject'];
    $mail->Body = $message['body'];
    $mail->addAttachment($message['attachment']);
    return $mail->send();
}
*/