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

global $CFG;
require_once($CFG->dirroot . '/local/moodlecheck/file.php');

/**
 * File class
 *
 * @package   local_maketestskeletons
 * @copyright 2019 - 2021 Mukudu Ltd - Bham UK
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_maketestskeletons_file extends local_moodlecheck_file {

    /* @var $varsdefined - have we filled in our protected variables? */
    private $varsdefined = false;

    /* @var $includes - the file's include and include_once */
    protected $includes = null;

    /* @var $requires - the file's require and require_once */
    protected $requires = null;

    /* @var $namespace - the namespace if there is one */
    protected $namespace = null;

    /* @var $extendedclasses - the extended classes */
    protected $extendedclasses = null;

    /**
     * Cleares all cached stuff to free memory
     */
    protected function clear_memory() {

        parent::clear_memory();
        $this->varsdefined = false;
        $this->includes = null;
        $this->requires = null;
        $this->namespace = null;
        $this->extendedclasses = null;

    }

    /**
     * This will fill in all our protected variables in one go.
     */
    private function set_protected_vars() {
        if (!$this->varsdefined) {
            $this->get_tokens();
            for ($tid = 0; $tid < $this->tokenscount; $tid++) {
                switch ($this->tokens[$tid][0]) {
                    case T_REQUIRE:
                    case T_REQUIRE_ONCE:
                    case T_INCLUDE:
                    case T_INCLUDE_ONCE;
                        $starttid = $tid;
                        $require = new stdClass();
                        $require->tid = $tid;
                        $require->pathmodifier = '';
                        for ($tid++; $tid < $this->tokenscount; $tid++) {
                            if ($this->is_whitespace_token($tid)) {
                                continue;
                            }
                            if (in_array($this->tokens[$tid][1], array(')', '('))) {
                                continue;
                            }
                            if ($this->tokens[$tid][0] == T_CONSTANT_ENCAPSED_STRING) {
                                $require->name = $this->tokens[$tid][1];
                                break;
                            } else {
                                $require->pathmodifier .= $this->tokens[$tid][1];
                            }
                        }

                        if (in_array($this->tokens[$starttid][0], array(T_REQUIRE, T_REQUIRE_ONCE))) {
                            if (is_array($this->requires)) {
                                $this->requires[] = $require;
                            } else {
                                $this->requires = array($require);
                            }
                        } else {
                            if (is_array($this->includes)) {
                                $this->includes[] = $require;
                            } else {
                                $this->includes = array($require);
                            }
                        }
                        break;
                    case T_NAMESPACE:
                        $this->namespace = new stdClass();
                        $this->namespace->tid = $this->next_nonspace_token($tid, true);
                        $this->namespace->name = $this->tokens[$tid][1];
                        for ($tid++; $tid < $this->tokenscount; $tid++) {
                            if ($this->tokens[$tid][0] == -1) { // ;
                                break;
                            } else {
                                $this->namespace->name .= $this->tokens[$tid][1];
                            }
                        }
                        break;
                    case T_EXTENDS:
                        $classes = $this->get_classes();
                        foreach ($classes as $class) {
                            if ($this->next_nonspace_token($class->tid) == '{') {
                                // no class extension.
                                continue;
                            }
                            // Now loop from here and find an extends or an end of statement.
                            for ($i = ($class->tid + 1); $i < $this->tokenscount; $i++) {
                                if ($this->tokens[$i][1] == '{') {
                                    break;  // We are done - might have been an implements.
                                }
                                if ($this->tokens[$i][0] == T_EXTENDS) {
                                    $extends = new stdClass();
                                    $extends->tid = $i;
                                    $extends->name = '';
                                    for ($i++; $i < $this->tokenscount; $i++) {
                                        if ($this->is_whitespace_token($i)) {
                                            continue;
                                        }
                                        if ($this->tokens[$i][0] == -1) { // ;
                                            break;
                                        } else {
                                            $extends->name .= $this->tokens[$i][1];
                                        }
                                    }
                                    if (!is_array($this->extendedclasses)) {
                                        $this->extendedclasses= array();
                                    }
                                    $this->extendedclasses[$class->name] = $extends;
                                }
                            }
                        }
                        break;
                }
            }
        }
    }

    public function &get_requires() {
        $this->set_protected_vars();
        return $this->requires;
    }

    public function &get_includes() {
        $this->set_protected_vars();
        return $this->includes;
    }

    public function &get_extendedclasses() {
        $this->set_protected_vars();
        return $this->extendedclasses;
    }

    public function &get_namespace() {
        $this->set_protected_vars();
        return $this->namespace;
    }

}