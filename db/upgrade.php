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
 * @package    block_edusupport
 * @copyright  2019 Digital Education Society (http://www.dibig.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

function xmldb_block_edusupport_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    /// Add a new column newcol to the mdl_myqtype_options
    if ($oldversion < 2019011000) {

        // Define table block_edusupport to be created.
        $table = new xmldb_table('block_edusupport');

        // Adding fields to table block_edusupport.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('forumid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table block_edusupport.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for block_edusupport.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }


      // Define table block_edusupport_issues to be created.
      $table = new xmldb_table('block_edusupport_issues');

      // Adding fields to table block_edusupport_issues.
      $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
      $table->add_field('courseid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
      $table->add_field('discussionid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
      $table->add_field('currentlevel', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '1');
      $table->add_field('currentsupporter', XMLDB_TYPE_INTEGER, '20', null, null, null, null);
      $table->add_field('opened', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');

      // Adding keys to table block_edusupport_issues.
      $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

      // Conditionally launch create table for block_edusupport_issues.
      if (!$dbman->table_exists($table)) {
          $dbman->create_table($table);
      }

      // Define table block_edusupport_supportlog to be created.
      $table = new xmldb_table('block_edusupport_supportlog');

      // Adding fields to table block_edusupport_supportlog.
      $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
      $table->add_field('issueid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
      $table->add_field('userid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
      $table->add_field('supportlevel', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, null);
      $table->add_field('created', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);

      // Adding keys to table block_edusupport_supportlog.
      $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

      // Conditionally launch create table for block_edusupport_supportlog.
      if (!$dbman->table_exists($table)) {
          $dbman->create_table($table);
      }

      // Define table block_edusupport_supporters to be created.
      $table = new xmldb_table('block_edusupport_supporters');

      // Adding fields to table block_edusupport_supporters.
      $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
      $table->add_field('courseid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
      $table->add_field('userid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
      $table->add_field('supportlevel', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);

      // Adding keys to table block_edusupport_supporters.
      $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

      // Conditionally launch create table for block_edusupport_supporters.
      if (!$dbman->table_exists($table)) {
          $dbman->create_table($table);
      }

      // Edusupport savepoint reached.
      upgrade_block_savepoint(true, 2019011000, 'edusupport');
    }

    if ($oldversion < 2019093000) {
        // Define field archiveid to be added to block_edusupport.
        $table = new xmldb_table('block_edusupport');
        $field = new xmldb_field('archiveid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, '0', 'forumid');

        // Conditionally launch add field archiveid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Edusupport savepoint reached.
        upgrade_block_savepoint(true, 2019093000, 'edusupport');
    }


    return true;
}
