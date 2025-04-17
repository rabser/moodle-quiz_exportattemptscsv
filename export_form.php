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
 * This file defines the setting form for the quiz export attempts history report.
 *
 * @package   quiz_exportattemptscsv
 * @copyright 2023-2025 Sergio Rabellino - sergio.rabellino@unito.it
 * @copyright based on work by 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_quiz\local\reports\attempts_report;
use mod_quiz\local\reports\attempts_report_options_form;


/**
 * Quiz export report settings form.
 *
 * @package   quiz_exportattemptscsv
 * @copyright 2023 Sergio Rabellino
 * @copyright 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_exportattemptscsv_settings_form extends attempts_report_options_form {

    /**
     * Define extended preference fields.
     * @param \MoodleQuickForm $mform the wrapped MoodleQuickForm.
     */
    protected function other_preference_fields(MoodleQuickForm $mform) {
        $mform->addGroup([
            $mform->createElement('advcheckbox', 'qtext', '',
                get_string('questiontext', 'quiz_responses')),
            $mform->createElement('advcheckbox', 'resp', '',
                get_string('response', 'quiz_responses')),
            $mform->createElement('advcheckbox', 'right', '',
                get_string('rightanswer', 'quiz_responses')),
            $mform->createElement('advcheckbox', 'gdpr', '',
                get_string('gdprready', 'quiz_exportattemptscsv')),
        ], 'coloptions', get_string('showthe', 'quiz_responses'), [' '], false);
        $mform->disabledIf('qtext', 'attempts', 'eq', attempts_report::ENROLLED_WITHOUT);
        $mform->disabledIf('resp',  'attempts', 'eq', attempts_report::ENROLLED_WITHOUT);
        $mform->disabledIf('right', 'attempts', 'eq', attempts_report::ENROLLED_WITHOUT);
        $mform->disabledIf('gdpr', 'attempts', 'eq', attempts_report::ENROLLED_WITHOUT);
    }

    /**
     * Preferences validation.
     *
     * @param  \stdClass $data Data to validate.
     * @param  array $files Array of files.
     * @return array of additional errors, or overridden errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        return $errors;
    }
}
