<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Library functions file.
 *
 * @package   local_maketestskeletons
 * @copyright 2019 - 2021 Mukudu Ltd - Bham UK
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function get_filetop($namespace, $relativefilepath) {

    $thisyear = date('Y');
    $classname = 'test_' . basename($relativefilepath, '.php');
    return
    "<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace $namespace;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../$relativefilepath');

/**
 * Test script for $relativefilepath.
 *
 * @package     $namespace
 * @copyright   $thisyear
 * @author
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class $classname extends \advanced_testcase {

";
}

function get_file_end() {
    return "}\n";
}

function get_pending_lines() {
    $pendinglines = '';
    $pendinglines .= "\t\t" . '$this->resetAfterTest(false);' . "\n";
    $pendinglines .= "\t\t" . '// Fail this test for now.' . "\n";
    $pendinglines .= "\t\t" . '$this->assertTrue(false, "This test needs to be completed");' . "\n\n";
    return $pendinglines;
}

function is_ui_facing(\local_moodlecheck_file $moodlecheckfile, $pluginfilepath) {

    // Get the file depth to compare relative depth for config.php.
    $filedepth = count(explode('/', ltrim(dirname($pluginfilepath), '/')));

    $tokens = $moodlecheckfile->get_tokens();
    $tokenscount = count($tokens);
    for ($tid = 0; $tid < $tokenscount; $tid++) {
        if ($tokens[$tid][0] == T_REQUIRE || $tokens[$tid][0] == T_REQUIRE_ONCE) {
            for ($tid++; $tid < $tokenscount; $tid++) {
                if ($tokens[$tid][0] == T_CONSTANT_ENCAPSED_STRING) {
                    $requiredfile = ltrim(trim($tokens[$tid][1], '"\''), '/');
                    if (basename($requiredfile) == 'config.php') {
                        // Let's double check by checking file depth - that this is the root config.php.
                        if ($filedepth == count(explode('/', dirname($requiredfile)))) {
                            return true;
                        }
                    }
                    break;
                }
            }
        }
    }
    return true;
}
