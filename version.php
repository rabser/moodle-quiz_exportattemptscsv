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
 * Quiz export attempts history as csv report version information.
 *
 * @package   quiz_exportattemptscsv
 * @copyright 2023 Sergio Rabellino - sergio.rabellino@unito.it
 * @copyright based on work by 2020 CBlue Srl
 * @copyright based on work by 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version = 2024020200;
$plugin->requires = 2020061500;
$plugin->maturity = MATURITY_STABLE;
$plugin->release = '1.0.3c (Build 2024032400)';
$plugin->component = 'quiz_exportattemptscsv';
