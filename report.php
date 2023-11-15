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
 * This file defines the quiz export attempts history report class.
 *
 * @package   quiz_exportattemptscsv
 * @copyright 2023 Sergio Rabellino - sergio.rabellino@unito.it
 * @copyright based on work by 2020 CBlue Srl
 * @copyright based on work by 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport.php');
require_once($CFG->dirroot . '/mod/quiz/report/exportattemptscsv/export_form.php');
require_once($CFG->dirroot . '/mod/quiz/report/exportattemptscsv/export_options.php');
require_once($CFG->dirroot . '/mod/quiz/report/exportattemptscsv/export_table.php');

/**
 * Quiz report subclass for the export attempts history report.
 *
 * @package   quiz_exportattemptscsv
 * @copyright 2023 Sergio Rabellino - sergio.rabellino@unito.it
 * @copyright based on work by 2020 CBlue Srl
 * @copyright based on work by 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_exportattemptscsv_report extends quiz_attempts_report
{

    /** @var object Store options for the quiz export report (page mode, etc.) */
    private $options;

    public function display($quiz, $cm, $course)
    {
        global $OUTPUT;

        // This inits the quiz_attempts_report (parent class) functionality
        list($currentgroup, $students, $groupstudents, $allowed) =
            $this->init('exportattemptscsv', 'quiz_exportattemptscsv_settings_form', $quiz, $cm, $course);

        // This creates a new options object and ...
        $this->options = new quiz_exportattemptscsv_options('exportattemptscsv', $quiz, $cm, $course);
        // ... takes the information from the form object
        if ($fromform = $this->form->get_data()) {
            $this->options->process_settings_from_form($fromform);
        } else {
            $this->options->process_settings_from_params();
        }
        // write the information from options back to form (in case options changed due to params)
        $this->form->set_data($this->options->get_initial_form_data());

        $questions = quiz_report_get_significant_questions($quiz);

        $table = new quiz_exportattemptscsv_table($quiz, $this->context, $this->qmsubselect,
            $this->options, $groupstudents, $students, $questions, $this->options->get_url());


        // Process actions
        $this->process_actions($quiz, $cm, $currentgroup, $groupstudents, $allowed, $this->options->get_url());

        // Start output.

        // Print moodle headers (header, navigation, etc.) only if not downloading
        if (!$table->is_downloading()) {
            $this->print_header_and_tabs($cm, $course, $quiz, $this->mode);
        }

        // Group selector
        if ($groupmode = groups_get_activity_groupmode($cm)) {
            // Groups are being used, so output the group selector
            groups_print_activity_menu($cm, $this->options->get_url());
        }

        $hasquestions = quiz_has_questions($quiz->id);
        if (!$hasquestions) {
            echo quiz_no_questions_message($quiz, $cm, $this->context);
        } else if (!$students) {
            echo $OUTPUT->notification(get_string('nostudentsyet'));
        } else if ($currentgroup && !$groupstudents) {
            echo $OUTPUT->notification(get_string('nostudentsingroup'));
        }

        $this->form->display();

        $hasstudents = $students && (!$currentgroup || $groupstudents);
        if ($hasquestions && ($hasstudents || $this->options->attempts == self::ALL_WITH)) {
            list($fields, $from, $where, $params) = $table->base_sql($allowed);

            $table->set_sql($fields, $from, $where, $params);

            // Define table columns.
            $columns = array();
            $headers = array();

            if (!$table->is_downloading() && $this->options->checkboxcolumn) {
                $columnname = 'checkbox';
                $headers[] = $table->checkbox_col_header($columnname);
            }

            // Display a checkbox column for bulk export
            $columns[] = 'checkbox';
            $headers[] = null;

            $this->add_user_columns($table, $columns, $headers);

            // $this->add_state_column($columns, $headers);
            $this->add_time_columns($columns, $headers);

            // Set up the table.
            $this->set_up_table_columns($table, $columns, $headers, $this->get_base_url(), $this->options, false);

            // Print the table
            $table->out($this->options->pagesize, true);
        }
    }

    /**
     * Process any submitted actions.
     * @param object $quiz the quiz settings.
     * @param object $cm the cm object for the quiz.
     * @param int $currentgroup the currently selected group.
     * @param array $groupstudents the students in the current group.
     * @param array $allowed the users whose attempt this user is allowed to modify.
     * @param moodle_url $redirecturl where to redircet to after a successful action.
     */
    protected function process_actions($quiz, $cm, $currentgroup, $groupstudents, $allowed, $redirecturl)
    {
        if (empty($currentgroup) || $groupstudents) {
            if (optional_param('export', 0, PARAM_BOOL) && confirm_sesskey()) {
                raise_memory_limit(MEMORY_HUGE);
                set_time_limit(600);
                if ($attemptids = optional_param_array('attemptid', array(), PARAM_INT)) {
                    $this->export_attempts($quiz, $cm, $attemptids, $allowed);
                    redirect($redirecturl);
                }
            }
        }
    }

    /**
     * Export the quiz attempts
     * @param object $quiz the quiz settings.
     * @param object $cm the course_module object.
     * @param array $attemptids the list of attempt ids to export.
     * @param array $allowed This list of userids that are visible in the report.
     *      Users can only export attempts that they are allowed to see in the report.
     *      Empty means all users.
     */
    protected function export_attempts($quiz, $cm, $attemptids, $allowed)
    {
        global $DB,$CFG;

        $tmp_dir = $CFG->tempdir;
        $tmp_file = tempnam($tmp_dir, "quiz_attempts_id".$quiz->id."_");
        $tmp_csv_file = $tmp_file . ".csv";
        rename($tmp_file, $tmp_csv_file);
        chmod($tmp_csv_file, 0644);

	// QUERY suggestions from https://docs.moodle.org/dev/Overview_of_the_Moodle_question_engine#Detailed_data_about_an_attempt
	$sqlSetRowNumber="SET @row_number = 0";
	$sqlQuizAttemptDetails="SELECT
    		(@row_number:=@row_number + 1) AS num,";
	if ( $this->options->showgdpr ) {
		$sqlQuizAttemptDetails.= " user.username,
    					   user.firstname,
    					   user.lastname,";
	}
    	$sqlQuizAttemptDetails.= " quiza.quiz,
    		quiza.id AS quizattemptid,
    		quiza.attempt,
    		quiza.sumgrades,
    		qa.slot,
    		qa.questionid,
    		qa.variant,
    		qa.maxmark,
    		qa.minfraction,
    		qa.flagged,
    		qas.sequencenumber,
    		qas.state,
    		qas.fraction,
    		qas.timecreated,";

	if ( $this->options->showgdpr ) {
 	 	$sqlQuizAttemptDetails.= "qas.userid,";
	}
	if ( $this->options->showright ) {
		$sqlQuizAttemptDetails.= "qa.rightanswer,";
	}
	if ( $this->options->showqtext ) {
		$sqlQuizAttemptDetails.= "qa.questionsummary,";
	}
	if ( $this->options->showresponses ) {
		$sqlQuizAttemptDetails.= "qa.responsesummary,";
	}

        $sqlQuizAttemptDetails.= "qasd.name
		
		FROM mdl_quiz_attempts quiza
		JOIN mdl_question_usages qu ON qu.id = quiza.uniqueid
		JOIN mdl_question_attempts qa ON qa.questionusageid = qu.id
		JOIN mdl_question_attempt_steps qas ON qas.questionattemptid = qa.id
		JOIN mdl_user user ON user.id = quiza.userid
		LEFT JOIN mdl_question_attempt_step_data qasd ON qasd.attemptstepid = qas.id
		
		WHERE quiza.id = ? 
		
		ORDER BY quiza.userid, quiza.attempt, qa.slot, qas.sequencenumber, qasd.name 
		";
		
	$csvFile = fopen($tmp_csv_file, 'w');

	$header[]=get_string('seq', 'quiz_exportattemptscsv');
	if ( $this->options->showgdpr ) {
		$header[]=get_string('username');
		$header[]=get_string('firstname');
		$header[]=get_string('lastname');
	}
	$header[]=get_string('quizid', 'quiz_exportattemptscsv');
	$header[]=get_string('quizattemptid', 'quiz_exportattemptscsv');
	$header[]=get_string('quizattempt', 'quiz_exportattemptscsv');
	$header[]=get_string('quizasumgrades', 'quiz_exportattemptscsv');
	$header[]=get_string('qaslot', 'quiz_exportattemptscsv');
	$header[]=get_string('qaquestionid', 'quiz_exportattemptscsv');
	$header[]=get_string('qavariant', 'quiz_exportattemptscsv');
	$header[]=get_string('qamaxmark', 'quiz_exportattemptscsv');
	$header[]=get_string('qaminfraction', 'quiz_exportattemptscsv');
	$header[]=get_string('qaflagged', 'quiz_exportattemptscsv');
	$header[]=get_string('qassequencenumber', 'quiz_exportattemptscsv');
	$header[]=get_string('qasstate', 'quiz_exportattemptscsv');
	$header[]=get_string('qasfraction', 'quiz_exportattemptscsv');
	$header[]=get_string('qastimecreated', 'quiz_exportattemptscsv');
	if ( $this->options->showgdpr ) {
		$header[]=get_string('qasuserid', 'quiz_exportattemptscsv');
	}

	if ( $this->options->showright ) {
		$header[]= get_string('qarightanswer', 'quiz_exportattemptscsv');
	}
	if ( $this->options->showqtext ) {
		$header[]= get_string('qaqtext', 'quiz_exportattemptscsv');
	}
	if ( $this->options->showresponses ) {
		$header[]= get_string('qaresponses', 'quiz_exportattemptscsv');
	}

	$header[]=get_string('qasdname', 'quiz_exportattemptscsv');

	fputcsv ($csvFile, array_map(fn($v) => $v.' ', $header) );

	$DB->execute($sqlSetRowNumber);

        foreach ($attemptids as $attemptid) {
		$params[0]=$attemptid;
		$quizAttemptDetailsRs = $DB->get_records_sql($sqlQuizAttemptDetails,$params);

		foreach ($quizAttemptDetailsRs as $quizAttemptDetails) {
			// Convert UNIXTIME to readable format
			$quizAttemptDetails->timecreated = userdate($quizAttemptDetails->timecreated);
			// save record to CSV file
			fputcsv ($csvFile, array_map(fn($v) => $v.' ', json_decode(json_encode($quizAttemptDetails), true)) );
		}
	}

        header("Content-Type: text/csv; charset=UTF-8");
        header("Content-Disposition: attachment; filename=\"quiz_exportattempts_".$quiz->id.".csv\"");
        readfile($tmp_csv_file);

	unlink($tmp_csv_file);
	exit;
    }
}
