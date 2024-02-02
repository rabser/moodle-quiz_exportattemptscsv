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
 * This file defines the class to store the options for the quiz export attempts history report
 *
 * @package   quiz_exportattemptscsv
 * @copyright 2023 Sergio Rabellino - sergio.rabellino@unito.it
 * @copyright based on work by 2020 CBlue Srl
 * @copyright based on work by 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/report/attemptsreport_options.php');


/**
 * Class to store the options for the quiz export report.
 *
 * @package   quiz_exportattemptscsv
 * @copyright 2023 Sergio Rabellino
 * @copyright based on work by 2020 CBlue Srl
 * @copyright based on work by 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_exportattemptscsv_options extends mod_quiz_attempts_report_options {

    /** @var bool whether to show the question text columns. */
    public $showqtext = false;

    /** @var bool whether to show the students' response columns. */
    public $showresponses = false;

    /** @var bool whether to show the correct response columns. */
    public $showright = false;

    /** @var bool whether to show the gdpr sensible columns. */
    public $showgdpr = true;


    /**
     * Get the URL parameters required to show the report with these options.
     * @return array URL parameter name => value.
     */
    protected function get_url_params() {
        $params = parent::get_url_params();
        $params['qtext']      = $this->showqtext;
        $params['resp']       = $this->showresponses;
        $params['right']      = $this->showright;
        $params['gdpr']      = $this->showgdpr;
        return $params;
    }

    /**
     * Get the current value of the settings to pass to the settings form.
     */
    public function get_initial_form_data() {
        $toform = parent::get_initial_form_data();
        $toform->qtext      = $this->showqtext;
        $toform->resp       = $this->showresponses;
        $toform->right      = $this->showright;
        $toform->gdpr      = $this->gdpr;
        return $toform;
    }

    /**
     * Set the fields of this object from the form data.
     * @param object $fromform The data from $mform->get_data() from the settings form.
     */
    public function setup_from_form_data($fromform) {
        parent::setup_from_form_data($fromform);
        $this->showqtext     = $fromform->qtext;
        $this->showresponses = $fromform->resp;
        $this->showright     = $fromform->right;
        $this->showgdpr     = $fromform->gdpr;
    }

    /**
     * Set the fields of this object from the URL parameters.
     */
    public function setup_from_params() {
        parent::setup_from_params();
        $this->showqtext     = optional_param('qtext', $this->showqtext,     PARAM_BOOL);
        $this->showresponses = optional_param('resp',  $this->showresponses, PARAM_BOOL);
        $this->showright     = optional_param('right', $this->showright,     PARAM_BOOL);
        $this->showgdpr     = optional_param('gdpr', $this->showgdpr,     PARAM_BOOL);

    }

    public function setup_from_user_preferences() {
        parent::setup_from_user_preferences();

        $this->showqtext     = get_user_preferences('quiz_report_responses_qtext', $this->showqtext);
        $this->showresponses = get_user_preferences('quiz_report_responses_resp',  $this->showresponses);
        $this->showright     = get_user_preferences('quiz_report_responses_right', $this->showright);
        $this->showgdpr     = get_user_preferences('quiz_report_responses_gdpr', $this->showgdpr);
    }

    /**
     * Set the fields of this object from the user's preferences.
     * (For those settings that are backed by user-preferences).
     */
    public function update_user_preferences() {
        parent::update_user_preferences();

        set_user_preference('quiz_report_responses_qtext', $this->showqtext);
        set_user_preference('quiz_report_responses_resp',  $this->showresponses);
        set_user_preference('quiz_report_responses_right', $this->showright);
        set_user_preference('quiz_report_responses_gdpr', $this->showgdpr);
    }

    /**
     * Check the settings, and remove any 'impossible' combinations.
     */
    public function resolve_dependencies() {
        $this->checkboxcolumn = true;
    }
}
