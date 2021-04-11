<?php
defined('MOODLE_INTERNAL') || die();
/**
 * Event observer for mod_forum.
 */
class format_event_observer {
    /**
     * Observer for \core\event\course_updated event.
     *
     * @param \core\event\course_updated $event
     * @return void
     */
    public static function course_updated(\core\event\calendar_event_updated $event) {
        global $CFG,$DB;
        
        require_once($CFG->dirroot.'/course/lib.php');
        require_once($CFG->dirroot.'/calendar/lib.php');

        $cur_event = $DB->get_record('event',array('id'=>$event->objectid));

        $event_timestart = $cur_event->timestart;
        $event_timeend = $event_timestart + $cur_event->timeduration;

        $course = $DB->get_record('course',array('id'=>$event->courseid));

        $course->startdate = $event_timestart;
        $course->enddate = $event_timeend;
        $course->summary = $cur_event->description;

        //send mail
        $context = context_course::instance($course->id);
        $enrolled = get_enrolled_users($context, null, 0);
        $fromuser = $DB->get_record('user',array('id'=>2));

        $format_event_options = array();
        $arr_format_event_options = $DB->get_records('course_format_options',array('courseid'=>$course->id,'format'=>'event'));
        foreach ($arr_format_event_options as $row) {
            $format_event_options[$row->name] = $row->value;
        }

        if(!file_exists($CFG->dataroot.'/temp/files')){
            mkdir($CFG->dataroot.'/temp/files');
        }

        if($format_event_options['sendemailuponenrollment'] == 1){
            $format_event_config = get_config('format_event');
            foreach ($enrolled as $user) {
                $mail_subject = get_mail_subject($format_event_config->welcome_email_subject,$user,$fromuser);
                $mail_subject = str_replace('[coursename]', $course->fullname, $mail_subject);
                $mail_content = $format_event_config->welcome_email_content;
                $mail_content = str_replace('[coursename]', $course->fullname, $mail_content);
                $mail_content = str_replace('[startdate]', date('Y-m-d',$course->startdate), $mail_content);
                $mail_content = str_replace('[enddate]', date('Y-m-d',$course->enddate), $mail_content);
                $mail_content = str_replace('[starttime]', date('H:i:s',$course->startdate), $mail_content);
                $mail_content = str_replace('[endtime]', date('H:i:s',$course->enddate), $mail_content);
                $mail_content = str_replace('[location]', $format_event_options['location'], $mail_content);
                $mail_content = str_replace('[description]', $course->summary, $mail_content);
                $mail_content = str_replace('[firstname]', $user->firstname, $mail_content);
                $mail_content = str_replace('[lastname]', $user->lastname, $mail_content);
                $mail_content.= '<br><a href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">更多信息/More info: '.$course->fullname.'</a>';

                $attachname_file = $attachname_filename = '';
                //if($format_event_config->email_attachment_setting == 2){
                    $ics_content = get_ical_attachment('invite',$course,$user,$format_event_options['location']);
                    $usercontext = context_user::instance($user->id);
                    $fs = get_file_storage();
                    $filerecord = array(
                        'contextid' => $usercontext->id,
                        'component' => 'user',
                        'filearea'  => 'private',
                        'itemid'    => 99999,
                        'filepath'  => '/',
                        'filename'  => md5(time().$user->id).'.ics'
                    );
                    $file = $fs->create_file_from_string($filerecord, $ics_content);
                    $attachname = $course->fullname.'.ics';
                    $message->attachment = $file;
                    $message->attachname = $attachname;
                    $attachname_file = $CFG->dataroot.'/temp/files/'.md5(time().mt_rand(1,10000));
                    file_put_contents($attachname_file, $ics_content);
                    $attachname_filename = $attachname;
                //}

                $mail_content = str_replace('[linktoics]', $CFG->wwwroot."/course/format/event/download.php?attach=".base64_encode($attachname_file)."&name=".base64_encode($course->fullname), $mail_content);

                if($format_event_config->email_attachment_setting == 2){
                    $messageid = email_to_user($user, $fromuser, $mail_subject, $mail_content,$mail_content, $attachname_file, $attachname_filename);
                }else{
                    $messageid = email_to_user($user, $fromuser, $mail_subject, $mail_content,$mail_content);
                }
                //unlink($attachname_file); //it will be deleted in mailqueue!
            }
        }

        $DB->update_record('course', $course);
    }

    public static function course_deleted(\core\event\calendar_event_deleted $event) {
        /*
        global $CFG,$DB;
        
        require_once($CFG->dirroot.'/course/lib.php');
        require_once($CFG->dirroot.'/calendar/lib.php');
        */
        //echo "<pre>";print_r($event);exit;

        
    }

    public static function course_created(\core\event\course_created $event) {
        global $CFG,$DB;
        
        require_once($CFG->dirroot.'/course/lib.php');
        require_once($CFG->dirroot.'/calendar/lib.php');

        $course = $DB->get_record('course',array('id'=>$event->courseid));

        $params = array("name" => $course->fullname,
                        "timestart" => $course->startdate,
                        "eventtype" => "course",
                        "courseid" => $course->id,
                        "description" => $course->summary,
                        "timedurationuntil" => $course->enddate);
        $duration = $course->enddate - $course->startdate;
        $events = array(array('name' => $course->fullname, 'courseid' => $course->id, "timestart" => $course->startdate, "timeduration" => $duration, 'eventtype' => 'course', 'description'=>$course->summary , 'repeats' => 0),);

        $eventsret = core_calendar_external::create_calendar_events($events);
        $eventsret = external_api::clean_returnvalue(core_calendar_external::create_calendar_events_returns(), $eventsret);
    }

    public static function user_enrolled(\core\event\user_enrolment_created $event){
        global $CFG,$DB;
        require_once($CFG->dirroot.'/course/lib.php');
        require_once($CFG->dirroot.'/course/format/event/lib.php');

        $course = $DB->get_record('course',array('id'=>$event->courseid));
        if(isset($event->relateduserid)){
            $user = $DB->get_record('user',array('id'=>$event->relateduserid));
        }else{
            $user = $DB->get_record('user',array('id'=>$event->userid));
        }
        $fromuser = $DB->get_record('user',array('id'=>2));

        $format_event_options = array();
        $arr_format_event_options = $DB->get_records('course_format_options',array('courseid'=>$course->id,'format'=>'event'));
        foreach ($arr_format_event_options as $row) {
            $format_event_options[$row->name] = $row->value;
        }

        if(!file_exists($CFG->dataroot.'/temp/files')){
            mkdir($CFG->dataroot.'/temp/files');
        }
        
        if($format_event_options['sendemailuponenrollment'] == 1){
            $format_event_config = get_config('format_event');
            $mail_subject = get_mail_subject($format_event_config->welcome_email_subject,$user,$fromuser);
            $mail_subject = str_replace('[coursename]', $course->fullname, $mail_subject);
            $mail_content = $format_event_config->welcome_email_content;
            $mail_content = str_replace('[coursename]', $course->fullname, $mail_content);
            $mail_content = str_replace('[startdate]', date('Y-m-d',$course->startdate), $mail_content);
            $mail_content = str_replace('[enddate]', date('Y-m-d',$course->enddate), $mail_content);
            $mail_content = str_replace('[starttime]', date('H:i:s',$course->startdate), $mail_content);
            $mail_content = str_replace('[endtime]', date('H:i:s',$course->enddate), $mail_content);
            $mail_content = str_replace('[location]', $format_event_options['location'], $mail_content);
            $mail_content = str_replace('[description]', $course->summary, $mail_content);
            $mail_content = str_replace('[firstname]', $user->firstname, $mail_content);
            $mail_content = str_replace('[lastname]', $user->lastname, $mail_content);
            $mail_content.= '<br><a href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">更多信息/More info: '.$course->fullname.'</a>';

            $CFG->allowattachments = true;

            $message = new \core\message\message();
            $message->courseid          = $course->id;
            $message->component         = 'moodle';
            $message->name              = 'instantmessage';
            $message->userfrom          = $fromuser;
            $message->userto            = $user;
            $message->subject           = $mail_subject;
            $message->fullmessagehtml   = $mail_content;
            $message->fullmessageformat = FORMAT_HTML;
            $message->smallmessage      = '';
            $message->notification      = 1;

            $attachname_file = $attachname_filename = '';
            //if($format_event_config->email_attachment_setting == 2){
                $ics_content = get_ical_attachment('invite',$course,$user,$format_event_options['location']);
                $usercontext = context_user::instance($user->id);
                $fs = get_file_storage();
                $filerecord = array(
                    'contextid' => $usercontext->id,
                    'component' => 'user',
                    'filearea'  => 'private',
                    'itemid'    => 99999,
                    'filepath'  => '/',
                    'filename'  => md5(time().$user->id).'.ics'
                );
                $file = $fs->create_file_from_string($filerecord, $ics_content);
                $attachname = $course->fullname.'.ics';
                $message->attachment = $file;
                $message->attachname = $attachname;

                $attachname_file = $CFG->dataroot.'/temp/files/'.md5(time().mt_rand(1,10000));
                file_put_contents($attachname_file, $ics_content);
                $attachname_filename = $attachname;
            //}

            $mail_content = str_replace('[linktoics]', $CFG->wwwroot."/course/format/event/download.php?attach=".base64_encode($attachname_file)."&name=".base64_encode($course->fullname), $mail_content);

            if($format_event_config->email_attachment_setting == 2){
                $messageid = email_to_user($user, $fromuser, $mail_subject, $mail_content,$mail_content, $attachname_file, $attachname_filename);
            }else{
                $messageid = email_to_user($user, $fromuser, $mail_subject, $mail_content,$mail_content);
            }
            //unlink($attachname_file); //It will be deleted in mailqueue!
			
            //message_send($message);
        }
    }

    public static function user_unenrolled(\core\event\user_enrolment_deleted $event){
        global $CFG,$DB;
        require_once($CFG->dirroot.'/course/lib.php');
        require_once($CFG->dirroot.'/course/format/event/lib.php');

        $course = $DB->get_record('course',array('id'=>$event->courseid));
        $user  = $DB->get_record('user',array('id'=>$event->other['userenrolment']['userid']));
        $fromuser = $DB->get_record('user',array('id'=>2));

        $format_event_options = array();
        $arr_format_event_options = $DB->get_records('course_format_options',array('courseid'=>$course->id,'format'=>'event'));
        foreach ($arr_format_event_options as $row) {
            $format_event_options[$row->name] = $row->value;
        }

        if(!file_exists($CFG->dataroot.'/temp/files')){
            mkdir($CFG->dataroot.'/temp/files');
        }
        
        if($format_event_options['sendemailuponunenrollment'] == 1){
            $format_event_config = get_config('format_event');
            $mail_subject = get_mail_subject($format_event_config->unenrollment_email_subject,$user,$fromuser);
            $mail_subject = str_replace('[coursename]', $course->fullname, $mail_subject);
            $mail_content = $format_event_config->unenrollment_email_content;
            $mail_content = str_replace('[coursename]', $course->fullname, $mail_content);
            $mail_content = str_replace('[startdate]', date('Y-m-d',$course->startdate), $mail_content);
            $mail_content = str_replace('[enddate]', date('Y-m-d',$course->enddate), $mail_content);
            $mail_content = str_replace('[starttime]', date('H:i:s',$course->startdate), $mail_content);
            $mail_content = str_replace('[endtime]', date('H:i:s',$course->enddate), $mail_content);
            $mail_content = str_replace('[location]', $format_event_options['location'], $mail_content);
            $mail_content = str_replace('[description]', $course->summary, $mail_content);
            $mail_content = str_replace('[firstname]', $user->firstname, $mail_content);
            $mail_content = str_replace('[lastname]', $user->lastname, $mail_content);
            $mail_content.= '<br><a href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">更多信息/More info: '.$course->fullname.'</a>';

            $CFG->allowattachments = true;

            $message = new \core\message\message();
            $message->courseid          = $course->id;
            $message->component         = 'moodle';
            $message->name              = 'instantmessage';
            $message->userfrom          = $fromuser;
            $message->userto            = $user;
            $message->subject           = $mail_subject;
            $message->fullmessagehtml   = $mail_content;
            $message->fullmessageformat = FORMAT_HTML;
            $message->smallmessage      = '';
            $message->notification      = 1;

            $attachname_file = $attachname_filename = '';
            $ics_content = get_ical_attachment('cancel',$course,$user,$format_event_options['location']);
            $usercontext = context_user::instance($user->id);
            $fs = get_file_storage();
            $filerecord = array(
                'contextid' => $usercontext->id,
                'component' => 'user',
                'filearea'  => 'private',
                'itemid'    => 99999,
                'filepath'  => '/',
                'filename'  => md5(time().$user->id).'.ics'
            );
            $file = $fs->create_file_from_string($filerecord, $ics_content);
            $attachname = $course->fullname.'.ics';
            $message->attachment = $file;
            $message->attachname = $attachname;
                
            $attachname_file = $CFG->dataroot.'/temp/files/'.md5(time().mt_rand(1,10000));
            file_put_contents($attachname_file, $ics_content);
            $attachname_filename = $attachname;

            $mail_content = str_replace('[linktoics]', $CFG->wwwroot."/course/format/event/download.php?attach=".base64_encode($attachname_file)."&name=".base64_encode($course->fullname), $mail_content);
            if($format_event_config->email_attachment_setting == 2){
              $messageid = email_to_user($user, $fromuser, $mail_subject, $mail_content,$mail_content, $attachname_file, $attachname_filename);
            }else{
              $messageid = email_to_user($user, $fromuser, $mail_subject, $mail_content,$mail_content);  
            }
            //unlink($attachname_file); It will be deleted in mailqueue!
			
            //$messageid = message_send($message);
        }
    }
}