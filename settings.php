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
 * Settings for format_singleactivity
 *
 * @package    format_singleactivity
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
	$settings->add(new admin_setting_configtextarea('format_event/locations',
         get_string('location', 'format_event'), 
         get_string('location_desc', 'format_event'),''));

	// Welcome email settings
	$settings->add(new admin_setting_heading('welcomeemailsettingheading', '', get_string('welcomeemailsettingheading', 'format_event')));
	$settings->add(new admin_setting_configtextarea('format_event/welcome_email_subject',
         get_string('welcomeemailsubject', 'format_event'), '',''));
	$settings->add(new admin_setting_configtextarea('format_event/welcome_email_content',
         get_string('welcomeemailcontent', 'format_event'), '',''));
	
	// Un-enrollment email settings
	$settings->add(new admin_setting_heading('unenrollmentemailsettingheading', '', get_string('unenrollmentemailsettingheading', 'format_event')));
	$settings->add(new admin_setting_configtextarea('format_event/unenrollment_email_subject',
         get_string('unenrollmentemailsubject', 'format_event'), '',''));
	$settings->add(new admin_setting_configtextarea('format_event/unenrollment_email_content',
         get_string('unenrollmentemailcontent', 'format_event'), '',''));
	
	// Change e-mail settings
	$settings->add(new admin_setting_heading('changeemailsettingheading', '', get_string('changeemailsettingheading', 'format_event')));
	$settings->add(new admin_setting_configtextarea('format_event/change_email_subject',
         get_string('changeemailsubject', 'format_event'), '',''));
	$settings->add(new admin_setting_configtextarea('format_event/change_email_content',
         get_string('changeemailcontent', 'format_event'), '',''));

	// e-Mail attachment settings
	$settings->add(new admin_setting_heading('emailattachmentsettingsheading', '', get_string('emailattachmentsettingsheading', 'format_event')));
	$options = array(1=>get_string('linkonly', 'format_event'),2=>get_string('linkandinemail', 'format_event'));
	/*
	$settings->add(new admin_setting_configselect('format_event/email_attachment_setting',
            get_string('icsfile', 'format_event'), '',
            array('value' => 1, 'adv' => false), $options));
    */
	$settings->add(new admin_setting_configselect('format_event/email_attachment_setting', get_string('icsfile', 'format_event'),'', 1, $options));
}
