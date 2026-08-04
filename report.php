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

use mod_quiz\local\reports\attempts_report;
use mod_quiz\question\bank\qbank_helper;
use mod_quiz\quiz_attempt;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/report/exportattemptscsv/export_form.php');
require_once($CFG->dirroot . '/mod/quiz/report/exportattemptscsv/export_options.php');
require_once($CFG->dirroot . '/mod/quiz/report/exportattemptscsv/export_table.php');


/**
 * Class to implement the report composition.
 */
class quiz_exportattemptscsv_report extends attempts_report {
    /** @var object Store options for the quiz export report (page mode, etc.) */
    private $options;

    /**
     * Override function to displays the report.
     * @param object $quiz this quiz.
     * @param object $cm the course-module for this quiz.
     * @param object $course the courses we are in.
     */
    public function display($quiz, $cm, $course) {
        global $DB, $OUTPUT;

        // This inits the quiz_attempts_report (parent class) functionality.
        [$currentgroup, $studentsjoins, $groupstudentsjoins, $allowedjoin] =
            $this->init('exportattemptscsv', 'quiz_exportattemptscsv_settings_form', $quiz, $cm, $course);

        // This creates a new options object and ...
        $this->options = new quiz_exportattemptscsv_options('exportattemptscsv', $quiz, $cm, $course);
        // ... takes the information from the form object
        if ($fromform = $this->form->get_data()) {
            $this->options->process_settings_from_form($fromform);
        } else {
            $this->options->process_settings_from_params();
        }
        // Write the information from options back to form (in case options changed due to params).
        $this->form->set_data($this->options->get_initial_form_data());

        $questions = quiz_report_get_significant_questions($quiz);

        $table = new quiz_exportattemptscsv_table(
            $quiz,
            $this->context,
            $this->qmsubselect,
            $this->options,
            $groupstudentsjoins,
            $studentsjoins,
            $questions,
            $this->options->get_url()
        );

        $hasstudents = false;
        if (!empty($studentsjoins->joins)) {
            $sql = "SELECT DISTINCT u.id
                    FROM {user} u
                    $studentsjoins->joins
                    WHERE $studentsjoins->wheres";
            $hasstudents = $DB->record_exists_sql($sql, $studentsjoins->params);
        }

        $hasgroupstudents = false;
        if (!empty($groupstudentsjoins->joins)) {
            $sql = "SELECT DISTINCT u.id
                      FROM {user} u
                    $groupstudentsjoins->joins
                     WHERE $groupstudentsjoins->wheres";
            $hasgroupstudents = $DB->record_exists_sql($sql, $groupstudentsjoins->params);
        }

        // Process actions.
        $this->process_actions($quiz, $cm, $currentgroup, $hasgroupstudents, $allowedjoin, $this->options->get_url());

        // Start output.

        // Print moodle headers (header, navigation, etc.).
        $this->print_header_and_tabs($cm, $course, $quiz, $this->mode);

        // Group selector.
        if ($groupmode = groups_get_activity_groupmode($cm)) {
            // Groups are being used, so output the group selector.
            groups_print_activity_menu($cm, $this->options->get_url());
        }

        $hasquestions = quiz_has_questions($quiz->id);
        if (!$hasquestions) {
            echo quiz_no_questions_message($quiz, $cm, $this->context);
        } else if (!$hasstudents) {
            echo $OUTPUT->notification(get_string('nostudentsyet'));
        } else if ($currentgroup && !$hasgroupstudents) {
            echo $OUTPUT->notification(get_string('nostudentsingroup'));
        }

        $this->form->display();

        $hasstudents = $hasstudents && (!$currentgroup || $hasgroupstudents);
        if ($hasquestions && ($hasstudents || $this->options->attempts == self::ALL_WITH)) {
            [$fields, $from, $where, $params] = $table->base_sql($allowedjoin);

            $table->set_sql($fields, $from, $where, $params);

            // Define table columns.
            $columns = [];
            $headers = [];

            if ($this->options->checkboxcolumn) {
                // Display a checkbox column for bulk export.
                $columnname = 'checkbox';
                $columns[] = $columnname;
                $headers[] = $table->checkbox_col_header($columnname);
            }

            $this->add_user_columns($table, $columns, $headers);

            $this->add_time_columns($columns, $headers);

            // Set up the table.
            $this->set_up_table_columns($table, $columns, $headers, $this->get_base_url(), $this->options, false);

            // Print the table.
            $table->out($this->options->pagesize, true);
        }
    }

    /**
     * Process any submitted actions.
     * @param object $quiz the quiz settings.
     * @param object $cm the cm object for the quiz.
     * @param int $currentgroup the currently selected group.
     * @param array $hasgroupstudents the students in the current group.
     * @param array $allowedjoin the users whose attempt this user is allowed to modify.
     * @param moodle_url $redirecturl where to redircet to after a successful action.
     */
    protected function process_actions($quiz, $cm, $currentgroup, $hasgroupstudents, $allowedjoin, $redirecturl) {
        global $DB;
        require_capability('quiz/exportattemptscsv:download', $this->context);
        if (empty($currentgroup) || $hasgroupstudents) {
            if (optional_param('export', 0, PARAM_BOOL) && confirm_sesskey()) {
                raise_memory_limit(MEMORY_HUGE);
                set_time_limit(600);

                if ($attemptids = optional_param_array('attemptid', [], PARAM_INT)) {
                    // Keep only attempts that belong to this quiz and to a user this report may show.
                    // IDOR mitigation suggested by MDLShield.
                    [$asql, $aparams] = $DB->get_in_or_equal($attemptids, SQL_PARAMS_NAMED);
                    $valid = $DB->get_fieldset_sql(
                        "SELECT quiza.id
                           FROM {quiz_attempts} quiza
                           JOIN {user} u ON u.id = quiza.userid
                           {$allowedjoin->joins}
                          WHERE quiza.quiz = :quizid
                            AND quiza.id $asql
                            AND {$allowedjoin->wheres}",
                        array_merge(['quizid' => $quiz->id], $aparams, $allowedjoin->params)
                    );
                    if ($valid) {
                        $this->export_attempts($quiz, $cm, $valid, $allowedjoin);
                    }
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
     * @param array $allowedjoin This list of userids that are visible in the report.
     *      Users can only export attempts that they are allowed to see in the report.
     *      Empty means all users.
     */
    protected function export_attempts($quiz, $cm, $attemptids, $allowedjoin) {
        global $DB, $CFG;

        require_capability('quiz/exportattemptscsv:download', $this->context);

        // Filesystem usage optimization suggested by MDLShield.
        $dir = make_request_directory();
        $tmpcsvfile = $dir . '/quiz_attempts_id' . $quiz->id . '.csv';
        $csvfile = fopen($tmpcsvfile, 'w');

        // QUERY suggestions from https://docs.moodle.org/dev/Overview_of_the_Moodle_question_engine#Detailed_data_about_an_attempt.
        // Extension to sqlsrv and oci suggested by MDLShield and GoogleGemini.
        $dbfamily = $DB->get_dbfamily();
        $sqlsetrownumber = "";

        if (in_array($dbfamily, ['postgres', 'sqlsrv', 'oci'])) {
            // Standard ANSI SQL window function supported by Postgres, SQL Server, and Oracle.
            $sqlquizattemptsdetails = "SELECT
                                       ROW_NUMBER() OVER (ORDER BY quiza.userid) AS num,";
        } else {
            // Fallback variable-assignment for MySQL and MariaDB.
            $sqlsetrownumber = "SET @row_number = 0";
            $sqlquizattemptsdetails = "SELECT
                                              (@row_number:=@row_number + 1) AS num,";
        }

        if ($this->options->showgdpr) {
            $sqlquizattemptsdetails .= " usertable.username,
                                         usertable.firstname,
                                         usertable.lastname,";
        }
        $sqlquizattemptsdetails .= " quiza.quiz,
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

        if ($this->options->showgdpr) {
            $sqlquizattemptsdetails .= "qas.userid,";
        }
        if ($this->options->showright) {
            $sqlquizattemptsdetails .= "qa.rightanswer,";
        }
        if ($this->options->showqtext) {
            $sqlquizattemptsdetails .= "qa.questionsummary,";
        }
        if ($this->options->showresponses) {
            $sqlquizattemptsdetails .= "qa.responsesummary,";
        }

        $sqlquizattemptsdetails .= " qasd.name";
        $sqlquizattemptsdetails .= " FROM {quiz_attempts} quiza
                                     JOIN {question_usages} qu ON qu.id = quiza.uniqueid
                                     JOIN {question_attempts} qa ON qa.questionusageid = qu.id
                                     JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
                                     JOIN {user} usertable ON usertable.id = quiza.userid
                                     LEFT JOIN {question_attempt_step_data} qasd ON qasd.attemptstepid = qas.id
                                     WHERE quiza.id = ? AND quiza.quiz = ?
                                     ORDER BY quiza.userid, quiza.attempt, qa.slot, qas.sequencenumber, qasd.name ";

        $header[] = get_string('seq', 'quiz_exportattemptscsv');
        if ($this->options->showgdpr) {
            $header[] = get_string('username');
            $header[] = get_string('firstname');
            $header[] = get_string('lastname');
        }
        $header[] = get_string('quizid', 'quiz_exportattemptscsv');
        $header[] = get_string('quizattemptid', 'quiz_exportattemptscsv');
        $header[] = get_string('quizattempt', 'quiz_exportattemptscsv');
        $header[] = get_string('quizasumgrades', 'quiz_exportattemptscsv');
        $header[] = get_string('qaslot', 'quiz_exportattemptscsv');
        $header[] = get_string('qaquestionid', 'quiz_exportattemptscsv');
        $header[] = get_string('qavariant', 'quiz_exportattemptscsv');
        $header[] = get_string('qamaxmark', 'quiz_exportattemptscsv');
        $header[] = get_string('qaminfraction', 'quiz_exportattemptscsv');
        $header[] = get_string('qaflagged', 'quiz_exportattemptscsv');
        $header[] = get_string('qassequencenumber', 'quiz_exportattemptscsv');
        $header[] = get_string('qasstate', 'quiz_exportattemptscsv');
        $header[] = get_string('qasfraction', 'quiz_exportattemptscsv');
        $header[] = get_string('qastimecreated', 'quiz_exportattemptscsv');
        if ($this->options->showgdpr) {
            $header[] = get_string('qasuserid', 'quiz_exportattemptscsv');
        }

        if ($this->options->showright) {
            $header[] = get_string('qarightanswer', 'quiz_exportattemptscsv');
        }
        if ($this->options->showqtext) {
            $header[] = get_string('qaqtext', 'quiz_exportattemptscsv');
        }
        if ($this->options->showresponses) {
            $header[] = get_string('qaresponses', 'quiz_exportattemptscsv');
        }

        $header[] = get_string('qasdname', 'quiz_exportattemptscsv');

        // Needed to Neutralize leading formula characters before writing.
        // Mitigation suggested by MDLShield.
        $harden = function ($v) {
            $v = (string)$v;
            if ($v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                $v = "'" . $v;
            }
            return $v;
        };

        fputcsv($csvfile, array_map($harden, (array)$header), escape: "");

        // For MySQL/MariaDB set first rownumber to zero.
        if ($sqlsetrownumber != "") {
            $DB->execute($sqlsetrownumber);
        }

        foreach ($attemptids as $attemptid) {
            [$asql, $aparams] = $DB->get_in_or_equal($attemptid, SQL_PARAMS_NAMED);
            $valid = $DB->get_fieldset_sql(
                "SELECT quiza.id
                   FROM {quiz_attempts} quiza
                   JOIN {user} u ON u.id = quiza.userid
                   {$allowedjoin->joins}
                  WHERE quiza.quiz = :quizid
                    AND quiza.id $asql
                    AND {$allowedjoin->wheres}",
                array_merge(['quizid' => $quiz->id], $aparams, $allowedjoin->params)
            );
            if ($valid) {
                $params = [$attemptid, $quiz->id];
                $quizattemptdetailsrs = $DB->get_records_sql($sqlquizattemptsdetails, $params);

                foreach ($quizattemptdetailsrs as $quizattemptdetails) {
                    // Convert UNIXTIME to readable format.
                    $quizattemptdetails->timecreated = userdate($quizattemptdetails->timecreated);
                    // Save record to CSV file.
                    fputcsv($csvfile, array_map($harden, (array)$quizattemptdetails), escape: "");
                }
            }
        }

        fclose($csvfile);
        header("Content-Type: text/csv; charset=UTF-8");
        header("Content-Disposition: attachment; filename=\"quiz_exportattempts_" . $quiz->id . ".csv\"");
        readfile($tmpcsvfile);

        unlink($tmpcsvfile);
        exit;
    }
}
