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
 * Contains the class used for the displaying the participants table.
 *
 * @package    core_user
 * @copyright  2017 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
declare(strict_types=1);

namespace report_completion\table;

use context;
use core_table\dynamic as dynamic_table;
use core_table\local\filter\filterset;
use core_user\output\status_field;
use core_user\table\participants_search;
use moodle_url;

defined('MOODLE_INTERNAL') || die;

global $CFG;

require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once("{$CFG->libdir}/completionlib.php");

/**
 * Class for the displaying the participants table.
 *
 * @package    core_user
 * @copyright  2017 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_table extends \table_sql implements dynamic_table {

    /**
     * @var int $courseid The course id
     */
    protected $courseid;

    /**
     * @var string[] The list of countries.
     */
    protected $countries;

    /**
     * @var \stdClass[] The list of groups with membership info for the course.
     */
    protected $groups;

    /**
     * @var string[] Extra fields to display.
     */
    protected $extrafields;

    /**
     * @var \stdClass $course The course details.
     */
    protected $course;

    /**
     * @var array $criteria The completion criteria.
     */
    protected $criteria;

    /**
     * @var array $criteria The completion criteria.
     */
    protected $completion;

    /**
     * @var \stdClass $modinfo The course modinfo.
     */
    protected $modinfo;

    /**
     * @var  context $context The course context.
     */
    protected $context;

    /**
     * @var \stdClass[] List of roles indexed by roleid.
     */
    protected $allroles;

    /**
     * @var \stdClass[] List of roles indexed by roleid.
     */
    protected $allroleassignments;

    /**
     * @var \stdClass[] Assignable roles in this course.
     */
    protected $assignableroles;

    /**
     * @var \stdClass[] Profile roles in this course.
     */
    protected $profileroles;

    /**
     * @var filterset Filterset describing which participants to include.
     */
    protected $filterset;

    /** @var \stdClass[] $viewableroles */
    private $viewableroles;

    /** @var moodle_url $baseurl The base URL for the report. */
    public $baseurl;

    /**
     * Sets up the table.
     *
     * @param int $courseid
     * @param int|false $currentgroup False if groups not used, int if groups used, 0 all groups, USERSWITHOUTGROUP for no group
     * @param int $accesssince The time the user last accessed the site
     * @param int $roleid The role we are including, 0 means all enrolled users
     * @param int $enrolid The applied filter for the user enrolment ID.
     * @param int $status The applied filter for the user's enrolment status.
     * @param string|array $search The search string(s)
     * @param bool $bulkoperations Is the user allowed to perform bulk operations?
     * @param bool $selectall Has the user selected all users on the page?
     */
    // public function __construct($uniqueid) {
    //       parent::__construct($uniqueid);
    //        $this->modinfo = get_fast_modinfo($this->course);
    //       $this->completion = new \completion_info($this->course);
    //     }

    /**
     * Render the participants table.
     *
     * @param int $pagesize Size of page for paginated displayed table.
     * @param bool $useinitialsbar Whether to use the initials bar which will only be used if there is a fullname column defined.
     * @param string $downloadhelpbutton
     */
    public function out($pagesize, $useinitialsbar, $downloadhelpbutton = '') {
        global $CFG, $DB, $USER, $OUTPUT, $PAGE;
        $this->modinfo = get_fast_modinfo($this->course);
        $this->completion = new \completion_info($this->course);
        $this->downloadable = true;
        $this->set_attribute('class', 'table-bordered');
        // Define the headers and columns.
        $headers = [];
        $columns = [];
        
      /*  $bulkoperations = has_capability('moodle/course:bulkmessaging', $this->context);
        if ($bulkoperations) {
            $mastercheckbox = new \core\output\checkbox_toggleall('participants-table', true, [
                'id' => 'select-all-participants',
                'name' => 'select-all-participants',
                'label' => get_string('selectall'),
                'labelclasses' => 'sr-only',
                'classes' => 'm-1',
                'checked' => false,
            ]);
            $headers[] = $OUTPUT->render($mastercheckbox);
            $columns[] = 'select';
        }*/

        $headers[] = get_string('fullname');
        $columns[] = 'fullname';

        $extrafields = get_extra_user_fields($this->context);
        foreach ($extrafields as $field) {
            $headers[] = get_user_field_name($field);
            $columns[] = $field;
        }

        // Get the list of fields we have to hide.
        $hiddenfields = array();
        if (!has_capability('moodle/course:viewhiddenuserfields', $this->context)) {
            $hiddenfields = array_flip(explode(',', $CFG->hiddenuserfields));
        }
        // Add column for groups if the user can view them.
        $canseegroups = !isset($hiddenfields['groups']);
        if ($canseegroups) {
            $headers[] = get_string('groups');
            $columns[] = 'groups';
        }
        if ($this->get_completion_criteria()) {
            foreach ($this->get_completion_criteria() as $criterion) {
                // Generate icon details
                $iconlink = '';
                $iconalt = ''; // Required
                $iconattributes = array('class' => 'icon');
                $name = "criterium" . $criterion->id;
                switch ($criterion->criteriatype) {
                    case COMPLETION_CRITERIA_TYPE_ACTIVITY:

                        // Display icon
                        $iconlink = $CFG->wwwroot . '/mod/' . $criterion->module . '/view.php?id=' . $criterion->moduleinstance;
                        $iconattributes['title'] = $this->modinfo->cms[$criterion->moduleinstance]->get_formatted_name();
                        $iconalt = get_string('modulename', $criterion->module);
                        break;

                    case COMPLETION_CRITERIA_TYPE_COURSE:
                        // Load course
                        $crs = $DB->get_record('course', array('id' => $criterion->courseinstance));

                        // Display icon
                        $iconlink = $CFG->wwwroot . '/course/view.php?id=' . $criterion->courseinstance;
                        $iconattributes['title'] = format_string($crs->fullname, true, array('context' => \context_course::instance($crs->id, MUST_EXIST)));
                        $iconalt = format_string($crs->shortname, true, array('context' => \context_course::instance($crs->id)));
                        break;

                    case COMPLETION_CRITERIA_TYPE_ROLE:
                        // Load role
                        $role = $DB->get_record('role', array('id' => $criterion->role));

                        // Display icon
                        $iconalt = $role->name;
                        break;
                }

                // Create icon alt if not supplied
                if (!$iconalt) {
                    $iconalt = $criterion->get_title();
                }
                if ($this->is_downloading()) {
                    $details = $criterion->get_title_detailed(true);
                } else {
                    $details = $criterion->get_title_detailed();
                }
                $icon = $iconlink ? '<a href="' . $iconlink . '" title="' . $iconattributes['title'] . '">' . $OUTPUT->render($criterion->get_icon($iconalt, $iconattributes)) . '</a>' : $OUTPUT->render($criterion->get_icon($iconalt, $iconattributes));

                $headers[] = $this->is_downloading() ? $details : "<div class=\"rotated-text-container\"><span class=\"rotated-text\">{$details}</span>{$icon}</div>";
                $columns[] = $name;
                if ($this->is_downloading()) {
                    $headers[] = $details . " " . get_string('date');
                    $columns[] = $name . "date";
                    $extrafields[] = $name . "date";
                }
                $extrafields[] = $name;
                $this->no_sorting($name);
            }
        }

        $headers[] = $this->is_downloading() ? get_string('coursecomplete', 'completion') : '<div class="rotated-text-container"><span class="rotated-text">' . get_string('coursecomplete', 'completion') . '</span>' . $OUTPUT->pix_icon('i/course', get_string('coursecomplete', 'completion')) . '</div>';
        $columns[] = "coursecomplete";
        $extrafields[] = "coursecomplete";
        $this->no_sorting('coursecomplete');

        if ($this->is_downloading()) {
            $headers[] = get_string('coursecomplete', 'completion') . " " . get_string('date');
            $columns[] = "coursecompletedate";
            $extrafields[] = "coursecompletedate";
        }

        $this->define_columns($columns);
        $this->define_headers($headers);

        // The name column is a header.
        $this->define_header_column('fullname');

        // Make this table sorted by last name by default.
        $this->sortable(true, 'lastname');

        $this->set_attribute('id', 'completion');
        if ($canseegroups) {
            $this->groups = groups_get_all_groups($this->courseid, 0, 0, 'g.*', true);
        }
        $this->countries = get_string_manager()->get_list_of_countries(true);
        $this->extrafields = $extrafields;
        parent::out($pagesize, $useinitialsbar, $downloadhelpbutton);
    }

    /*
        /**
     * Generate the select column.
     *
     * @param \stdClass $data
     * @return string
    
    public function col_select($data) {
        global $OUTPUT;

        $checkbox = new \core\output\checkbox_toggleall('participants-table', false, [
            'classes' => 'usercheckbox m-1',
            'id' => 'user' . $data->id,
            'name' => 'user' . $data->id,
            'checked' => false,
            'label' => get_string('selectitem', 'moodle', fullname($data)),
            'labelclasses' => 'accesshide',
        ]);

        return $OUTPUT->render($checkbox);
    }*/
    /**
     * Generate the fullname column.
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_fullname($data) {
        global $OUTPUT;
        if (completion_can_view_data($data->id, $this->course)) {
            $userurl = new moodle_url('/blocks/completionstatus/details.php', array('course' => $this->course->id, 'user' => $data->id));
        } else {
            $userurl = new moodle_url('/user/view.php', array('id' => $data->id, 'course' => $this->course));
        }
        if ($this->is_downloading()) {
            return fullname($data);
        } else {
            return '<a href="' . $userurl->out() . '">' . $OUTPUT->user_picture($data, array('size' => 35, 'courseid' => $this->course->id, 'includefullname' => true, 'link' => false)) . '</a>';
        }
        //return $OUTPUT->user_picture($data, array('size' => 35, 'courseid' => $this->course->id, 'includefullname' => true, 'link' => false));
    }

    /**
     * Generate the country column.
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_country($data) {
        if (!empty($this->countries[$data->country])) {
            return $this->countries[$data->country];
        }
        return '';
    }

    /**
     * Generate the groups column.
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_groups($data) {
        $displayvalue = get_string('groupsnone');
        $usergroups = [];
        foreach ($this->groups as $coursegroup) {
            if (isset($coursegroup->members[$data->id])) {
                $usergroups[] = $coursegroup->name;
            }
        }

        if (!empty($usergroups)) {
            $displayvalue = implode(', ', $usergroups);
        } else {
            $$displayvalue = get_string('groupsnone');
        }
        return $displayvalue;
    }

    /**
     * This function is used for the extra user fields.
     *
     * These are being dynamically added to the table so there are no functions 'col_<userfieldname>' as
     * the list has the potential to increase in the future and we don't want to have to remember to add
     * a new method to this class. We also don't want to pollute this class with unnecessary methods.
     *
     * @param string $colname The column name
     * @param \stdClass $data
     * @return string
     */
    public function other_cols($colname, $data) {
        // Do not process if it is not a part of the extra fields.
        if (!in_array($colname, $this->extrafields)) {
            return '';
        }

        return $data->{$colname};
    }

    /**
     * Query the database for results to display in the table.
     *
     * @param int $pagesize size of page for paginated displayed table.
     * @param bool $useinitialsbar do you want to use the initials bar.
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        global $OUTPUT;
        list($twhere, $tparams) = $this->get_sql_where();
        $psearch = new participants_search($this->course, $this->context, $this->filterset);

        $total = $psearch->get_total_participants_count($twhere, $tparams);

        if ($this->is_downloading()) {
            $this->pagesize($total, $total);
        } else {
            $this->pagesize($pagesize, $total);
        }
        $sort = $this->get_sql_sort();
        if ($sort) {
            $sort = 'ORDER BY ' . $sort;
        }

        $rawdata = $psearch->get_participants($twhere, $tparams, $sort, $this->get_page_start(), $this->get_page_size());

        $this->rawdata = [];
        foreach ($rawdata as $user) {
            // Progress for each course completion criteria
            foreach ($this->get_completion_criteria() as $criterion) {
                $criteria_completion = $this->completion->get_user_completion($user->id, $criterion);
                $is_complete = $criteria_completion->is_complete();
                $name = "criterium" . $criterion->id;
                // Handle activity completion differently
                if ($criterion->criteriatype == COMPLETION_CRITERIA_TYPE_ACTIVITY) {

                    // Load activity
                    $activity = $this->modinfo->cms[$criterion->moduleinstance];

                    $progress = $this->get_user_progress($user->id);
                    // Get progress information and state
                    if (array_key_exists($activity->id, $progress)) {
                        $state = $progress[$activity->id]->completionstate;
                    } else if ($is_complete) {
                        $state = COMPLETION_COMPLETE;
                    } else {
                        $state = COMPLETION_INCOMPLETE;
                    }
                    if ($is_complete) {
                        $date = userdate($criteria_completion->timecompleted, get_string('strftimedatetimeshort', 'langconfig'));
                    } else {
                        $date = '';
                    }
                    // Work out how it corresponds to an icon
                    switch ($state) {
                        case COMPLETION_INCOMPLETE : $completiontype = 'n';
                            break;
                        case COMPLETION_COMPLETE : $completiontype = 'y';
                            break;
                        case COMPLETION_COMPLETE_PASS : $completiontype = 'pass';
                            break;
                        case COMPLETION_COMPLETE_FAIL : $completiontype = 'fail';
                            break;
                    }

                    $auto = $activity->completion == COMPLETION_TRACKING_AUTOMATIC;
                    $completionicon = 'completion-' . ($auto ? 'auto' : 'manual') . '-' . $completiontype;

                    $describe = get_string('completion-' . $completiontype, 'completion');
                    $a = new \stdClass();
                    $a->state = $describe;
                    $a->date = $date;
                    $a->user = strip_tags(fullname($user));
                    $a->activity = strip_tags($activity->get_formatted_name());
                    $fulldescribe = get_string('progress-title', 'completion', $a);
                    $user->$name = $this->is_downloading() ? $describe : $OUTPUT->pix_icon("i/" . $completionicon, $fulldescribe);

                    if ($this->is_downloading()) {
                        $name = $name . "date";
                        $user->$name = $date;
                    }
                    continue;
                }

                // Handle all other criteria
                $completiontype = $is_complete ? 'y' : 'n';
                $completionicon = 'completion-auto-' . $completiontype;

                $describe = get_string('completion-' . $completiontype, 'completion');

                $a = new \stdClass();
                $a->state = $describe;

                if ($is_complete) {
                    $a->date = userdate($criteria_completion->timecompleted, get_string('strftimedatetimeshort', 'langconfig'));
                } else {
                    $a->date = '';
                }

                $a->user = fullname($user);
                $a->activity = strip_tags($criterion->get_title());
                $fulldescribe = get_string('progress-title', 'completion', $a);

                if ($this->allow_marking_criteria === $criterion->id) {
                    $describe = get_string('completion-' . $completiontype, 'completion');

                    $toggleurl = new moodle_url(
                            '/course/togglecompletion.php',
                            array(
                        'user' => $user->id,
                        'course' => $this->course->id,
                        'rolec' => $this->allow_marking_criteria,
                        'sesskey' => sesskey()
                            )
                    );

                    $user->$name = $this->is_downloading() ? $describe : "<a href=\"" . $toggleurl->out() . "\" title=\"" . s(get_string('clicktomarkusercomplete', 'report_completion')) . "\">" .
                            $OUTPUT->pix_icon('i/completion-manual-' . ($is_complete ? 'y' : 'n'), $describe) . "</a>";
                } else {
                    $user->$name = $this->is_downloading() ? $describe : $OUTPUT->pix_icon("i/" . $completionicon, $fulldescribe);
                }

                if ($this->is_downloading()) {
                    $name = $name . "date";
                    $user->$name = $a->date;
                }
            }

            // Load course completion
            $params = array(
                'userid' => $user->id,
                'course' => $this->course->id
            );

            $ccompletion = new \completion_completion($params);
            $completiontype = $ccompletion->is_complete() ? 'y' : 'n';

            $describe = get_string('completion-' . $completiontype, 'completion');

            $a = new \StdClass;

            if ($ccompletion->is_complete()) {
                $a->date = userdate($ccompletion->timecompleted, get_string('strftimedatetimeshort', 'langconfig'));
            } else {
                $a->date = '';
            }

            $a->state = $describe;
            $a->user = fullname($user);
            $a->activity = strip_tags(get_string('coursecomplete', 'completion'));
            $fulldescribe = get_string('progress-title', 'completion', $a);

            $user->coursecomplete = $this->is_downloading() ? $describe : $OUTPUT->pix_icon('i/completion-auto-' . $completiontype, $fulldescribe);

            if ($this->is_downloading()) {
                $user->coursecompletedate = $date;
            }

            $this->rawdata[$user->id] = $user;
        }
        $rawdata->close();
        if ($this->rawdata) {
            $this->allroleassignments = get_users_roles($this->context, array_keys($this->rawdata),
                    true, 'c.contextlevel DESC, r.sortorder ASC');
        } else {
            $this->allroleassignments = [];
        }
        // Set initial bars.
        if ($useinitialsbar) {
            $this->initialbars(true);
        }
    }

    /**
     * Override the table show_hide_link to not show for select column.
     *
     * @param string $column the column name, index into various names.
     * @param int $index numerical index of the column.
     * @return string HTML fragment.
     */
    protected function show_hide_link($column, $index) {
        return '';
    }

    /**
     * Set filters and build table structure.
     *
     * @param filterset $filterset The filterset object to get the filters from.
     */
    public function set_filterset(filterset $filterset): void {
        // Get the context.
        $this->courseid = $filterset->get_filter('courseid')->current();
        $this->course = get_course($this->courseid);
        $this->context = \context_course::instance($this->courseid, MUST_EXIST);

        // Process the filterset.
        parent::set_filterset($filterset);
    }

    /**
     * Guess the base url for the participants table.
     */
    public function guess_base_url(): void {
        $this->baseurl = new moodle_url('/report/completion/index.php', ['course' => $this->courseid]);
    }

    /**
     * Get the context of the current table.
     *
     * Note: This function should not be called until after the filterset has been provided.
     *
     * @return context
     */
    public function get_context(): context {
        return $this->context;
    }

    /**
     * Get the context of the current table.
     *
     * Note: This function should not be called until after the filterset has been provided.
     *
     * @return context
     */
    public function get_user_progress($userid) {
        global $DB;
        $sql = " SELECT cmc.* FROM {course_modules} cm
                    INNER JOIN {course_modules_completion} cmc ON cm.id=cmc.coursemoduleid
                    WHERE
                    cm.course={$this->courseid} AND cmc.userid ={$userid}";
        return $DB->get_records_sql($sql);
    }

    private function get_completion_criteria() {
        global $USER;
        // Get criteria and put in correct order
        $criteria = array();

        foreach ($this->completion->get_criteria(COMPLETION_CRITERIA_TYPE_COURSE) as $criterion) {
            $criteria[] = $criterion;
        }

        foreach ($this->completion->get_criteria(COMPLETION_CRITERIA_TYPE_ACTIVITY) as $criterion) {
            $criteria[] = $criterion;
        }

        foreach ($this->completion->get_criteria() as $criterion) {
            if (!in_array($criterion->criteriatype, array(
                        COMPLETION_CRITERIA_TYPE_COURSE, COMPLETION_CRITERIA_TYPE_ACTIVITY))) {
                $criteria[] = $criterion;
            }
        }

// Can logged in user mark users as complete?
// (if the logged in user has a role defined in the role criteria)
        $this->allow_marking = false;
        $this->allow_marking_criteria = null;

        // Get role criteria
        $rcriteria = $this->completion->get_criteria(COMPLETION_CRITERIA_TYPE_ROLE);

        if (!empty($rcriteria)) {

            foreach ($rcriteria as $rcriterion) {
                $users = get_role_users($rcriterion->role, $this->context, true);

                // If logged in user has this role, allow marking complete
                if ($users && in_array($USER->id, array_keys($users))) {
                    $this->allow_marking = true;
                    $this->allow_marking_criteria = $rcriterion->id;
                    break;
                }
            }
        }
        return $criteria;
    }

}
