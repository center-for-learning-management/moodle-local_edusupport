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
 * @package   block_edusupport
 * @copyright 2018 Digital Education Society (http://www.dibig.at)
 * @author    Robert Schrenk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Hilfe';
$string['edusupport:addinstance'] = 'eduSupport Block hinzufügen';
$string['edusupport:manage'] = 'eduSupport Manage Capability';
$string['edusupport:myaddinstance'] = 'eduSupport Block hinzufügen';

$string['archive'] = 'Archiv';
$string['assigned'] = 'Zugeordnet';
$string['autocreate_orggroup'] = 'Automatisch Gruppen für eduvidual-Organisationen anlegen';
$string['autocreate_usergroup'] = 'Automatisch private Gruppen für Nutzer/innen anlegen';
$string['be_more_accurate'] = 'Bitte beschreiben Sie das Problem genauer!';
$string['changes_saved_successfully'] = 'Änderungen erfolgreich gespeichert.';
$string['changes_saved_fail'] = 'Änderungen konnten nicht gespeichert werden.';
$string['contactphone'] = 'Telefon';
$string['contactphone_missing'] = 'Bitte geben Sie Ihre Telefonnummer für Rückfragen an!';
$string['courseconfig'] = 'Kurskonfiguration';
$string['create_issue'] = 'Problem melden';
$string['create_issue_error_title'] = 'Fehler';
$string['create_issue_error_description'] = 'Die Anfrage konnte nicht gespeichert werden!';
$string['create_issue_success_title'] = 'Erfolg';
$string['create_issue_success_description'] = 'Ihre Anfrage wurde gespeichert. Wir kümmern uns darum so rasch wie möglich!';
$string['create_issue_success_description_mail'] = 'Ihre Anfrage wurde per e-Mail gesendet. Wir kümmern uns darum so rasch wie möglich!';
$string['create_issue_success_goto'] = 'Anfrage öffnen';
$string['create_issue_success_close'] = 'Schließen';
$string['cron:reminder:title'] = 'eduSupport Erinnerung';
$string['cron:reminder:intro'] = 'Dies ist eine freundlicher Erinnerung an jene offenen Tickets, die Ihnen als eduSupporter zugeteilt wurden!';
$string['description'] = 'Beschreibung';
$string['description_missing'] = 'Bitte geben Sie eine detaillierte Beschreibung an!';
$string['goto_tutorials'] = 'Hilfe & Anleitungen';
$string['goto_targetforum'] = 'Supportforum';
$string['edusupport:manage'] = 'Verwalten';
$string['email_to_xyz'] = 'Sende e-Mail an {$a->email}';
$string['header'] = 'Hilfe in <i>{$a}</i> anfordern';
$string['issue'] = 'Ticket';
$string['issue_assign'] = 'Zuordnen';
$string['issue_assign_3rdlevel'] = '{$a->fromuserfullname} hat dieses Ticket an {$a->touserfullname} ({$a->tosupportlevel}) weitergeleitet.';
$string['issue_assign_nextlevel'] = 'Dieses Ticket dem 2nd Level Support zuweisen';
$string['issue_assign_nextlevel:error'] = 'Entschuldigung, das Ticket konnte nicht dem 2nd Level Support zugewiesen werden.';
$string['issue_assign_nextlevel:post'] = '{$a->userfullname} hat dieses Ticket dem nächsthöheren Supportlevel zugewiesen!';
$string['issue_assigned:subject'] = 'Supportanfrage zugeordnet';
$string['issue_assigned:text'] = 'Die Supportanfrage wurde <a href="{$a->wwwroot}/profile/view.php?{$a->id}">{$a->firstname} {$a->lastname}</a> zugeordnet!';
$string['issue_close'] = 'Anfrage schließen';
$string['issue_closed:subject'] = 'Anfrage wurde geschlossen';
$string['issue_closed:text'] = 'Dieses Ticket wurde von <a href="{$a->wwwroot}/profile/view.php?id={$a->fromuserid}">{$a->fromuserfullname}</a> geschlossen. Falls Sie weitere Unterstützung benötigen, fordern Sie bitte wieder den 2nd Level Support an!';
$string['issues'] = 'Anfragen';
$string['issues:openmine'] = '{$a} für mich';
$string['issues:opennosupporter'] = '{$a} nicht zugeordnet';
$string['issues:openall'] = '{$a} gesamt offen';
$string['missing_permission'] = 'Fehlende Erlaubnis!';
$string['missing_targetforum'] = 'Das Zielforum fehlt und muss konfiguriert werden!';
$string['missing_targetforum_exists'] = 'Das konfigurierte Zielforum existiert nicht. Die fehlerhafte Konfiguration muss behoben werden!';
$string['only_you'] = 'Nur Sie und unser Team';
$string['privacy:metadata'] = 'Dieses Plugin speichert keine personenbezogenen Daten, da die Informationen in einem Forum abgelegt werden.';
$string['relativeurlsupportarea'] = 'Relative URL zum Supportbereich';
$string['screenshot'] = 'Screenshot anhängen';
$string['screenshot:description'] = 'Ein Screenshot kann bei der Problembehebung helfen!';
$string['select_isselected'] = 'Derzeit ausgewählt';
$string['select_unavailable'] = 'Nicht verfügbar';
$string['subject'] = 'Betreff';
$string['subject_missing'] = 'Bitte geben Sie einen stichwortartigen Titel an, der das Problem beschreibt!';
$string['support_area'] = 'Hilfe & Anleitungen';
$string['supporters'] = 'Supportmitarbeiter/innen';
$string['supporters:choose'] = 'Supportmitarbeiter/innen wählen';
$string['supportforum:choose'] = 'Foren für eduSupport auswählen';
$string['supporters:description'] = 'Alle Nutzer/innen des Kurses, die zumindest als "non-editing Teacher" eingestuft sind, stehen als Supportmitarbeiter/innen zur Verfügung. Geben Sie ein beliebiges Supportlevel (wird immer alphabetisch sortiert) an, um jemanden als Mitarbeiter/in einzusetzen!';
$string['supportforum:disable'] = 'Als Supportforum deaktivieren';
$string['supportforum:enable'] = 'Als Supportforum aktivieren';
$string['supportlevel'] = 'Supportlevel';
$string['targetforum'] = 'Supportforum';
$string['targetforum:description'] = 'Bitte wählen Sie jenes Forum, welches im Kurs für Supportanfragen genutzt werden soll. In diesem Forum wird zwangszweise der Gruppenmodus aktiviert bevor die erste Supportanfrage erstellt wird. Das Plugin wird außerdem für jede/n Nutzer/in automatisch eine private Gruppe anlegen.';
$string['targetforum:core:description'] = 'Alle Nutzer/innen werden automatisch in das systemweite Supportforum eingeschrieben, sobald sie eine Supportanfrage erstellen. Außerdem besteht die Möglichkeit automatische Gruppen anzulegen, um die Supportanfragen voneinander zu trennen.';
$string['to_group'] = 'Sichtbar für';
$string['toggle'] = 'Umschalten';
$string['userid'] = 'UserID';
$string['your_issues'] = 'Ihre Anfragen';
