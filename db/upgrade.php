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
 * Post-install script for the quiz attempts history export report.
 * @package   quiz_exportattemptscsv
 * @copyright 2026 Sergio Rabellino - sergio.rabellino@unito.it
 * @copyright  based on work by 2013 Tim Hunt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Quiz exportattemptscsv report upgrade code.
 */
function xmldb_quiz_exportattemptscsv_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026073001) {
        // Add the missing capability.
        if ($dbman->table_exists('quiz_reports')) {
            $record = $DB->get_record('quiz_reports', ['name' => 'exportattemptscsv']);
            $record->capability = 'quiz/exportattemptscsv:download';
            $DB->update_record('quiz_reports', $record);
        } else {
            $record = $DB->get_record('quiz_report', ['name' => 'exportattemptscsv']);
            $record->capability = 'quiz/exportattemptscsv:download';
            $DB->update_record('quiz_report', $record);
        }
        upgrade_plugin_savepoint(true, 2026073001, 'quizreport', 'exportattemptscsv');
    }

    return true;
}
