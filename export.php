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
 * This file contains the Export Quiz: exporting the file related code.
 *
 * @package    block_export_quiz
 * @copyright  2019 onwards Ashish Pawar (github : CustomAP)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');

// Get the parameters from the URL.
$quizid = required_param('id', PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

if ($courseid) {
    require_login($courseid);
    $thiscontext = context_course::instance($courseid);
    $urlparams['courseid'] = $courseid;
} else {
    print_error('missingcourseorcmid', 'question');
}

// require_sesskey(); 

// Load the necessary data.
$contexts = new question_edit_contexts($thiscontext);
$questiondata = array();
if ($questions = $DB->get_records('quiz_slots', array('quizid' => $quizid))) {
    foreach ($questions as $question) {
        array_push($questiondata, question_bank::load_question_data($question->questionid));
    }   
}

/**
 * Check if the Quiz is visible to the user only then display it : 
 * Teacher can choose to hide the quiz from the students in that case it should not be visible to students
 */
$modinfo = get_fast_modinfo($courseid);
$cm = $modinfo->get_cm($DB->get_record('course_modules', array('module' => 16, 'instance' => $quizid))->id);
if(!$cm->uservisible)
    print_error('noaccess', 'block_export_quiz');


// Initialise $PAGE. Nothing is output, so this does not really matter. Just avoids notices.
$nexturl = new moodle_url('/question/type/stack/questiontestrun.php', $urlparams);
$PAGE->set_url('/blocks/export_quiz/export.php', $urlparams);
$PAGE->set_heading(get_string('pluginname','block_export_quiz'));
$PAGE->set_pagelayout('admin');

// Set up the export format.
$qformat = new qformat_xml();
$qformat->setContexts($contexts->having_one_edit_tab_cap('export'));
$qformat->setCourse($COURSE);
$qformat->setCattofile(false);
$qformat->setContexttofile(false);
$qformat->setQuestions($questiondata);

// Get quiz name to assign it to file name used for exporting
$filename = get_string('quiz', 'block_export_quiz');
if ($quiz = $DB->get_record('quiz', array('id' => $quizid))) {
    $filename = $quiz->name;
}


// Pre-processing the export
if (!$qformat->exportpreprocess()) {
    send_file_not_found();
}

// Actual export process to get the converted string
if (!$content = $qformat->exportprocess(true)) {
    send_file_not_found();
}

send_file($content, $filename, 0, 0, true, true, $qformat->mime_type());
