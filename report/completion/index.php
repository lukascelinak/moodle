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
 * Course completion progress report
 *
 * @package    report
 * @subpackage completion
 * @copyright  2009 Catalyst IT Ltd
 * @author     Aaron Barnes <aaronb@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
require_once("{$CFG->libdir}/completionlib.php");
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/notes/lib.php');
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->libdir . '/filelib.php');

use core_table\local\filter\filter;
use core_table\local\filter\integer_filter;
use core_table\local\filter\string_filter;

/**
 * Configuration
 */
define('COMPLETION_REPORT_PAGE', 25);
define('COMPLETION_REPORT_COL_TITLES', true);

/*
 * Setup page, check permissions
 */

// Get course
$courseid = required_param('course', PARAM_INT);
$format = optional_param('format', '', PARAM_ALPHA);
$sort = optional_param('sort', '', PARAM_ALPHA);
$edituser = optional_param('edituser', 0, PARAM_INT);
$perpage = optional_param('perpage', 20, PARAM_INT); // How many per page.
$page = optional_param('page', 0, PARAM_INT); // Which page to show.
$contextid = optional_param('contextid', 0, PARAM_INT); // One of this or.
$newcourse = optional_param('newcourse', false, PARAM_BOOL);
$roleid = optional_param('roleid', 0, PARAM_INT);
$urlgroupid = optional_param('group', 0, PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($course->id);

$PAGE->set_url('/report/completion/index.php', array(
    'page' => $page,
    'perpage' => $perpage,
    'contextid' => $contextid,
    'id' => $courseid,
    'newcourse' => $newcourse,
        'course' => $courseid));

$url = new moodle_url('/report/completion/index.php', array('course'=>$course->id));
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');

// Not needed anymore.
unset($contextid);
unset($courseid);

require_login($course);
require_capability('report/completion:view', $context);

course_require_view_participants($context);
user_list_view($course, $context);

// Get group mode
$group = groups_get_course_group($course, true); // Supposed to verify group
if ($group === 0 && $course->groupmode == SEPARATEGROUPS) {
    require_capability('moodle/site:accessallgroups',$context);
}

// Retrieve course_module data for all modules in the course
$modinfo = get_fast_modinfo($course);

// Get criteria for course
$completion = new completion_info($course);

if (!$completion->has_criteria()) {
    print_error('nocriteriaset', 'completion', $CFG->wwwroot.'/course/report.php?id='.$course->id);
}

$strreports = get_string("reports");
$strcompletion = get_string('pluginname','report_completion');

$PAGE->set_title($strcompletion);
$PAGE->set_heading($course->fullname);

$filterset = new \report_completion\table\completion_table_filterset();
$filterset->add_filter(new integer_filter('courseid', filter::JOINTYPE_DEFAULT, [(int) $course->id]));

$participanttable = new \report_completion\table\completion_table("user-index-participants-{$course->id}",$course);
$participanttable->is_downloading($download, 'test', 'testing123');

if (!$participanttable->is_downloading()) {
echo $OUTPUT->header();
echo $OUTPUT->heading($strcompletion);
 $PAGE->requires->js_call_amd('report_progress/completion_override', 'init', [fullname($USER)]);
}

$canaccessallgroups = has_capability('moodle/site:accessallgroups', $context);
$filtergroupids = $urlgroupid ? [$urlgroupid] : [];

// Force group filtering if user should only see a subset of groups' users.
if ($course->groupmode != NOGROUPS && !$canaccessallgroups) {
    if ($filtergroupids) {
        $filtergroupids = array_intersect(
                $filtergroupids,
                array_keys(groups_get_all_groups($course->id, $USER->id))
        );
    } else {
        $filtergroupids = array_keys(groups_get_all_groups($course->id, $USER->id));
    }

    if (empty($filtergroupids)) {
        if ($course->groupmode == SEPARATEGROUPS) {
            // The user is not in a group so show message and exit.
            echo $OUTPUT->notification(get_string('notingroup'));
            echo $OUTPUT->footer();
            exit();
        } else {
            $filtergroupids = [(int) groups_get_course_group($course, true)];
        }
    }
}

// Apply groups filter if included in URL or forced due to lack of capabilities.
if (!empty($filtergroupids)) {
    $filterset->add_filter(new integer_filter('groups', filter::JOINTYPE_DEFAULT, $filtergroupids));
}

// Display single group information if requested in the URL.
if ($urlgroupid > 0 && ($course->groupmode != SEPARATEGROUPS || $canaccessallgroups)) {
    $grouprenderer = $PAGE->get_renderer('core_group');
    $groupdetailpage = new \core_group\output\group_details($urlgroupid);
    if (!$participanttable->is_downloading()) {
    echo $grouprenderer->group_details($groupdetailpage);}
}

// Filter by role if passed via URL (used on profile page).
if ($roleid) {
    $viewableroles = get_profile_roles($context);

    // Apply filter if the user can view this role.
    if (array_key_exists($roleid, $viewableroles)) {
        $filterset->add_filter(new integer_filter('roles', filter::JOINTYPE_DEFAULT, [$roleid]));
    }
}

$userrenderer = $PAGE->get_renderer('core_user');
if (!$participanttable->is_downloading()) {
echo $userrenderer->participants_filter($context, $participanttable->uniqueid);}

ob_start();
$participanttable->set_filterset($filterset);
$participanttable->out($perpage, true);
$participanttablehtml = ob_get_contents();
ob_end_clean();

if (!$participanttable->is_downloading()) {
echo $participanttablehtml;}
/*
$csvurl = new moodle_url('/report/completion/index.php', array('course' => $course->id, 'format' => 'csv'));
$excelurl = new moodle_url('/report/completion/index.php', array('course' => $course->id, 'format' => 'excelcsv'));*/
if (!$participanttable->is_downloading()) {/*
print '<ul class="progress-actions"><li><a href="index.php?course=' . $course->id .
        '&amp;format=csv">' . get_string('csvdownload', 'completion') . '</a></li>
    <li><a href="index.php?course=' . $course->id . '&amp;format=excelcsv">' .
        get_string('excelcsvdownload', 'completion') . '</a></li></ul>';
*/
echo $OUTPUT->footer($course);
}
// Trigger a report viewed event.
$event = \report_completion\event\report_viewed::create(array('context' => $context));
$event->trigger();
