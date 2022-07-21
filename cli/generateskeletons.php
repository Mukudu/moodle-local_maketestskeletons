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
 * CLI script.
 *
 * @package   local_maketestskeletons
 * @copyright 2019 - 2021 Mukudu Ltd - Bham UK
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

if (isset($_SERVER['REMOTE_ADDR'])) {
    die; // no access from web!
}

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/local_maketestskeletons_file.php');
require_once(__DIR__ .'/../locallib.php');
global $CFG;
require_once($CFG->libdir . '/clilib.php');

$testsdir = 'tests/';       // Trailing slash required.
// $excludefiles = array('/settings.php', '/version.php');
// $excludedirs = array('/tests', '/db', '/lang/en');

$excludefiles = array();
$excludedirs = array('/db');

// Add the tests folder to the directory exclusions.
$excludedirs[] = '/' . trim($testsdir, '/');

// Test Moodle logo
cli_logo();

list($options, $unrecognized) = cli_get_params(
    [
        'help'                 => false,
        'plugin-path'          => false,
        'purge'                => false
    ],
    [
        'h' => 'help',
    ]
);

if ($unrecognized) {
    $message = 'Unrecognised parameters';
    foreach ($unrecognized as $param => $value) {
        $message .=  " $param,";
    }
    echo rtrim($message, ',');
}

$help = "
Generates PHPUnit Test skeleton files.

Usage:
  php generateskeletons.php [--plugin-path=path/to/plugin] [--help]

--plugin-path
        is required and must exist.
--purge
        overwrite existing files.

-h, --help          Print out this help

Example from Moodle root directory:
\$ php local/maketestskeletons/cli/generateskeletons.php --plugin-path=local/housekeeping

";

// Check the plugin directory.
$plugin = new \stdClass();
$fullpluginpath = '';
if (!empty($options['plugin-path'])) {
    // Clear leading or trailing slashes
    $options['plugin-path'] = trim($options['plugin-path'], '/');
    $fullpluginpath = $CFG->dirroot . "/" . $options['plugin-path'] . "/";
    if (!file_exists($fullpluginpath)) {
        echo "\n**Directory not found\n";
        $options['plugin-path'] = false;
    } else {
        $versionfile = $fullpluginpath . "version.php";
        if (file_exists($versionfile)) {
            require_once($versionfile);
        } else {
            echo "\n**Version file is missing - is this a plugin?\n";
            $options['plugin-path'] = false;
        }
    }
}

// Check for a sub directory e.g. local/myplugin.
$pos = strpos($options['plugin-path'], '/');
if ($pos === false) {       // Should not happen.
    echo "\n**No sub path has been specified\n";
    $options['plugin-path'] = false;
}

if (!empty($options['help']) || empty($options['plugin-path'])) {
    echo $help;
    exit(0);
}

// Namespace for plugin.
$testnamespace = substr_replace($options['plugin-path'], '_', $pos, 1);

// Now let's get all possible testable php files.
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fullpluginpath));
$filedets = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);
foreach ($filedets as $files) {
    foreach ($files as $file) {
        // Relative path name
        $relativefile = str_replace($fullpluginpath, '/', $file);

        /*
         * Check exclusions.
         */
        // Is it in excluded directory?
        $relativedir = dirname($relativefile);
        if (!in_array($relativedir, $excludedirs)) {
            while(dirname($relativedir) != '/') {
                $relativedir = dirname($relativedir);
            }
        } else {
            continue;
        }
        // Is it in the excluded files list?
        if (in_array($relativefile, $excludefiles)) {
            continue;
        }

        // TODO BEN remove.
//         if (basename($relativefile) != 'lib.php') {
//             continue;
//         }

        // TODO Ben - variable names should only have alphanumeric chars only

        echo "Processing $file\n";

        // Check for existing file.
        if (dirname($relativefile) != '/') {
            $pathbit = str_replace('/', '_', trim(dirname($relativefile), '/')) . '_';
        } else {
            $pathbit = '';
        }

        $testfilename = $fullpluginpath . $testsdir . $pathbit . basename($file, '.php') . '_test.php';
        if (!$options['purge'] && file_exists($testfilename)) {
            echo("Test file exists for '$relativefile', skipping generation\n");
            continue;
        }

        $filelines = '';
        $filelines .= get_filetop($testnamespace, ltrim($relativefile, '/'), $testfilename);

        // This is why we require local/moodlecheck
        $parsefile = new local_maketestskeletons_file($file);

        //         $tokens = $parsefile->get_tokens();
        //         die(print_r($tokens, true));

        // Check if this is a UI facing script.
        if ($requires = $parsefile->get_requires()) {
            if (is_ui_facing($requires, str_replace($CFG->dirroot, '', $file))) {
                continue;
            }
        }

        $classes = $parsefile->get_classes();
        if (count($classes) > 1) {
            die('Cannot deal with files with multiple classes');
        }

        $functions = $parsefile->get_functions();
        // If there are no funtions - then we have nothing to do.
        if (empty($functions)) {
            continue;
        }

        $namespace = null;
        $extendedclasses = null;
        $classname = '';
        $iseventclass = false;
        $classlines = array();
        if (count($classes)) {  // Will only be one.

            if ($namespace = $parsefile->get_namespace()) {
                $classname = '\\' . $namespace->name . '\\' . $classes[0]->name;
            } else {
                $classname = '\\' . $classes[0]->name;
            }

            //die(print_r($namespace, true)) . "\n";

            $classvarname = $classes[0]->name;

            $extendedclasses = $parsefile->get_extendedclasses();

            if ($extendedclasses && count($extendedclasses)) {
                // We do not test moodleforms.
                if (is_moodleform_class($extendedclasses)) {
                    continue;
                }

                // Check if we are an events class
                $iseventclass = is_event_class($namespace, $extendedclasses, $file);
            }

            // Let's see if there is a constructor method
            if (!$iseventclass) {
                foreach ($functions as $function) {
                    if ($function->name == '__construct') {
                        $argsnippet = '';
                        if (count($function->arguments)) {
                            foreach ($function->arguments as $arg) {
                                if ($argmt = $arg[1]) {
                                    $classlines[] = "\t\t$argmt = null;\t// Provide a value here.";
                                    if ($argsnippet) {
                                        $argsnippet .= ', ';
                                    }
                                    $argsnippet .= $argmt;
                                }
                            }
                        }
                        $classlines[] = "\t\t" . '$' . $classvarname . " = new $classname($argsnippet);";
                    }
                }
            } else {            // Constructor for events is different.
                // $event = \local_course_history\event\snapshot_backedup::create($eventdata);
                $classlines[] = "\t\t" . '/* Here ensure to define the event properties that are required */';
                $classlines[] = "\t\t" . '$eventdata = array(';
                $classlines[] = "\t\t\t" . '"other" => array("message" => "This is just a test")';
                $classlines[] = "\t\t" . ');';
                $classlines[] = "\t\t" . '$' . "$classvarname = $classname::create(\$eventdata);";
            }
            if (empty($classlines)) {
                $classlines[] = "\t\t" . '$' . $classvarname . " = new $classname(); // This may cause errors as we do not know the arguments we need.";
            }
        }

        foreach ($functions as $function) {
            // Ignore magic functions
            if (preg_match('/^__/', $function->name)) {
                // echo "Ignoring " . $function->name . "\n";
                continue;
            }
            // Ignore private, protected functions
            $ispublic = false;
            $isstatic = false;
            if (empty($function->accessmodifiers)) {
                $ispublic = true;
            } else {
                foreach($function->accessmodifiers as $accessmodifier) {
                    switch ($accessmodifier) {
                        case T_PUBLIC :
                            $ispublic = true;
                            break;
                        case T_STATIC :
                            $isstatic = true;
                            break;
                    }
                }
            }

            if (!$ispublic) {
                continue;
            }

            // PHP Doc for test function.
            $methodlines = "\t/**\n\t * Testing {$function->name}()\n \t */\n";

            // Function lines.
            $testmethodname = 'test_' . $function->name;
            $methodlines .= "\tpublic function $testmethodname() {\n\n";

            $methodlines .=  get_pending_lines();
            $variablename = '$' . $function->name;

            if (!$isstatic && count($classlines)) {    // Class methods tests.
                $methodlines .= implode("\n", $classlines) . "\n\n";
            }

            $argsnippet = '';
            foreach ($function->arguments as $arg) {
                if ($argmt = $arg[1]) {
                    $methodlines .= "\t\t$argmt = null;\t// Provide a value here.\n";
                    if ($argsnippet) {
                        $argsnippet .= ', ';
                    }
                    $argsnippet .= $argmt;
                }
            }

            if ($classname && !$isstatic) {
                $methodlines .= "\t\t" . $variablename . ' = $' . $classvarname . '->' . $function->name . "($argsnippet);\n";
            } else if ($classname && $isstatic) {
                if ($namespace) {
                    $staticfuncall = '\\' . $namespace->name . '\\' . $function->fullname;
                } else {
                    $staticfuncall = '\\' . $function->fullname;
                }
                $methodlines .= "\t\t$variablename = $staticfuncall($argsnippet);\n";
            } else {
                $methodlines .= "\t\t$variablename = " . $function->name . "($argsnippet);\n";
            }

            // Add in a final assertion.
            $methodlines .= "\t\t" . '$this->assertNotEmpty(' . $variablename . ", 'Provide a better assertion here!');\n";

            $methodlines .= "\t}\n\n";

            $filelines .= $methodlines;

        }

        if ($iseventclass) {
            $filelines .= get_trigger_testlines($classname);
        }

        $filelines .= get_file_end();

        file_put_contents($testfilename, $filelines);

        break;
    }
}

echo "\nTest Skeleton Generation complete\n";


