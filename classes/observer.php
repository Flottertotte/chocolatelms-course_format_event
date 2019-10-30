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

        if($format_event_options['sendemailuponenrollment'] == 1){
            $format_event_config = get_config('format_event');
            foreach ($enrolled as $user) {
                $mail_subject = $format_event_config->welcome_email_subject;
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
                $mail_content.= '<br><a href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">Link to '.$course->fullname.'</a>';

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
                    //$messageid = self::mailsent($message,$mail_subject,$mail_content,$ics_content);
                    $messageid = email_to_user($user, $fromuser, $mail_subject, $mail_content,$mail_content, $attachname_file, $attachname_filename);
                }else{
                    $messageid = email_to_user($user, $fromuser, $mail_subject, $mail_content,$mail_content);
                }
                /*
                if(empty($attachname_file)){
                    $messageid = email_to_user($user, $fromuser, $mail_subject, $mail_content,$mail_content);
                }else{
                    $messageid = email_to_user($user, $fromuser, $mail_subject, $mail_content,$mail_content, $attachname_file, $attachname_filename);
                }
                */
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
        
        if($format_event_options['sendemailuponenrollment'] == 1){
            $format_event_config = get_config('format_event');
            $mail_subject = $format_event_config->welcome_email_subject;
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
            $mail_content.= '<br><a href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">Link to '.$course->fullname.'</a>';

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
                //$messageid = self::mailsent($message,$mail_subject,$mail_content,$ics_content);
                $messageid = email_to_user($user, $fromuser, $mail_subject, $mail_content,$mail_content, $attachname_file, $attachname_filename);
            }else{
                $messageid = email_to_user($user, $fromuser, $mail_subject, $mail_content,$mail_content);
            }

            /*
            if(empty($attachname_file)){
                $messageid = email_to_user($user, $fromuser, $mail_subject, $mail_content,$mail_content);
            }else{
                $messageid = email_to_user($user, $fromuser, $mail_subject, $mail_content,$mail_content, $attachname_file, $attachname_filename);
            }
            */
            //unlink($attachname_file);
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
        
        if($format_event_options['sendemailuponunenrollment'] == 1){
            $format_event_config = get_config('format_event');
            $mail_subject = $format_event_config->unenrollment_email_subject;
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
            $mail_content.= '<br><a href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">Link to '.$course->fullname.'</a>';

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
            //}

            $mail_content = str_replace('[linktoics]', $CFG->wwwroot."/course/format/event/download.php?attach=".base64_encode($attachname_file)."&name=".base64_encode($course->fullname), $mail_content);
            if($format_event_config->email_attachment_setting == 2){
                //$messageid = self::mailsent($message,$mail_subject,$mail_content,$ics_content);
              $messageid = email_to_user($user, $fromuser, $mail_subject, $mail_content,$mail_content, $attachname_file, $attachname_filename);
            }else{
              $messageid = email_to_user($user, $fromuser, $mail_subject, $mail_content,$mail_content);  
            }
            /*
            if(empty($attachname_file)){
                $messageid = email_to_user($user, $fromuser, $mail_subject, $mail_content,$mail_content);
            }else{
                $messageid = email_to_user($user, $fromuser, $mail_subject, $mail_content,$mail_content, $attachname_file, $attachname_filename);
            }
            */
            //unlink($attachname_file);
            //$messageid = message_send($message);
        }
        //echo $messageid."<br><pre>";print_r($message);exit;
    }

    private function mailsent($message,$subject,$content,$ical=''){
        //echo $message->userto->email."\n";
        //echo "<pre>";print_r($message);exit;
        $from_name = $message->userfrom->firstname.' '.$message->userfrom->lastname;
        $from_address = $message->userfrom->email;
        $subject = $subject;
        $email = $message->userto->email;

        //Create Mime Boundry
        $mime_boundary = "----Meeting Booking----".md5(time());

        //Create Email Headers
        $headers = "From: ".$from_name." <".$from_address.">\n";
        $headers .= "Reply-To: ".$from_name." <".$from_address.">\n";

        $headers .= "MIME-Version: 1.0\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"$mime_boundary\"\n";
        $headers .= "Content-class: urn:content-classes:calendarmessage\n";

        //Create Email Body (HTML)
        $message = "";
        $message .= "--$mime_boundary\n";
        $message .= "Content-Type: text/html; charset=UTF-8\n";
        $message .= "Content-Transfer-Encoding: 8bit\n\n";

        $message .= "<html>\n";
        $message .= "<body>\n";
        $message .= $content;
        $message .= "</body>\n";
        $message .= "</html>\n";
        $message .= "--$mime_boundary\n";

        $message .= 'Content-Type: text/calendar; name="calendar.ics";method=REQUEST; charset=utf-8\n';
        $message .= 'Content-Disposition: inline;\n';
        $message .= "Content-Transfer-Encoding: 2048bit\n\n";
        $message .= $ical; 

        //SEND MAIL
        $mail_sent = mail($email, $subject, $message, $headers);
        if($mail_sent){
            return time();
        }else{
            echo $email."\n";
            echo $subject."\n";
            echo $message."\n";
            echo $headers."\n";
            echo 'mail sent failed';exit;
            return 0;
        }   
    }
}