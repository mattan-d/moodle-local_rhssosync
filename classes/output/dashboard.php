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

namespace local_rhssosync\output;

use local_rhssosync\local\config;
use renderable;
use templatable;
use renderer_base;
use moodle_url;

/**
 * Dashboard renderable for local_rhssosync.
 *
 * @package    local_rhssosync
 * @copyright  2026 local_rhssosync contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard implements renderable, templatable {
    /** @var config */
    protected $config;

    /** @var \stdClass|false */
    protected $lastrun;

    /** @var int */
    protected $managedcount;

    /** @var int */
    protected $conflictcount;

    /**
     * Constructor.
     *
     * @param config $config
     * @param \stdClass|false $lastrun
     * @param int $managedcount
     * @param int $conflictcount
     */
    public function __construct(config $config, $lastrun, $managedcount, $conflictcount) {
        $this->config = $config;
        $this->lastrun = $lastrun;
        $this->managedcount = (int) $managedcount;
        $this->conflictcount = (int) $conflictcount;
    }

    /**
     * Export for the dashboard template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        $base = new moodle_url('/local/rhssosync/index.php');

        $data = [
            'sesskey'        => sesskey(),
            'actionurl'      => $base->out(false),
            'configured'     => $this->config->is_connection_configured(),
            'groupselected'  => $this->config->is_group_selected(),
            'grouppath'      => $this->config->get_group_path(),
            'managedcount'   => $this->managedcount,
            'conflictcount'  => $this->conflictcount,
            'hasconflicts'   => $this->conflictcount > 0,
            'urls'           => [
                'preview'        => (new moodle_url('/local/rhssosync/preview.php'))->out(false),
                'groupselection' => self::section_url('local_rhssosync_groupselection'),
                'connection'     => self::section_url('local_rhssosync_connection'),
                'syncsettings'   => self::section_url('local_rhssosync_syncsettings'),
                'managed'        => self::section_url('local_rhssosync_managed'),
                'conflicts'      => self::section_url('local_rhssosync_conflicts'),
                'history'        => self::section_url('local_rhssosync_history'),
            ],
        ];

        if ($this->lastrun) {
            $run = $this->lastrun;
            $data['lastrun'] = [
                'exists'      => true,
                'status'      => $run->status,
                'statusbadge' => $this->status_badge_class($run->status),
                'needsreview' => (int) $run->needsreview === 1,
                'dryrun'      => (int) $run->dryrun === 1,
                'time'        => userdate($run->timestart),
                'summary'     => $run->summary,
                'received'    => $run->receivedcount,
                'created'     => $run->createdcount,
                'updated'     => $run->updatedcount,
                'suspended'   => $run->suspendedcount,
                'reactivated' => $run->reactivatedcount,
                'skipped'     => $run->skippedcount,
                'unchanged'   => $run->unchangedcount,
                'conflicts'   => $run->conflictcount,
                'errors'      => $run->errorcount,
            ];
        } else {
            $data['lastrun'] = ['exists' => false];
        }

        return $data;
    }

    /**
     * Build the URL for an admin settings section.
     *
     * @param string $section
     * @return string
     */
    protected static function section_url($section) {
        return (new moodle_url('/admin/settings.php', ['section' => $section]))->out(false);
    }

    /**
     * Bootstrap badge class for a run status.
     *
     * @param string $status
     * @return string
     */
    protected function status_badge_class($status) {
        switch ($status) {
            case 'success':
                return 'badge-success';
            case 'partial':
                return 'badge-warning';
            case 'aborted':
                return 'badge-secondary';
            case 'failed':
                return 'badge-danger';
            default:
                return 'badge-info';
        }
    }
}
