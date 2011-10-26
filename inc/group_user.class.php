<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2011 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

// ----------------------------------------------------------------------
// Original Author of file: Julien Dombre
// Purpose of file:
// ----------------------------------------------------------------------

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/// Group_User class - Relation between Group and User
class Group_User extends CommonDBRelation{

   // From CommonDBRelation
   public $itemtype_1 = 'User';
   public $items_id_1 = 'users_id';

   public $itemtype_2 = 'Group';
   public $items_id_2 = 'groups_id';

   public $checks_only_for_itemtype1 = true;
   public $logs_only_for_itemtype1   = false;


   static function getUserGroups($users_id) {
      global $DB;

      $groups = array();
      $query = "SELECT `glpi_groups`.*,
                       `glpi_groups_users`.`id` AS IDD,
                       `glpi_groups_users`.`id`  as linkID,
                       `glpi_groups_users`.`is_dynamic` AS is_dynamic
                FROM `glpi_groups_users`
                LEFT JOIN `glpi_groups` ON (`glpi_groups`.`id` = `glpi_groups_users`.`groups_id`)
                WHERE `glpi_groups_users`.`users_id` = '$users_id'
                ORDER BY `glpi_groups`.`name`";

      foreach ($DB->request($query) as $data) {
         $groups[] = $data;
      }
      return $groups;
   }


   /**  Show groups of a user
    *
    * @param $user the user
   **/
   static function showForUser(User $user) {
      global $CFG_GLPI, $LANG;

      $ID = $user->fields['id'];
      if (!Session::haveRight("group","r") || !$user->can($ID,'r')) {
         return false;
      }

      $canedit = $user->can($ID,'w');

      $rand        = mt_rand();
      $nb_per_line = 3;
      if ($canedit) {
         $headerspan = $nb_per_line*2;
         echo "<form name='groupuser_form$rand' id='groupuser_form$rand' method='post'";
         echo " action='".Toolbox::getItemTypeFormURL('User')."'>";
      } else {
         $headerspan = $nb_per_line;
      }

      $groups = self::getUserGroups($ID);
      $used   = array();
      if (!empty($groups)) {
         foreach ($groups as $data) {
            $used[$data["id"]] = $data["id"];
         }
      }

      if ($canedit) {
         echo "<div class='firstbloc'>";

         echo "<table class='tab_cadre_fixe'>";
         echo "<tr class='tab_bg_1'><th colspan='2'>".$LANG['setup'][604]."</th></tr>";
         echo "<tr><td class='tab_bg_2 center'>";
         echo "<input type='hidden' name='users_id' value='$ID'>";

         // All entities "edited user" have access
         $strict_entities = Profile_User::getUserEntities($ID, true);

         // Keep only entities "connected user" have access
         foreach ($strict_entities as $key => $val) {
            if (!Session::haveAccessToEntity($val)) {
               unset($strict_entities[$key]);
            }
         }

         if (countElementsInTableForEntity("glpi_groups", $strict_entities) > count($used)) {
            Dropdown::show('Group', array('entity'    => $strict_entities,
                                          'used'      => $used,
                                          'condition' => '`is_usergroup`'));
            echo "</td><td class='tab_bg_2 center'>";
            echo "<input type='submit' name='addgroup' value=\"".$LANG['buttons'][8]."\"
                   class='submit'>";
         } else {
            echo $LANG['common'][49];
         }

         echo "</td></tr>";
         echo "</table></div>";
      }

      echo "<div class='spaced'>";
      echo "<table class='tab_cadre_fixehov'><tr>";
      echo "<th colspan='$headerspan'>".$LANG['Menu'][36]."&nbsp;(D=".$LANG['profiles'][29].")</th>";
      echo "</tr>";


      if (!empty($groups)) {
         Session::initNavigateListItems('Group', $user->getTypeName()." = ".$user->getName());
         $i = 0;
         foreach ($groups as $data) {
            Session::addToNavigateListItems('Group', $data["id"]);
            if ($i%$nb_per_line == 0) {
               if ($i != 0) {
                  echo "</tr>";
               }
               echo "<tr class='tab_bg_1'>";
            }

            if ($canedit) {
               echo "<td width='10'>";
               $sel = "";
               if (isset($_GET["select"]) && $_GET["select"]=="all") {
                  $sel = "checked";
               }
               echo "<input type='checkbox' name='item[".$data["linkID"]."]' value='1' $sel>";
               echo "</td>";
            }
            echo "<td><a href='".$CFG_GLPI["root_doc"]."/front/group.form.php?id=".$data["id"]."'>".
                  $data["name"].($_SESSION["glpiis_ids_visible"]?" (".$data["id"].")":"")."</a>";
            echo "&nbsp;";

            if ($data["is_dynamic"]) {
               echo "<strong>&nbsp;(D)</strong>";
            }
            echo "</td>";
            $i++;
         }

         while ($i%$nb_per_line != 0) {
            if ($canedit) {
               echo "<td>&nbsp;</td>";
            }
            echo "<td>&nbsp;</td>";
            $i++;
         }
         echo "</tr>";

      } else {
         echo "<tr class='tab_bg_1'>";
         echo "<td colspan='$headerspan' class='center'>".$LANG['common'][49]."</td></tr>";
      }
      echo "</table>";

      if ($canedit) {
         if (count($used)) {
            Html::openArrowMassives("groupuser_form$rand", true);
            Html::closeArrowMassives(array('deletegroup' => $LANG['buttons'][6]));
         }
         echo "</form>";
      }
      echo "</div>";
   }


   /**
    * Show users of a group
    *
    * @param $group the group
   **/
   static function showForGroup(Group $group) {
      global $DB, $LANG, $CFG_GLPI;

      $ID = $group->fields['id'];
      if (!Session::haveRight("user","r") || !$group->can($ID,'r')) {
         return false;
      }
      // Have right to manage members
      $canedit     = ($group->can($ID, 'r') && $group->canUpdate());
      $rand        = mt_rand();
      $nb_per_line = 3;


      $query = "SELECT `glpi_users`.*,
                       `glpi_groups_users`.`id` AS linkID,
                       `glpi_groups_users`.`is_dynamic` AS is_dynamic,
                       `glpi_groups_users`.`is_manager` AS is_manager,
                       `glpi_groups_users`.`is_userdelegate` AS is_userdelegate
                FROM `glpi_groups_users`
                LEFT JOIN `glpi_users` ON (`glpi_users`.`id` = `glpi_groups_users`.`users_id`)
                WHERE `glpi_groups_users`.`groups_id`='$ID'
                ORDER BY `glpi_users`.`name`,
                         `glpi_users`.`realname`,
                         `glpi_users`.`firstname`";

      $used   = array();
      $result = $DB->query($query);

      if ($DB->numrows($result)>0) {
         while ($data=$DB->fetch_array($result)) {
            $used[$data["id"]] = $data;
         }
      }
      $used_ids = array_keys($used);
      if ($canedit) {
         $headerspan = $nb_per_line*2;
         echo "<form name='groupuser_form$rand' id='groupuser_form$rand' method='post'
                action='".$CFG_GLPI['root_doc']."/front/group.form.php'>";
         echo "<input type='hidden' name='groups_id' value='$ID'>";

         if ($group->fields['is_recursive']) {
            $entityrestrict = getSonsOf('glpi_entities', $group->fields['entities_id']);

            // active entity could be a child of object entity
            if ($_SESSION['glpiactive_entity'] != $group->fields['entities_id']
                && in_array($_SESSION['glpiactive_entity'], $entityrestrict)) {
               $entityrestrict = getSonsOf('glpi_entities', $_SESSION['glpiactive_entity']);
            }
         } else {
            $entityrestrict = $group->fields['entities_id'];
         }
         $res = User::getSqlSearchResult (true, "all", $entityrestrict, 0, $used_ids);
         $nb  = ($res ? $DB->result($res,0,"CPT") : 0);

         if ($nb) {
            echo "<div class='firstbloc'>";
            echo "<table class='tab_cadre_fixe'>";
            echo "<tr class='tab_bg_1'><th colspan='6'>".$LANG['setup'][603]."</tr>";
            echo "<tr><td class='tab_bg_2 center'>";

            User::dropdown(array('right'  => "all",
                                 'entity' => $entityrestrict,
                                 'used'   => $used_ids));

            echo "</td><td>".$LANG['common'][64]."</td><td>";
            Dropdown::showYesNo("is_manager", 0);

            echo "</td><td>".$LANG['common'][123]."</td><td>";
            Dropdown::showYesNo("is_userdelegate", 0);

            echo "</td><td class='tab_bg_2 center'>";
            echo "<input type='hidden' name'is_dynamic' value='0'>";
            echo "<input type='submit' name='adduser' value=\"".$LANG['buttons'][8]."\"
                   class='submit'>";
            echo "</td></tr>";
            echo "</table></div>";
         }

      } else {
         $headerspan = $nb_per_line;
      }

      if (count($used)) {
         Session::initNavigateListItems('User', $group->getTypeName()." = ".$group->getName());
         $items = array(0 => "is_manager",
                        1 => "is_userdelegate",
                        2 => "is_user");

         foreach ($items as $key => $role) {
            echo "<div id='groupuser_form$rand-$role' class='spaced'>";
            echo "<table class='tab_cadre_fixe'>";
            $title = $LANG['Menu'][14]." (D=".$LANG['profiles'][29].")";
            if ($role == "is_manager") {
               $title = $LANG['common'][64];
            } else if ($role == "is_userdelegate") {
               $title = $LANG['common'][123];
            }
            echo "<tr><th colspan='$headerspan'>".$title."</th></tr>";

            $i    = 0;
            $user = new User();

            foreach ($used as $id => $data) {
               if ((isset($data[$role]) && !$data[$role])
                   || !$user->can($id, 'r')) {
                  // For recursive group, could be in another (sister) entity
                  continue;
               }
               Session::addToNavigateListItems('User', $data["id"]);
               if ($i%$nb_per_line==0) {
                  if ($i!=0) {
                     echo "</tr>";
                  }
                  echo "<tr class='tab_bg_1'>";
               }
               if ($canedit) {
                  echo "<td width='10'>";
                  $sel = "";
                  if (isset($_GET["select"]) && $_GET["select"]=="all") {
                     $sel = "checked";
                  }
                  echo "<input type='checkbox' name='item[".$data["linkID"]."]' value='1' $sel>";
                  echo "</td>";
               }

               echo "<td>";
               echo $user->getLink();
               if ($data["is_dynamic"]) {
                  echo "<strong>&nbsp;(D)</strong>";
               }

               echo "</td>";
               $i++;
            }
            while ($i%$nb_per_line!=0) {
               echo "<td>&nbsp;</td>";
               if ($canedit) {
                  echo "<td>&nbsp;</td>";
               }
               $i++;
            }
            echo "</tr>";
            echo "</table>";

            if ($canedit && $i) {
               Html::openArrowMassives("groupuser_form$rand-$role", true);
               $actions = array('deleteuser' => $LANG['buttons'][6]);
               if ($role == "is_manager") {
                  $actions['unset_manager'] = $LANG['users'][20];
               } else if ($role == "is_userdelegate") {
                  $actions['unset_delegate'] = $LANG['users'][25];
               } else {
                  $actions['set_manager'] = $LANG['users'][19];
                  $actions['set_delegate'] = $LANG['users'][24];
               }
               Html::closeArrowMassives($actions);
            }
            echo "</div>";
         }
      }
      if ($canedit) {
         echo "</form>";
      }
   }


   /**
    * Get search function for the class
    *
    * @return array of search option
   **/
   function getSearchOptions() {
      global $LANG;

      $tab = array();
      $tab['common'] = $LANG['common'][32];

      $tab[2]['table']         = $this->getTable();
      $tab[2]['field']         = 'id';
      $tab[2]['name']          = $LANG['common'][2];
      $tab[2]['massiveaction'] = false;

      $tab[3]['table']    = $this->getTable();
      $tab[3]['field']    = 'is_dynamic';
      $tab[3]['name']     = $LANG['profiles'][29];
      $tab[3]['datatype'] = 'bool';

      $tab[4]['table'] = 'glpi_groups';
      $tab[4]['field'] = 'name';
      $tab[4]['name']  = $LANG['common'][35];

      $tab[5]['table'] = 'glpi_users';
      $tab[5]['field'] = 'name';
      $tab[5]['name']  = $LANG['common'][34];

      return $tab;
   }


   static function deleteGroups($user_ID, $only_dynamic = false) {
      global $DB;

      $crit['users_id'] = $user_ID;
      if ($only_dynamic) {
         $crit['is_dynamic'] = '1';
      }
      $obj = new Group_User();
      $obj->deleteByCriteria($crit);
   }


   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
      global $LANG;

      if (!$withtemplate) {
         switch ($item->getType()) {
            case 'User' :
               if ($_SESSION['glpishow_count_on_tabs']) {
                  return self::createTabEntry(Group::getTypeName(2),
                                              countElementsInTable($this->getTable(),
                                                                   "users_id = '".$item->getID()."'"));
               }
               return Group::getTypeName(2);
         }
      }
      return '';
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {

      if ($item->getType()=='User') {
         self::showForUser($item);
      }
      return true;
   }

}

?>
