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
 * Block bacssummary is defined here.
 *
 * @package     block_bacssummary
 * @copyright   2025 Gomozov Maxim
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_bacssummary extends block_base {

    /**
     * Initializes class member variables.
     */
    public function init() {
        $this->title = 'Статистика BACS';
    }

    /**
     * Returns statistics about BACS contest attempts.
     *
     * @return array Information about contests and tasks.
     */
    public function get_statistics() {
        global $USER, $DB;

        $stats = [];

        $contest_cache = [];
        $task_cache = [];

        $submits = $DB->get_records('bacs_submits', ['user_id' => $USER->id]);

        foreach ($submits as $submit) {
            $contest_id = $submit->contest_id;
            $task_id = $submit->task_id;

            if (!isset($contest_cache[$contest_id])) {
                $contest = $DB->get_record('bacs', ['id' => $contest_id]);
                if (!$contest) continue;
                $contest_cache[$contest_id] = $contest;
            }
            $contest = $contest_cache[$contest_id];

            if (!isset($task_cache[$task_id])) {
                $task = $DB->get_record('bacs_tasks', ['task_id' => $task_id]);
                if (!$task) continue;
                $task_cache[$task_id] = $task;
            }
            $task = $task_cache[$task_id];

            if (!isset($stats[$contest_id])) {
                $stats[$contest_id] = [
                    'contest_id' => $contest_id,
                    'contest_name' => $contest->name,
                    'total_tasks' => 0,
                    'solved_tasks' => 0,
                    'tasks' => []
                ];
            }

            $stats[$contest_id]['total_tasks']++;
            if ($submit->result_id == 13) {
                $stats[$contest_id]['solved_tasks']++;
            }

            $stats[$contest_id]['tasks'][$task_id] = [
                'task_id' => $task_id,
                'task_name' => $task->name,
                'solved' => ($submit->result_id == 13)
            ];
        }

        return $stats;
    }

    /**
     * Returns the block contents.
     *
     * @return stdClass The block contents.
     */
    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';


        $statistics = $this->get_statistics();

        $css = "
            <style>
                .block-bacssummary {
                    font-family: Arial, sans-serif;
                    font-size: 14px;
                }
                .contest-header {
                    font-weight: bold;
                    margin: 15px 0 10px;
                    padding-bottom: 5px;
                    border-bottom: 1px solid #eee;
                }
                .contest-title {
                    font-size: 16px;
                    color: #2c3e50;
                }
                .task-list {
                    margin-left: 20px;
                    list-style-type: none;
                    padding-left: 15px;
                }
                .task-list li {
                    position: relative;
                    padding-left: 25px;
                    margin: 5px 0;
                }
                .task-list li::before {
                    content: '•';
                    position: absolute;
                    left: 0;
                    color: #27ae60;
                }
                .task-solved::before {
                    content: '✓';
                    color: #27ae60;
                    font-weight: bold;
                }
                .task-failed::before {
                    content: '✗';
                    color: #c0392b;
                    font-weight: bold;
                }
                .contest-summary {
                    margin: 10px 0;
                    padding: 10px;
                    background-color: #f9f9f9;
                    border-radius: 4px;
                }
                .percent {
                    font-weight: bold;
                    padding: 2px 5px;
                    border-radius: 3px;
                    margin-left: 10px;
                }
                .percent-low {background: #ffe6e6; color: #c0392b; }
                .percent-medium {background: #fff3e0; color: #f39c12; }
                .percent-high {background: #e6f5e6; color: #27ae60; }
            </style>
        ";

        if (!empty($this->config->text)) {
            $this->content->text = $this->config->text;
        } else {
            $text = $css . '<div class="block-bacssummary">';

            if (empty($statistics)) {
                $text .= '<p>Нет данных о пройденных контестах</p>';
            } else {
                $text .= '<h3>Статистика контестов</h3>';

                foreach ($statistics as $contest) {
                    $total = $contest['total_tasks'];
                    $solved = $contest['solved_tasks'];
                    $percent = $total > 0 ? round(($solved / $total) * 100, 1) : 0;

                    $percentClass = 'percent-low';
                    if ($percent >= 80) {
                        $percentClass = 'percent-high';
                    } elseif ($percent >= 50) {
                        $percentClass = 'percent-medium';
                    }

                    $text .= '<div class="contest-section">';

                    $text .= '<div class="contest-header">';
                    $text .= '<div class="contest-title">Контест №' . $contest['contest_id'] . ': ' . $contest['contest_name'] . '</div>';
                    $text .= '</div>';

                    $text .= '<div class="contest-summary">';
                    $text .= "Решено задач: {$solved}/{$total} (<span class=\"percent {$percentClass}\">{$percent}%</span>)";
                    $text .= '</div>';

                    $text .= '<ul class="task-list">';
                    foreach ($contest['tasks'] as $task) {
                        $taskText = $task['solved']
                            ? "Задача \"{$task['task_name']}\" — решена"
                            : "Задача \"{$task['task_name']}\" — не решена";
                        $text .= '<li class="' . ($task['solved'] ? 'task-solved' : 'task-failed') . '">' . $taskText . '</li>';
                    }
                    $text .= '</ul>';

                    $text .= '</div>';
                }
            }

            $text .= '</div>';
            $this->content->text = $text;
        }

        return $this->content;
    }

    /**
     * Defines configuration data.
     */
    public function specialization() {
        if (empty($this->config->title)) {
            $this->title = 'Статистика BACS';
        } else {
            $this->title = $this->config->title;
        }
    }

    /**
     * Sets the applicable formats for the block.
     */
    public function applicable_formats() {
        return ['my' => true];
    }
}