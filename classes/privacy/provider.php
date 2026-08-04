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
 * This file defines the quiz export attempts privacy provider
 *
 * @package   quiz_exportattemptscsv
 * @copyright 2024 Sergio Rabellino - sergio.rabellino@unito.it
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quiz_exportattemptscsv\privacy;

/**
 * Class to implement user-preference provider and declare the preference(s) the plugin owns.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\user_preference_provider {
    /**
     * Returns meta data about this system.
     *
     * @param  collection $collection The initialised item collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_user_preference('quiz_exportattemptscsv_qtext', 'privacy:preference:qtext');
        $collection->add_user_preference('quiz_exportattemptscsv_resp', 'privacy:preference:resp');
        $collection->add_user_preference('quiz_exportattemptscsv_right', 'privacy:preference:right');
        $collection->add_user_preference('quiz_exportattemptscsv_gdpr', 'privacy:preference:gdpr');
        return $collection;
    }

    /**
     * Store all user preferences for the plugin.
     *
     * @param  int $userid The userid of the user whose data is to be exported.
     */
    public static function export_user_preferences(int $userid) {
        $pref = get_user_preferences('quiz_exportattemptscsv_qtext', null, $userid);
        if ($pref !== null) {
            writer::export_user_preference(
                'quiz_exportattemptscsv',
                'qtext',
                transform::yesno($pref),
                get_string('privacy:preference:qtext', 'quiz_exportattemptscsv')
            );
        }
        $pref = get_user_preferences('quiz_exportattemptscsv_resp', null, $userid);
        if ($pref !== null) {
            writer::export_user_preference(
                'quiz_exportattemptscsv',
                'resp',
                transform::yesno($pref),
                get_string('privacy:preference:resp', 'quiz_exportattemptscsv')
            );
        }
        $pref = get_user_preferences('quiz_exportattemptscsv_right', null, $userid);
        if ($pref !== null) {
            writer::export_user_preference(
                'quiz_exportattemptscsv',
                'right',
                transform::yesno($pref),
                get_string('privacy:preference:right', 'quiz_exportattemptscsv')
            );
        }
        $pref = get_user_preferences('quiz_exportattemptscsv_gdpr', null, $userid);
        if ($pref !== null) {
            writer::export_user_preference(
                'quiz_exportattemptscsv',
                'gdpr',
                transform::yesno($pref),
                get_string('privacy:preference:gdpr', 'quiz_exportattemptscsv')
            );
        }
    }
}
