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

function get_filetop($namespace, $relativefilepath, $testfilename) {

    $thisyear = date('Y');
    $classname = basename($testfilename, '.php');
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

function is_moodleform_class($extendedclasses) {
    // This function will not find classes extending classes that extend moodleform or mod_moodleform.
    foreach ($extendedclasses as $extendedclass) {
        if (stripos($extendedclass->name, 'moodleform') !== false) {
            return true;
        }
    }
    return false;
}

function is_ui_facing($requires, $pluginfilepath) {

    // Get the file depth to compare relative depth for config.php.
    $filedepth = count(explode('/', ltrim(dirname($pluginfilepath), '/')));
    foreach ($requires as $required) {
        $requiredfile = ltrim(trim($required->name, '"\''), '/');
        if (basename($requiredfile) == 'config.php') {
            // Let's double check by checking file depth - that this is the root config.php.
            if ($filedepth == count(explode('/', dirname($requiredfile)))) {
                return true;
            }
        }
        break;
    }
    return false;
}

function get_trigger_testlines($classname) {

    return
'
    /**
     * Test the triggering of the event.
     */
    function test_trigger() {
        $this->resetAfterTest();

        $sink = $this->redirectEvents();

        /* Here ensure to define the event properties that are required */
        $eventdata = array(
            "other" => array("message" => "This is just a test")
        );

        $event = ' . $classname . '::create($eventdata);
        $event->trigger();

        $events = $sink->get_events();
        $this->assertGreaterThan(0, count($events));

        foreach ($events as $event) {
            if ($event instanceof ' . $classname . ') {
                break;  // The variable $event will be the correct event.
            }
        }
        // This will fail if the event is not found.
        $this->assertInstanceOf(' . "'" . $classname . "'" . ', $event);
    }

';

}

function is_event_class($namespace, $extendedclasses, $file) {

    // Quickest way to find it
    foreach ($extendedclasses as $extendedclass) {
        if ($extendedclass->name == '\core\event\base'){
            return true;
        }
    }
    // In case we are extending a class that extends \core\event\base.
    if (is_object($namespace)) {        // Events always are in namsepaces.
        if (strpos($namespace->name, '/event') !== false && strpos($file, 'classes/event') !== false) {
            return true;
        }
    }

    return false;
}
