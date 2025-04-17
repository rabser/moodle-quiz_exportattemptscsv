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
 * This file defines the quiz attempts history table.
 *
 * @package   quiz_exportattemptscsv
 * @copyright 2023-2025 Sergio Rabellino
 * @copyright based on work by 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_quiz\local\reports\attempts_report_table;
use mod_quiz\quiz_attempt;

/**
 * This is a table subclass for displaying the quiz export attempts history report.
 *
 * @package   quiz_exportattemptscsv
 * @copyright 2023 Sergio Rabellino
 * @copyright based on work by 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_exportattemptscsv_table extends attempts_report_table {

    /**
     * Constructor
     * @param object $quiz
     * @param context $context
     * @param string $qmsubselect
     * @param quiz_exportattemptscsv_options $options
     * @param \core\dml\sql_join $groupstudentsjoins
     * @param \core\dml\sql_join $studentsjoins
     * @param array $questions
     * @param moodle_url $reporturl
     */
    public function __construct($quiz, $context, $qmsubselect,
                                quiz_exportattemptscsv_options $options,
                                $groupstudentsjoins, $studentsjoins, $questions, $reporturl) {
        parent::__construct('mod-quiz-report-export-report', $quiz, $context,
                            $qmsubselect, $options, $groupstudentsjoins,
                            $studentsjoins, $questions, $reporturl);
    }

    /**
     * Build the results table.
     */
    public function build_table() {
        if (!$this->rawdata) {
            return;
        }

        $this->strtimeformat = str_replace(',', ' ', get_string('strftimedatetime'));
        parent::build_table();
    }

    /**
     * Add the download button.
     */
    protected function submit_buttons() {
        global $PAGE;
        echo '<input type="submit" id="exportattemptsbutton" name="export" value="' .
            get_string('exportselected', 'quiz_exportattemptscsv') . '"/>';
        $PAGE->requires->event_handler('#exportattemptsbutton', 'click', 'M.util.show_confirm_dialog',
            ['message' => get_string('exportattemptcheck', 'quiz_exportattemptscsv')]);
    }
}
