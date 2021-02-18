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
 * @package    local_edusupport
 * @copyright  2018 Digital Education Society (http://www.dibig.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_edusupport\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use context_user;

defined('MOODLE_INTERNAL') || die;

class provider implements
\core_privacy\local\metadata\provider,
\core_privacy\local\request\core_userlist_provider,
\core_privacy\local\request\plugin\provider 
{

	public static function get_metadata(collection $collection) : collection {

        // Table edusuport subscribers.
        $collection->add_database_table(
            'local_edusupport_subscr',
            [
            'id' => 'privacy:metadata:edusupport:fieldid',
            'issueid' => 'privacy:metadata:edusupport:issueid',
            'discussionid' => 'privacy:metadata:edusupport:discussionid',
            'userid' => 'privacy:metadata:edusupport:userid',
            ],
            'privacy:metadata:edusupport:subscr'
            );
        // Table edusuport supporters.
        $collection->add_database_table(
            'local_edusupport_supporters',
            [
            'id' => 'privacy:metadata:edusupport:fieldid',
            'courseid' => 'privacy:metadata:edusupport:courseid',
            'userid' => 'privacy:metadata:edusupport:userid',
            'supportlvl' => 'privacy:metadata:edusupport:supportlvl',
            ],
            'privacy:metadata:edusupport:supporters'
            );
        // Table edusuport issues.
        $collection->add_database_table(
            'local_edusupport_issues',
            [
            'id' => 'privacy:metadata:edusupport:fieldid',
            'discussionid' => 'privacy:metadata:edusupport:discussionid',
            'currentsupporter' => 'privacy:metadata:edusupport:currentsupporter',
            'opened' => 'privacy:metadata:edusupport:opened',
            ],
            'privacy:metadata:edusupport:issues'
            );

        return $collection;
    }



  /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid the userid.
     * @return contextlist the list of contexts containing user info for the user.
     */
  public static function get_contexts_for_userid(int $userid) : contextlist  {
    $contextlist = new contextlist();
    $params = [
    'contextlevel' => CONTEXT_USER,
    'userid'       => $userid
    ];   
    $sql = "SELECT ctx.id
    FROM {context} ctx
    JOIN {local_edusupport_subscr} esc ON ctx.instanceid = esc.userid AND ctx.contextlevel = :contextlevel
    WHERE esc.userid = :userid
    ";

    $contextlist->add_from_sql($sql, $params);

    $sql = "SELECT ctx.id
    FROM {context} ctx
    JOIN {local_edusupport_supporters} esc ON ctx.instanceid = esc.userid AND ctx.contextlevel = :contextlevel
    WHERE esc.userid = :userid
    ";
    $contextlist->add_from_sql($sql, $params);

    $sql = "SELECT ctx.id
    FROM {context} ctx
    JOIN {local_edusupport_issues} esc ON ctx.instanceid = esc.currentsupporter AND ctx.contextlevel = :contextlevel
    WHERE esc.currentsupporter = :userid
    ";  
    $contextlist->add_from_sql($sql, $params); 

    return $contextlist;
    }


    public static function get_users_in_context(userlist $userlist) {

        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_USER) {
            return;
        }

        $params = [
        'contextlevel' => CONTEXT_USER,
        'contextid'       => $context->id
        ];   
        $sql = "SELECT esc.userid
        FROM {context} ctx
        JOIN {local_edusupport_subscr} esc ON ctx.instanceid = esc.userid AND ctx.contextlevel = :contextlevel
        WHERE ctx.id = :contextid
        ";

        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT esc.userid
        FROM {context} ctx
        JOIN {local_edusupport_supporters} esc ON ctx.instanceid = esc.userid AND ctx.contextlevel = :contextlevel
        WHERE ctx.id = :contextid
        ";
        $params = [
        'contextlevel' => CONTEXT_USER,
        'userid'       => $userid
        ];   
        $userlist->add_from_sql($sql, $params);

        $sql = "SELECT esc.currentsupporter
        FROM {context} ctx
        JOIN {local_edusupport_issues} esc ON ctx.instanceid = esc.currentsupporter AND ctx.contextlevel = :contextlevel
        WHERE ctx.id = :contextid
        ";
        $params = [
        'contextlevel' => CONTEXT_USER,
        'userid'       => $userid
        ];   
        $userlist->add_from_sql($sql, $params);

        return $userlist;
    } 


     /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist $contextlist The approved contexts to export information for.
     */
     public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        if (!$contextlist->count()) {
            return;
        }
        $datasupport[] = null;
        $datasubscr[] = null;
        $dataissues[] = null;
        $user = $contextlist->get_user();
        $context = context_user::instance($user->id);
        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);
        $sql = "SELECT ctx.id as cmid, esc.*
        FROM {context} ctx
        JOIN {local_edusupport_subscr} esc ON ctx.instanceid = esc.userid AND ctx.contextlevel = :contextlevel
        WHERE esc.userid = :userid
        ";
        $rs = $DB->get_recordset_sql($sql, $contextparams + ['contextlevel' => CONTEXT_USER,
            'userid' => $user->id]);
        foreach ($rs as $row) {
            $datasubscr[] = (object)[
            'id' => $row->id,   
            'issueid' => $row->issueid,
            'discussionid' => $row->discussionid,
            'userid' => $row->userid
            ];          
        }
        writer::with_context($context)
        ->export_data([get_string('pluginname', 'local_edusupport'),get_string('issue:countassigned','local_edusupport')], (object)$datasubscr);    

        $sql = "SELECT ctx.id as cmid, esc.*
        FROM {context} ctx
        JOIN {local_edusupport_supporters} esc ON ctx.instanceid = esc.userid AND ctx.contextlevel = :contextlevel
        WHERE esc.userid = :userid
        ";
        $rs = $DB->get_recordset_sql($sql, $contextparams + ['contextlevel' => CONTEXT_USER,
            'userid' => $user->id]);
        foreach ($rs as $row) {
            $datasupport[] = (object)[
            'id' => $row->id,    
            'courseid' => $row->courseid,
            'userid' => $row->userid,    
            'supportlevel' => $row->supportlevel
            ];               

        }   
        writer::with_context($context)
        ->export_data([get_string('pluginname', 'local_edusupport'),get_string('supporters', 'local_edusupport')], (object)$datasupport);    
        $sql = "SELECT ctx.id as cmid, esc.*
        FROM {context} ctx
        JOIN {local_edusupport_issues} esc ON ctx.instanceid = esc.currentsupporter AND ctx.contextlevel = :contextlevel
        WHERE esc.currentsupporter = :userid
        ";
        $rs = $DB->get_recordset_sql($sql, $contextparams + ['contextlevel' => CONTEXT_USER,
            'userid' => $user->id]);
        foreach ($rs as $row) {
            $dataissues[] = (object)[
            'issueid' => $row->id,
            'discussionid' => $row->discussionid,
            'currentsupporter' => $row->currentsupporter, 
            'opened' => $row->opened
            ]; 
        }
        writer::with_context($context)
        ->export_data([get_string('pluginname', 'local_edusupport'),get_string('your_issues', 'local_edusupport')], (object)$dataissues);    
    }

     /**
     * Delete all user data for this context.
     *
     * @param  \context $context The context to delete data for.
     */
     public static function delete_data_for_all_users_in_context(\context $context) {
        if ($context->contextlevel != CONTEXT_USER) {
            return;
        }
        static::delete_user_data($context->instanceid);
    }
    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if ($context instanceof \context_user) {
            static::delete_user_data($context->instanceid);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        foreach ($contextlist as $context) {
            // Check what context we've been delivered.
            if ($context instanceof \context_user) {
              static::delete_user_data($context->instanceid);
              static::alter_currentsupporter($context->instanceid);
          }
      }   


  }

    /**
     * Delete data from $tablename with the IDs returned by $sql query.
     *
     * @param  string $sql    SQL query for getting the IDs of the uer enrolments entries to delete.
     * @param  array  $params SQL params for the query.
     */
    protected static function alter_currentsupporter(int $userid) {
        global $DB;

        $params = [
        'userid' => $userid,
        ];
        $sql = "UPDATE {local_edusupport_issues} 
        SET currentsupporter = '-1'
        WHERE currentsupporter = :userid";
        $DB->execute($sql, $params);
        
    }  

    /**
     * Delete data from $tablename with the IDs returned by $sql query.
     *
     * @param  string $sql    SQL query for getting the IDs of the uer enrolments entries to delete.
     * @param  array  $params SQL params for the query.
     */
    protected static function delete_user_data(int $userid) {
        global $DB;

        $DB->delete_records('local_edusupport_supporters', ['userid' => $userid]);
        $DB->delete_records('local_edusupport_subscr', ['userid' => $userid]);
        
    }       
}