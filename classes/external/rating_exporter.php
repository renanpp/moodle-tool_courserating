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

namespace tool_courserating\external;

use core\external\persistent_exporter;
use core_user\external\user_summary_exporter;
use tool_courserating\api;
use tool_courserating\local\models\flag;
use tool_courserating\local\models\rating;
use tool_courserating\output\renderer;
use tool_dataprivacy\category;
use tool_dataprivacy\context_instance;

/**
 * Class for exporting field data.
 *
 * @package    tool_courserating
 * @copyright  2022 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rating_exporter extends persistent_exporter {

    /**
     * Defines the persistent class.
     *
     * @return string
     */
    protected static function define_class() {
        return rating::class;
    }

    /**
     * Returns a list of objects that are related.
     *
     * @return array
     */
    protected static function define_related() {
        return array(
            'context' => 'context?',
        );
    }

    /**
     * Get the formatting parameters for the review field.
     *
     * @return array
     */
    protected function get_format_parameters_for_review() {
        return [
            'component' => 'tool_courserating',
            'filearea' => 'review',
            'itemid' => $this->data->id
        ];
    }

    /**
     * Return a list of additional properties used only for display
     *
     * @return array
     */
    protected static function define_other_properties(): array {
        return [
            'user' => ['type' => user_summary_exporter::read_properties_definition(), 'optional' => true],
            'reviewstars' => ['type' => PARAM_RAW],
            'reviewdate' => ['type' => PARAM_RAW],
            'reviewflag' => [
                'type' => 'array',
                'optional' => true,
            ],
            'ratingid' => ['type' => PARAM_INT],
        ];
    }

    /**
     * Get additional values to inject while exporting
     *
     * @param \renderer_base $output
     * @return array
     */
    protected function get_other_values(\renderer_base $output): array {
        global $PAGE, $USER;
        $result = ['ratingid' => $this->data->id];

        if ($user = \core_user::get_user($this->data->userid)) {
            $userexporter = new user_summary_exporter($user);
            $result['user'] = $userexporter->export($output);
        } else {
            $result['user'] = [];
        }

        /** @var renderer $renderer */
        $renderer = ($output instanceof renderer) ? $output : $PAGE->get_renderer('tool_courserating');
        $result['reviewstars'] = $renderer->stars($this->data->rating);

        $result['reviewdate'] = userdate($this->data->timecreated, get_string('strftimedatetimeshort', 'core_langconfig'));
        //$result['contextid'] = \context_course::instance($this->data->courseid)->id;

        // TODO premissions to see flagged, candelete
        $flagged = flag::get_records(['ratingid' => $this->data->id, 'userid' => $USER->id]) ? true : false;
        $flags = flag::count_records(['ratingid' => $this->data->id]);

        $result['reviewflag'] = (object)[
            'flagged' => $flagged,
            'toggleflag' => api::get_flag_inplace_editable($this->data->id, $flagged)->export_for_template($output),
            'flags' => $flags,
            'candelete' => $flags > 0,
            'ratingid' => $this->data->id,
        ];

        return $result;
    }
}
