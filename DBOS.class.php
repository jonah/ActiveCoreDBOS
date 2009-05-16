<?php
/**
 * DataBase Object Styler
 *
 * @license LGPL
 * @version $Id: DBOS.class.php 5788 2009-05-12 21:04:41Z jonah $
 * @package ActiveCore
 * @subpackage DBOS
 * @author Jonah
 * @modified  4/9/08 5:21 PM by Jonah
 * @version 1.6 Jonah  2/5/09 10:00 AM - Consolidated with open source version.
 * @link http://activecore-wiki.activistinfo.org/index.php/ActiveCore_Coding_Standards
 */

/**
 * Bring in useful DB stuff from Sam's abstract DO class
 */

require_once(dirname(__FILE__)."/DBObjectAC.class.php");

/**
 *  DataBase Object Styler
 *
 *  Relationships for the data object are driven by data stored by phpmyadmin.
 *  You can define table relationships there, use
 *  the visualization tools, etc.  Then those relationships will be reflected in
 *  the forms and tables.
 *
 *  EX:
 *  class Example extends DBOS { function __construct("table",$id,"db","optional index field if not table_id"); }
 *  You can view pretty documentation at /core/includes/classes/DBOS/docs/index.html
 *  @example DBOS/docs/example.php
 *
 * A typical class extending this would look like:
 *
 * class Example extends DBOS {
 *    var $_db = "db";
 *    var $_table = "example";
 *    var $_idField = "example_id";
 * }
 *
 * @version 1.5 JB  10/6/08 2:03 PM
 * - show full tables, delete records, etc
 * - ajax grid-editing for tables
 *
 * @version 1.4g
 *  - better ajaxField implementation  6/19/08 10:16 AM
 *
 * @version 1.3
 *  - supports Enums, slightly better documentation
 * @version 1.2 GP
 *  - now extends DBObjectAC.  Experimental, I haven't tested using any inherited functions.  JB  2/26/08 9:46 AM
 *
 * @author Jonah  1/2/08
 * @package ActiveCore
 * @link http://activecore-wiki.activistinfo.org/
 * @abstract
 * See file abstract above.  Adds html and ajax capabilities to the DBObjectAC class.
 * Interitance:
 * ACTable->DBObjectAC->DBOS
 */

class DBOS extends DBObjectAC {
    protected static $_tableInfo = array();
    /*
    * The following four fields are depreciated.  Always use _ for data object related fields
    * so they don't conflict with db set fields.  JB  8/18/08 6:25 PM
    */
    public $explain = array();
    public $tbl = array(); // mysql info about the table and fields
    public $result_content;
    public $hidden_fields = array();
    public $_passThrough = array();
    /**
    * The admin file that will deal with ajax and CRUD for this table/record.
    */
    public $_controller = "/admin/people/admin_person.php";

    /*
    * @abstract load the data via the load function.
    * @return returns the object.
    */

    function __construct($table,$id=false,$db=false,$indexField=false) {
        if(is_array($indexField)) {
          $indexField = $indexField[indexField];
        }
        if(is_numeric($table)) {
          // DataObjectAC compatibility
          parent::__construct($table);
        } else {
          $this->load($table,$id,$db,$indexField);
        }
    }

    /**
     * Special case override function.  See $this->loadRecord.  JB  2/28/09 11:53 AM
     *    This is the heavy lifting.  Initialize the features of this data object
     *    with this "load" function.
     * This function is actually useful to tack on fields from 'extension' tables.  JB  3/16/09 7:16 PM
     * @modified  3/21/08 3:20 PM JB
     * @return True or false if the record was found
     * @param string $table database table
     * @version 1.1  6/19/08 10:18 AM
     */

    function load($table,$id,$db,$indexField=false) {
        $this->db = $db;
        if(!$this->db) $this->db = PERSON_DB;
        if(!$indexField) $indexField = $this->_indexField;

        $this->table = $table;
        $this->id = $id;

        // set some DBObjectAC vars
        $this->_db = $db;
        $this->_table = $table;

        if(!$this->tbl)   {
          $this->_loadTableInfo();
          $this->tbl = self::$_tableInfo[$this->table];
        }

        if(!$indexField) {
            // Just assume the first field is the index field.
            if(!is_array($this->tbl)) $this->loadTable();
            foreach($this->tbl as $field) {
                $this->_idField = $field['field'];
                break;
            }
            $this->indexField = $this->_idField;
        } else {
          $this->indexField = $indexField;
        }

        if(!$this->_idField) $this->_idField = $this->indexField;


        // Forms for editing are an array named after the table.
        $this->form_name = $this->_table;

        //  get the record from the database if the id is numeric.
        //  We only support numeric ids right now.
        if(is_numeric($id)) {
            $this->loadRecord($id);
            /*
            $sql = "SELECT * FROM $table WHERE $this->indexField = $id";
            $result=mysql_db_query($db,$sql) or mysql_die($sql); if($debug) { echo "<hr><pre> $sql </pre><hr>"; }
            $row=mysql_fetch_assoc($result);
            if($num=mysql_num_rows($result)) {
                foreach($row AS $field=>$value) {
                  $this->$field = $value;
                }
                $this->_loaded = true;
            }
            */
        } else {
          $this->defaults();
          return false;
        }
    } // end function load

   /**
    *  @function html
    *  @abstract
    *  This function is supposed to output what we know about this
    *  record in a pretty way.  The output is ugly right now.
    *  The point of this function is to be used as the "summary" view
    *  of the record.
    */

    public function html() {
      da($this);
    }

   /**
    *  @function set
    *  @returns Id of the record.
    *  @abstract
    *  Sets a single field.  Takes "field","value"
    */

    function set($field,$value=false) {
        if($value == false) {
          $value = $_REQUEST[$field];
        }
        $value = stripslashes($value);
        if(!empty($this->db)) {
            mysql_select_db($this->db);
        }
        // Backwards compatibility
        if($this->table && !$this->_table) {
            $this->_table = $this->table;
        }

        if($this->indexField && !$this->_idField) {
            $this->_idField = $this->indexField;
        }

        if(contains("phone",$field)) {
          $info = cleanPhone($value);
          if(is_numeric($info[numeric])) {
            $value = $info[numeric];
          }
        }

        $query = "UPDATE $this->_table
                    SET $field = '" . addslashes($value) . "'
                    WHERE $this->_idField = '" . $this->id . "'";
        mysql_query($query) or mysql_die($query);
        $this->$field = $value;
        return $this->id;
    }


   /** Display functions */

   /**
    * @function formfield
    * @returns html string
    * @abstract
    *  Stand-alone display of a single field.
    *  EX: $r->formField("field_name");
    *  Currently only supports enum, add in other types as you need them.  JB  3/20/08 11:23 AM
    *  Because this function is really just a switch for the various field type
    *  output functions, options aren't guaranteed to work universally.  Implement
    *  as needed.
    *  options:
    *    - raw - do not wrap in table html
    *    - bulk or datagrid: Display fields assuming there are multiple records
    *                        being displayed on the same page.
    *    - footer - add this html to the right of the field.
    *
    * @version 1.3 jonah  9/30/08 12:46 PM
    *  - fixed bug where options weren't being handed to text field types
    *  - added footer string to add to edit field display
    *
    * @version 1.2 jonah  9/30/08 12:40 PM
    *  - added datatime field support with popup calendar selection.
    *
    * @version 1.1  4/9/08 5:25 PM
    *  - now supports passing get vars in as defaults when creating new records.
    */

    function formField($name,$opts=false) {
        ob_start();
        $R =& $_REQUEST;
        $debug = false;
        if($opts=="explain") {
          show_errors("name,option - options are: raw, sql");
          die();
        }

        // if(!$this->tbl) $this->tbl = $this->_loadTableInfo();

        if($opts[bulk]) $this->dataGrid = true;

        if(is_array($name)) {
          $info =& $name;
        } else {
          $info = $this->_loadFieldInfo($name);
          if($debug) echo "<br> Found full field info for".da($info)." in formField.";
        }

        if($R[$info[field]]) $this->$info[field] = $R[$info[field]];

        switch($info[type]) {
             case"tinyint":
               if($info['size']==1) {
                 $this->formBoolean($info,$opts);
               } else {
                 $this->formText($info[field],$opts);
               }
               break;
             case"text":
               $this->formTextArea($info[field], $this->$info[field],$opts);
               break;
             case"enum":
               $this->formEnum($info,$opts);
               break;
             case"datetime":
             case"date":
               $this->formDate($info[field], $this->$info[field],$opts);
               break;
             default:
               if(is_array($info[rel])) {
                   // there is a table relationship from phpmyadmin data.
                   if($debug) echo "relation found for $name, call this->formSelect";
                   $this->formSelect($info,$opts);
               } else {
                   // we havn't found any other way to display, use default
                   $this->formText($info[field],$this->$info[field],$opts);
               }
         }
         $string = ob_get_clean();
         echo $string;
         return $string;
    }

   /**
    * @function _loadFieldInfo
    * @returns an array of the info we know about the field
    */

    function _loadFieldInfo($name) {
        // For backwards compatibility JB  8/14/08 2:16 PM
        if(!$this->_table) {
          if($this->table) $this->_table = $this->table;
        }

        if(!$this->_table) {
            show_errors("_loadFieldInfo could not load table info");
            return false;
        }

        if(!is_array($this->tbl[$name])) {
            $this->_loadTableInfo();
            $this->tbl = self::$_tableInfo[$this->_table];
        }
        $fieldInfo =& $this->tbl[$name];
        return $fieldInfo;
    }


   /**
    * @author Jonah
    * @version 0.2 Beta  8/12/08 12:38 PM
    * @version 0.1 - alpha Early 08
    * @returns nothing
    * @var options
    *
    * @abstract
    *  Works in conjunction with saveAjax and scripaculous InPlaceSelect
    *   3/25/08 3:08 PM
    * http://wiki.script.aculo.us/scriptaculous/show/InPlaceSelect
    *
    * This tries to be flexible and determine the right way to deal with
    * the field type.  It does not support all field types.
    */

    function ajaxField($name,$opts=false) {
        $debug = false;
        // Figure out some of the stuff we need to know about this field to display it.
        if(is_array($name)) {
            // @todo - _fieldInfo instead of info, always be explicit about field vs table.
            $this->_info = $name;
        } else {
            if(!$this->tbl) {
              $this->tbl = $this->_loadTableInfo();
            }
            $this->_info = $this->tbl[strtolower($name)];
        }

        if(!is_array($this->_info) && !$this->_gridEdit) {
            show_errors("The function ajaxField failed with field $name");
            da($this->tbl);
            return false;
        }

        if($debug) da($this->_info);

        $this->_opts = $opts;
        if($opts['table']) $this->fieldHeader($this->_info);

        switch($this->_info[type]) {
            case"enum":
                $this->ajaxFieldEnum($name,$opts);
                break;
            case"int":
            case"tinyint":
            case"smallint":
            case"mediumint":
               if($this->_info['size']==1) {
                 $this->ajaxBoolean($this->_info,$opts);
               } else {
                 if(is_array($this->_info[rel])) {
                    // This is a foreign key field, do the dropdown.
                    $this->ajaxFieldEnum($this->_info,$opts);
                 } else {
                    // "No relation found, do a simple text field.";
                    $this->ajaxText($this->_info,$opts);
                 }
               }
               break;
             case"time":
             case"date":
             case"datetime":
               // @todo, make these date fields use calendar. JB  8/19/08 12:02 PM
             case"decimal":
             case"char":
             case"varchar":
             case"text":
               $this->ajaxText($this->_info,$opts);
               break;
            default:
              $failed = true;
              if(!$this->_gridEdit) echo "Ajax field type {$this->_info[type]} not built.";

        }
        if($opts['table']) $this->fieldFooter($this->_info);
        if($failed) return false;
    }


   /**
    * In place editing of varchar or text fields.
    * @function ajaxText
    * @author Jonah  5/30/08 12:02 PM
    * @version 0.2 Beta JB  8/12/08 1:12 PM
    * @version 0.1 Alpha 5/30/08 12:02 PM
    */

    function ajaxText($info=false,$opts=false) {
        $debug = false;
        if(is_array($opts)) extract($opts);
        if(contains("phone",$info[field])) {
           $this->$info[field] = show_phone($this->$info[field],array("string"=>true));
        }

        if($info['type']=="text") $textarea = true;
        if($info['type']=="datetime") $this->$info[field] = hdatetime($this->$info[field]);

        if($textarea) $config = "rows:8,cols:40,";
        if($this->$info[field]!="") {
          $current_value = $this->$info[field];
        } else {
          if(is_numeric($this->id)) {
            $current_value = "Edit";
          } else {
            $current_value = "Add";
          }
          $current_value .= " $info[field]";

        }
        // Backward compatibility JB  10/7/08 4:19 PM
        if($this->indexField && !$this->_idField) {
            $this->_idField = $this->indexField;
        }


        if(!is_numeric($this->id) && is_numeric($this->person_id)) {
            $index = $this->person_id;
        } else {
            $index = $this->id;
        }
        if($label) $this->fieldHeader($info);

        // Display only 150 characters -- SJP 02/27/2009 01:12 PM
        if($textarea) {
            $value_length = strlen($current_value);
            if($value_length > 150) {
                $short_value = substr($current_value, 0, 150) . "...";
            }
            ?><span id="<?= $this->_table ?>_<?= $index ?>_<?= $info[field] ?>_long" style="display:none"><?= stripslashes($current_value) ?></span><?
        }
        ?><span id="<?= $this->_table ?>_<?= $index ?>_<?= $info[field] ?>"><? if($short_value) { ?><?= stripslashes($short_value) ?><? } else { ?><?= stripslashes($current_value) ?><? } ?></span>
        <script type="text/javascript">
            new Ajax.InPlaceEditor(
                '<?= $this->_table ?>_<?= $index ?>_<?= $info[field] ?>',
                '<?= $this->_controller ?>?table=<?= $this->_table ?>&field=<?= $info[field] ?>&action=ajaxField&<?= $this->_idField ?>=<?= $this->id ?><? if($this->person_id) { echo "&person_id=$this->person_id"; }?>', {
                    <?= $config ?>
                    clickToEditText:"Click to change value."
                    <? if($textarea) { ?>
                        ,onEnterEditMode: getEnterEditMode_<?= $this->_table ?>_<?= $index ?>_<?= $info[field] ?>
                        ,onComplete: getLeaveEditMode_<?= $this->_table ?>_<?= $index ?>_<?= $info[field] ?>
                    <? } ?>
            });
            <? if($textarea) { ?>
                function getEnterEditMode_<?= $this->_table ?>_<?= $index ?>_<?= $info[field] ?>(form_edit, value_edit) {
                    var long_value = $('<?= $this->_table ?>_<?= $index ?>_<?= $info[field] ?>_long').innerHTML;
                    $('<?= $this->_table ?>_<?= $index ?>_<?= $info[field] ?>').update(long_value);
                    $('<?= $this->_table ?>_<?= $index ?>_<?= $info[field] ?>').innerHTML;
                }
                function getLeaveEditMode_<?= $this->_table ?>_<?= $index ?>_<?= $info[field] ?>(form_edit, value_edit) {
                    var long_value = $('<?= $this->_table ?>_<?= $index ?>_<?= $info[field] ?>').innerHTML;
                    long_value = long_value.replace(/\\'/g,'\'');
                    long_value = long_value.replace(/\\"/g,'"');
                    long_value = long_value.replace(/\\\\/g,'\\');
                    long_value = long_value.replace(/\\0/g,'\0');
                    var value_length = long_value.length;
                    var display_value = long_value;
                    if(value_length > 150) {
                        display_value = display_value.substr(0,150) + '...';
                    }
                    $('<?= $this->_table ?>_<?= $index ?>_<?= $info[field] ?>').update(display_value);
                    $('<?= $this->_table ?>_<?= $index ?>_<?= $info[field] ?>').innerHTML;
                    $('<?= $this->_table ?>_<?= $index ?>_<?= $info[field] ?>_long').update(long_value);
                    $('<?= $this->_table ?>_<?= $index ?>_<?= $info[field] ?>_long').innerHTML;
                }
            <? } ?>

        </script><?
        if($label) $this->fieldFooter($info);
    }

   /**
    * @function fieldHeader
    * @options
    *  - raw: No display wrapping at all.
    *  - span: Use span instead of table.
    * @abstract
    *  Wrap field display in a table row/cell.
    */
    function fieldHeader($info,$opts=false) {
        if(is_array($opts)) extract($opts);
        if($div) {
          $span_type = "div";
        }
        if($span) {
          $span_type = "span";
        }
        if(is_string($info)) {
            $info = $this->_loadFieldInfo($info);
        }
        if(!$raw)
        {
            if($span_type)
            {
                echo "<$span_type ";
            } else
            {
                echo "<tr> <td align=\"right\"";
              }
             ?> class="field_label" > <?php
                      if($info[comment]) {
                        $display_comment = $info[comment];
                      } elseif($comment) {
                        $display_comment = $comment;
                      }
                      if($display_comment) {
                        yui_info("_".$info['field']."_comments",$display_comment);
                      }
                    ?><label><?= camelcaps($info["field"]); ?></label>:
                <? if($span_type) { ?>
                    </<?= $span_type ?>><<?= $span_type ?>>
                <? } else { ?>
                    </td>
                    <td><?
                }

        }

        return $info;
    }

    /**
    * HTML field footer
    * @retuns nothing
    */

    function fieldFooter(&$info,$opts=false) {
        if(is_array($opts)) extract($opts);
        if($div) $span_type = "div";
        if($span) $span_type = "span";
        if($footer) echo $footer;
        if(!$raw) {
            if($span_type) {
              ?></<?= $span_type ?>><?
            } else {
              ?></td></tr><?
            }
        }
    }

    function ajaxBoolean($info,$opts=false) {
        // http://wiki.script.aculo.us/scriptaculous/show/InPlaceSelect
        if(!is_array($info)) {
          $info =& $this->tbl[$info];
        }
        $name = $info['field'];

        $select = array("Yes","No");
        $keys = "1,0";
        $labels = "'".implode("','",$select)."'";

        if($current=$this->$name) {
          if($current==1) {
            $current = "Yes";
          } else {
            $current = "No";
          }
        } else {
          $current = "No";
        }

        $this->fieldHeader($info,$opts);
        ?>
        <span id="<?= $name ?>_<?= $this->id ?>"><?= $current ?></span>
        <script type="text/javascript">

         new Ajax.InPlaceSelect('<?= $name ?>_<?= $this->id ?>', '<?= $this->_controller ?>?action=ajaxField&field=<?= $info[field] ?>&type=binary&table=<?= $this->_table ?>&<?= $this->_idField ?>=<?= $this->id ?>', [<?= $keys ?>], [<?= $labels ?>],
          { paramName: 'value', parameters: "field=<?= $name ?>" } );
        </script>
        <?
        $this->fieldFooter($info,$opts);
    }

   /***
    * @author Jonah
    * @version 0.3 - Alpha - Jonah - 7/13/08 2:46 PM
    *   - Allow multiple event fields per page.
    *
    * @abstract
    *  Works in conjunction with saveAjax and scripaculous InPlaceSelect
    *   experimental  3/25/08 3:08 PM
    * Currently in production with the Call Campaign grid and people records,
    * Along with events for hotseat easy editing.
    */
    function ajaxFieldEnum($name,$opts=false) {
        // http://wiki.script.aculo.us/scriptaculous/show/InPlaceSelect
        global $zdb_schema;
        if(is_array($name)) {
          $info =& $name;
        } else {
          $info =& $this->tbl[$name];
        }
        // Backwards compaitibility
        if($this->indexField && !$this->_idField) {
          $this->_idField = $this->indexField;
        }

        switch($info[type]) {
            case"enum":
                $select = $this->formEnum($info,array("get_array"=>true));
                $keys = implode(",",array_keys($select));
                $labels = "'".implode("','",$select)."'";
                $keys = $labels;
                break;
            default:
                // Non-enums assume to be related key tables.
                // make $select array.
                if(class_exists($info['rel']['foreign_table'])) {
                    $foreign = new $info['rel']['foreign_table']($this->$info['field']);
                    $titleField = $foreign->_titleField;
                    $current = $foreign->$titleField;
                } else {
                    // see if phpmyadmin knows the title fields.
                    $sql = "SELECT display_field FROM pma_table_info
                        WHERE db_name = '{$rel['foreign_db']}' AND table_name = '{$info['rel']['foreign_table']}' ";
                    $foreign->_titleField = $zdb_schema->fetchOne($sql);
                    // @todo - Make this smarter.  JB  12/4/08 4:21 PM
                    $foreign->_idField = $info['rel']['foreign_table']."_id";
                }
                if(!$foreign->_titleField) $foreign->_titleField = $foreign->_idField;
                $sql = "SELECT $foreign->_idField AS id, $foreign->_titleField AS title FROM {$info['rel']['foreign_table']} ORDER BY $foreign->_titleField ";
                $res = acqueryz($sql,$info['rel']['foreign_db']);
                $options = $res->fetchAll();
                foreach($options AS $op) {
                    $select[$op['id']] = $op['title'];
                }
                $keys = implode(",",array_keys($select));
                $labels = "'".implode("','",$select)."'";

        }
        if(!$current) {
            if($current=$this->$info['field']) {
            } else {
                $current = "none";
            }
        }

        ?>
        <span id="<?= $info[field] ?>_<?= $this->id ?>"><?= $current ?></span>
        <script type="text/javascript">

         new Ajax.InPlaceSelect('<?= $info[field] ?>_<?= $this->id ?>', '<?= $this->_controller ?>?&field=<?= $info[field] ?>&action=ajaxField&table=<?= $this->_table ?>&<?= $this->_idField ?>=<?= $this->id ?>', ['0','0',<?= $keys ?>], ['change','none',<?= $labels ?>],
          { paramName: 'value', parameters: "field=<?= $info[field] ?>" } );
        </script>
        <?
    }

   /***
    * @returns string of the onchange function to be called when changed.
    *
    */
    function fieldOnChange() {
      $onchange = "onchange=\"ACUpdateRecord(".$this->table.",<?= $p->person_id ?>);\"";
    }

   /**
    * @Function formEnum
    * @Author Jonah
    * @Created  3/20/08 6:02 PM
    * $info is from the get table info function.
    * opts:
    *  raw - no table wrapper
    *  choose - show the "choose..." option from the pulldown, off by default
    *  get_array - no output, just return array of enum choices
    */
    function formEnum(&$info,$opts=false) {
        global $R;
        if(is_array($opts)) extract($opts);
        if(!$this->$info[field] && $default) $this->$info[field] = $default;
        // figure out the array
        $select = str_replace("'","",$info[size]);
        // turn string into an array
        $select = explode(",",$select);
        if($get_array) return $select;
        if(!$raw) { ?>
            <tr>
            <td align="right" valign="top" class="fieldlabel" >
              <p><label><nobr><?= camelcaps($info[field]); ?>:</nobr></label></p>
            </td><td><?
        }
        // Now do the menu itself.


        if($choose) {
            //  The following seems very retarded.  Probably a better way.  JB  3/25/08 11:20 AM
            // $select = array_merge(array(""=>"Choose..."),$select);
            // $select = array_unshift($select,"Choose...");
            if($choose===true) $choose = "Choose...";
            $selectchoose = array(""=>$choose) + $select;
            unset($select);
            foreach($selectchoose as $choose) {
              if(contains("choose",strtolower($choose))) {
                $select[''] = $choose;
              } else {
                $select[$choose] = $choose;
             }
            }
        }
        $fname = $this->getFname($info[field]);
        if($this->debug || $R[debug]) da("Default is :".$this->$info[field]. " for field $info[field] with id $this->id.");

        array_menu($select,$fname,$this->$info[field]);

        if(!$raw) { ?></td></tr><? }
    }

   /**
    * @function formBoolean
    * @modified  4/29/08 3:05 PM by Jonah
    */

    function formBoolean(&$info,$opts=false) {
        global $R;
        $fname = $this->getFname($info[field]);
        if(is_array($opts)) extract($opts);
        if(!$default) {
          if($R[$info[field]]) {
            $default = $R[$info[field]];
          } elseif($this->$info[field]) {
            $default = $this->$info[field];
          }
        }

        $this->fieldHeader($info,$opts);

        checkbox($fname,$default);
        if($this->dataGrid) {
          $this->person_id ? $id_value = $this->person_id : $id_value = $this->id;
          // For checkboxes, we need to know if they were shown in order to know if they were unchecked.  JB  5/30/08 12:04 PM
          ?><input type="hidden" name="records_shown[<?= $info[field] ?>]" value="<?= $id_value ?>" /><?
        } else {
          ?><input type="hidden" name="checkbox_shown[]" value="<?= $info[field] ?>" /><?
        }

        $this->fieldFooter($info,$opts);

    }

   /**
    *  @Function: getFname
    *  @Description:
    *  Get the name of the array that this edit form is going to
    *  build with the variables being submitted.
    *
    */
    function getFname($name) {
        if($this->form_name)  {
            $fname = $this->form_name."[".$name."]";
        } elseif($this->_table) {
            $this->form_name = $this->_table;
            $fname = $this->_table."[".$name."]";
        } else {
            $fname = $name;
        }
        // assumes person id.  Might be a better way.
        if($this->dataGrid) $fname .="[$this->person_id]";
        return $fname;
    }

   /**
    * @function DBOS->formText
    * @abstract
    *  Supports 'raw' and 'frozen' opts.
    */

    function formText($name, $default=false, $opts=false) {
        if(is_numeric($this->id) && !$default) $default = $this->$name;
        $fname = $this->getFname($name);
        if($opts['size']) {
          $size = $opts['size'];
        } elseif($strl=strlen($default)) {
          $size = $strl+2;
        }

        $info = $this->fieldHeader($name,$opts);
        if(!$opts[frozen])
        { ?>
            <input type="text" id="<?= $fname ?>" name="<?= $fname ?>" value="<?= $default ?>" maxlength="255" size="<?= $size ?>" >
            <? if(in_array($name,array_keys($this->explain))) { ?><span class="caption"><?= $this->explain[$name] ?></span><? } ?>
          <?
        } else { ?>
            <?= stripslashes($default) ?><?
        }
        $this->fieldFooter($info,$opts);
    }

    /*
    * depends on function datetime_field for fancy css/js calendar.
    */

    function formDate($name, $default=false,$opts=false) {
        if(is_numeric($this->id) && !$default) $default = $this->$name;
        $fname = $this->getFname($name);

        // Causing conflicts with displaying time selector in js calendar -- SJP 03/23/2009 09:57 AM
        //if($default=="0000-00-00 00:00:00" || $default=="0000-00-00") {
        //  unset($default);
        //}

        if(is_array($opts)) extract($opts);

        // Do the output
        $info = $this->fieldHeader($name,$opts);
        datetime_field($fname,$default);
        $this->fieldFooter($info,$opts);
    }

    public function formState($name, $default=false,$opts=false) {
        if(is_numeric($this->id) && !$default) $default = $this->$name;
        $fname = $this->getFname($name);
        ?><tr>
        <td align="right" valign="top" class="fieldlabel">
          <label><p><?= camelcaps($name); ?>:</p></label>
        </td>
        <td align="left" valign="top">
          <? state_menu($default, $fname); ?>
        </td>
        </tr>    <?
    }

    /*
    * @author Jonah  10/18/08 5:45 PM
    * @abstract
    * Create a dropdown menu from the field
    * $p is the array of info we know about a field from the table info function.
    *  TODO: support for "raw" option to hide table html
    *  TODO: support for "string" option to return string instead of output
    * @requires pulldown
    */

    function formSelect($p,$opts=false) {
        $fname = $this->getFname($p[field]);
        $this->debug = false;
        if(is_array($opts)) {
            extract($opts);
        } else { unset($opts); }
        if($this->debug) da($opts);
        if(!$opts[required]) {
            if(!$choose) $choose = "Choose...";
        } elseif(!$choose) {
            $choose = false;
        }
        $this->fieldHeader($p,$opts);
        pulldown(
                   $p[rel]['foreign_table'],
                   array(
                      "id_field"=>$p[rel][foreign_field],
                      "choose"=>$choose,
                      "fname"=>$fname,
                      "default"=>$this->$p[field],
                      "db"=>$p[rel]['foreign_db'],
                      "where"=>$opts['where'],
                      "frozen"=>$opts['frozen']
                   ),
                   $opts
                 );
         $this->fieldFooter($p,$opts);
    }

    // Show a label for a field assuming a table structure.
    public function showLabel($label) {
        ?><td align="right" valign="top" class="fieldlabel">
        <p><?= camelcaps($label); ?>:</p>
      </td><?
    }

    /**
    * Show a text area, expects the name of the field, the default value, and any options.
    * possible options: size
    */
    public function formTextArea($name, $default=false, $opts=false) {
        $cols = "80";
        switch($opts[size]) {
          case"large":
            $cols = "80";
            $rows = "30";
            break;
          case"medium":
          case"small":
            $rows = "8";
            $cols = "50";
            break;
          case"mini":
            $rows = "5";
            $cols = "40";
            break;
          default:
            $rows = "15";
            break;
        }
        $fname = $this->getFname($name);
        $this->fieldHeader($name,$opts);
        ?>
          <textarea name="<?= $fname ?>" cols="<?= $cols ?>" rows="<?= $rows ?>" wrap="VIRTUAL"><?= stripslashes($this->$name) ?></textarea>
       <? $this->fieldFooter($name,$opts);
    }
    /*
    *  @Function
    // Show a checkbox
    // $name = name of field
    // $d = default value
    // $opts = none defined
    */

    public function formCheckbox($name, $d, $opts=false) {
        if(is_array($opts)) extract($opts);
        $fname = $this->getFname($name);
        if($d) $checked = "checked";
        ?>
        <tr>
          <td align="right" valign="top" class="fieldlabel">
            <p><?= camelcaps($name); ?>:</p>
          </td>
          <td align="left" valign="top">
            <input type="checkbox" id="<?= $fname ?>" name="<?= $fname ?>" value="1" <?= $checked ?> />
          </td>
        </tr><?
    }

    public function formHidden($name, $default=false, $opts=false) {
       $fname = $this->getFname($name);
       ?><input type="hidden" name="<?= $fname ?>" value="<?= $default ?>" /><?
    }

    public function defaults() {
      return false;
    }

    /*
    * I think this is a dup function. JB  8/26/08 5:55 PM
    */

    function fName($field_name) {
        if($this->form_name) {
          $fname = $this->form_name."[".$field_name."]";
        } elseif($this->_table) {
          $this->form_name = $this->_table;
          $fname = $this->form_name."[".$field_name."]";
        } else {
          $fname = $field_name;
        }
        return $fname;
    }

    // This is a general function for deleting records that will
    // ask the user if she is sure before doing anything.

    function deleteVerify($name=false) {
      global $sure, $id, $h_section, $delete;
      global $theme, $language, $type;

      $title = $this->nameField;
      if (empty($sure)) {
        start_box();
        ?>
        <br>
        <form method="get" action="<?php echo $PHP_SELF; ?>" name="surething">
        <p align="center">Are you sure you want to delete the record for <b><?= $this->$title ?></b>?</p>
        <p align="center">
          <input type="radio" name="sure" value="1" checked>
          Yes
          <input type="radio" name="sure" value="0">
          No</p>
        <p align="center">
        <input type=hidden name='action' value='delete'>
         <input type=hidden name='id' value='<?= $this->id ?>'>
        <? if(isset($type)) {
          ?><input type="hidden" name="type" value="<?php echo $type; ?>"><?php
        } ?>
        <input type="hidden" name="<?= $name ?>" value="<?php echo $value; ?>">
        <input type="image" alt="Delete" name="Delete" value="Submit" src="/admin_live/images_oa/button_delete.gif">
      </p>
      </form>
      <?php
        end_box();
        }
    }

    /**
    * @function edit
    * @modified   4/9/08 5:38 PM by Jonah
    * @abstract
    *  Generate html form to edit the object.
    * @var opts
    *  - hide form removes opening and closing form so you can add fields and do yourself.
    */

    function edit($opts=false) {
        $R =& $_REQUEST;
        if(is_array($opts)) extract($opts);

        if(!$this->tbl) {
          $this->tbl = $this->_loadTableInfo();
          // $this->tbl = self::$_tableInfo[$this->table];
        }

        if(!$hide_form) { ?>  <form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST" name="form"> <? }
        ?><table><?
            if(is_array($this->tbl)) {
                foreach($this->tbl AS $f=>$p) {
                  // first do some default field name operations
                  if(!in_array($f,$this->hidden_fields)) {
                      if($f==$this->_idField) {
                        $this->$f ? $ivalue = $this->$f : $ivalue = "new";
                        ?><input type="hidden" name="<?= $f ?>" value="<?= $ivalue ?>" /> <?
                      } elseif($f=="hidden") {
                        $this->formCheckbox($f, $this->$f);
                      } elseif($f == "modified" || $f=="created_by" || $f=="created" || $f=="created_date") {
                        // don't show
                      } else {
                        // then decide by type
                        $this->formField($p);
                      }
                  }
                }
            } else {
              ?>Table info is not loaded correctly for:<?
              da($this);
            }
            ?>
          </table>
          <? if(is_string($footer)) { echo $footer; } ?>
          <input type="hidden" name="action" value="db_edit_<?= $this->_table ?>" />
          <input type="hidden" name="table" value="<?= $this->_table ?>" />
          <?php if(is_array($this->_passThrough)) {
            foreach($this->_passThrough AS $pass_field) {
                ?><input type="hidden" name="<?= $pass_field ?>" value="<?= $_REQUEST[$pass_field] ?>" /> <?
            }
          } ?>
         <? if(!$hide_form) { ?>
          <input type="submit" value="Save">
          </form>
          <? }
    }

    /*
    *  Alias to the "edit" function
    */
    function showForm($opts=false) {
        $this->edit($opts=false);
    }

    function editForm($opts=false) {
        $this->edit($opts=false);
    }


    /*
    * @function saveForm
    * @modified   7/21/08 2:18 PM PM by JB
    * @returns record_id
    * @var $in = associative array of input data.
    * @version 1.2 JB 7/08
    *   - Added 'on duplicate key' to inserts.
    * @version 1.1
    *   - Stomped on DBObjectAC save function, changed name to saveForm instead.
    */

    public function saveForm($in=false) {
        global $session_user;
        $debug = false;
        if(!is_array($in)) {
          $in = $_REQUEST[$this->_table];
        }

        if(!is_array($in)) {
          $this->errors[] = "No data given to DBOS->saveForm()";
          show_errors($this->errors);
          return false;
        }

        /**
        * Because this function allows for partially displayed forms, we need to know
        * what fields were shown, and if a checkbox has been unchecked, then no field
        * data is passed.  This is a special field that overcomes that.
        */
        $checkbox_shown = $_REQUEST['checkbox_shown'];
        if(is_array($checkbox_shown)) {
            foreach($checkbox_shown AS $cfield) {
                if(empty($in[$cfield])) $in[$cfield] = "";
            }
        }



        /**
        * I'm not sure why the following would work, since the fields will probably not be
        * in the form itself, thus not set in the $in array.  JB  2/5/09 3:23 PM
        */
        if(in_array("created_by",array_keys($in)) && !$in['created_by']) {
            $in['created_by'] = $session_user->user_name;
        }

        if(in_array("creator_user_id",array_keys($in))) $in[creator_user_id] = $session_user->id;

        // process incoming variables
        foreach($in AS $f=>$v) {
            // deal with dates
            if(!self::$_tableInfo[$this->_table][$f]) $tbl = $this->loadTable();

            // The following came from CCIE, possibly dealt with someplace else.  JB  3/9/09 4:05 PM
            if(self::$_tableInfo[$this->table][$f][type]=="datetime") {
              if($v) {
                  $timestamp = strtotime($v);
                  $v = date("Y-m-d H:i:s", $timestamp);
               }
            }

            $sql_sets[] = " $f = '".mysql_escape_string(stripslashes($v))."' ";
            $this->$f = $v;
        }

        if($debug) da($in);
        if($debug) da($this);

        $this->save();
        return $this->id;
    }

    public function show() {
        da($this);
    }

    /*
    * @author JB  8/12/08 12:44 PM
    * @returns array of table info
    * @overrides DBObjectAC::_loadTableInfo()
    * @abstract
    * Use phpmyadmin relationship info along with any mysql table and field data.
    */

    public function _loadTableInfo() {
        $debug=false;
        if($debug) echo "ran DBOS::_loadTableInfo()";
        // non _fields are depreciated, but for backwards compatibility.  JB  8/12/08 12:45 PM
        if($this->_table) {
          $tn = $this->_table;
        } elseif($this->table) {
          $tn = $this->table;
        }

        if($this->_db) {
          $db = $this->_db;
        } else {
          $db = $this->db;
        }

        if(!$tn) {
          show_errors("The function _loadTableInfo couldn't find the table.  Be sure to set _table for the class.");
          return false;
        }

        // Don't do this more than once if it's already set.  JB  8/12/08 12:56 PM
        if (isset(self::$_tableInfo[$tn]) && is_array(self::$_tableInfo[$tn]) && count(self::$_tableInfo[$tn])) {
            if(is_array(self::$_tableInfo[$tn])) {
                if(count(self::$_tableInfo[$tn])) {
                    if($debug) echo "returned cached table value.";
                    return self::$_tableInfo[$tn];
                }
            }
        } else {
          // Do a db call to load it.  Consider a session cache for this.  JB  8/12/08 1:08 PM
          $sql = "SHOW FULL COLUMNS FROM $tn";

          if($debug) echo $sql;

          $res = mysql_db_query($db,$sql) or mysql_die($sql);
          if (!$res) return false;
          $ti = array();
          while ($row = mysql_fetch_assoc($res)) {
              $fn = strtolower($row['Field']);
              $ti[$fn] = array();
              foreach ($row as $k => $v) {
                  $k = strtolower($k);
                  // break type into more useful type and size components
                  if($k=="type") {
                      $ps = explode("(",$v);
                      if(count($ps)) {
                        $k = $ps[0];
                        $size = substr($ps[1],0,-1);
                        $ti[$fn]['size'] = $size;
                      }
                      $ti[$fn]['type'] = $k;
                  } else {
                      $ti[$fn][$k] = $v;
                  }
                  // look for phpmyadmin relationship data

              }
              if($rel = $this->getRelation($fn)) {
                  $ti[$fn]['rel'] = $rel;
              }
          }
          self::$_tableInfo[$tn] = $ti;
          $this->tbl = $ti;
          mysql_free_result($res);
          return $ti;
        }
    }

    /*
    * @alias to _loadTableInfo();
    * @author JB  8/15/08 10:03 PM
    */

    public function loadTable($table=false) {
        if(!$this->_titleField) {
            $this->_titleField = $this->_idField;
        }
        return $this->_loadTableInfo();
    }

    /*
    * @author Jonah  10/25/08 12:09 PM
    * @returns an associative array of fields matched with request input values.
    * Under construction, only supports custom fields.
    */

    static function get_input($fields=false) {
        if(is_array($fields)) {
            foreach($fields AS $f) {
                $in[$f] = $_REQUEST[$f];
            }
            return $in;
        } else {
            // @todo, load fields from table metadata.
        }

    }

    /*
    * Under construction.  JB  8/4/08 5:31 PM
    */

    public function _get_record_name($id=false,$table=false,$db,$id_field) {
        $sql = "SELECT * FROM pma_table_info WHERE 'db_name' = '$db' AND table_name = '' ";

        // $display_field

        $sql = " SELECT $display_field FROM $table WHERE ";
    }

    /**
    * @todo - Consolidate this into a single call for a db table, so you don't do so many calls. JB  12/18/08 9:02 PM
    * @returns Array describing foreign table/key relationship of this field, or false.
    */
    public function getRelation($field) {
        global $zdb_schema;
        $use_cache = false;
        $cache = new ACCache();
        $cache->key = $this->_db."_".$this->_table."_".$field;
        // echo $cache->key."<br />";
        $debug=false;
        if(!is_object($zdb_schema)) return false;
        // now see if we have some phpmyadmin relational data

        $sql = " SELECT * FROM pma_relation
        WHERE master_db='{$this->_db}' AND master_table = '{$this->_table}' AND master_field = '{$field}' ";

        if($use_cache) {
            $result = $cache->get();
            if($result!==false) {
                 // hit
            } else {
                 $result = $zdb_schema->fetchAll($sql);
                 $cache->addValue($result,60*10);
            }
        } else {
            $result = $zdb_schema->fetchAll($sql);
        }

        if($num=count($result)) {
            $row=$result[0];
            return $row;
        } else {
            return false;
        }

    }

    /**
    * This works with scriptaculous in-place editor for text data types only.
    * @author Jonah  10/25/08 12:30 PM
    * @todo - Return the field name of the related table record in the case of relationships.
    *
    * @version 1.0
    * @returns the value given to the function that was just updated in the db.
    */

    public function saveAjax($opts=false) {
        $R =& $_REQUEST;
        if($opts && is_array($opts)) extract($opts);

        //  validate some variables
        if(!isset($R[value])) {
          $errors[] = "Value field not provided to DBOS:ajax_db, information was not saved.  Use standard edit link.";
        } else { $value=$R[value]; }

        if(!($field=$R[field])) {
          $errors[] = "Field var not provided to DBOS:ajax_db, information was not saved.  Use standard edit link.";
        }
        if(!is_numeric($this->id)) {
          $errors[] = "DBOS:ajax_db must have numeric id to update. $this->id";
        }
        if($errors) {
          show_errors($errors);
          return;
        }

        // Some data types will be translated
        if($R[type]=="binary") {
          if(strtolower($value) == "yes" || $value==1) {
              $value=1;
              $out_value = "Yes";
           } else {
              $value=0;
              $out_value = "No";
           }
        } else {
            // $out_value = $R[value];
        }
        // ok, we have sane data, give it a try:
        // $sql = " UPDATE  $this->table SET $field = '".mes($value)."' WHERE $this->indexField = '$this->id' LIMIT 1 ";
        // $this->set($field,$value);
        $this->$field = $value;
        $this->save();
        // $this->db_query($sql);
        if(!$out_value) $out_value = $this->$field;
        ?><?= $out_value ?><?
    }

    /*
    * @returns MySQL result;
    */

    public function db_query($sql) {
        if($this->db && !$this->_db) $this->_db = $this->db;
        if(!$this->_db) $this->_db = PERSON_DB;
        $result=mysql_db_query($this->_db,$sql) or mysql_die($sql); if($debug) { echo "<hr><pre> $sql </pre><hr>"; }
        return $result;
    }

    // just a helper function to facilitate writing SQL
    protected function _sql_pairs($ar) {
        return sql_pairs($ar);
    }


    /*
    * @author jonah  8/27/08 1:14 PM
    * @version 0.1 experimental  8/27/08 1:14 PM JB
    * @function ACRecord->showRelated
    * options: get_result
    * @abstract
    * Show records from another linked table that are related to this one.
    */
    function showRelated($foreign_table=false,$opts=false) {
        if(is_array($opts)) extract($opts);
        if($foreign_table) {
            $sql = " SELECT * FROM $foreign_table WHERE $this->_indexField = $this->id ";
            $result=mysql_db_query($this->_db,$sql) or mysql_die($sql); if($debug) { echo "<hr><pre> $sql </pre><hr>"; }
            if($get_result) return $result;

            // It would be nice to use the object here, and be able to customize with the $this->summary_link function.
            if(class_exists($foreign_table)) {
                while($row=mysql_fetch_array($result)) {
                    $object = new $table($row[0]);
                    $object->summary_link();
                }
            } else {
            // get the title field
            $sql = "";
            }

        }
    }

    /*
    * @function ACTable->adminActionCell
    * @creator Jonah  9/2/08 7:34 AM
    * @version alpha
    *
    */

    function adminActionCell($row) {

      if(is_array($this->_idField)) {
        foreach($this->_idField AS $field) {
            $unique_field_get .= "&".$field."=".$row[$field];
        }
      } else {
        $unique_field_get = "&".$this->_idField."=".$row[$this->_idField];
      }

      // The following is slow, but brings a lot more info.  Necessary for delete functionality and multi-index tables.  JB  9/4/08 11:48 AM
      // $record = DBOS::factory($this->_table,$row[$this->_idField]);

      ?><a href="<?= $this->_controller ?>?action=edit_<?= $this->_table ?><?= $unique_field_get ?>&table=<?= $this->_table ?>">Edit</a>
      | <a href="<?= $this->_controller ?>?action=<?= $this->_table ?>_summary&<?= $unique_field_get ?>&table=<?= $this->_table ?>">Summary</a>
        | <a class="deleteMe"
            href="javascript:confirm_delete('<?= $this->_controller ?>?action=delete&table=<?= $this->_table ?><?= $unique_field_get ?>','this record');"
            >Delete</a>
      <?
    }


    /*
    * @function ACRecord->toHTML
    * @version 1.0 alpha JB  8/14/08 2:21 PM
    * @abstract
    * Make some attempt to show the record as html.
    */

    function toHTML($opts=false) {
        global $session_user;
        pretty($this);
    }

    /*
    * Show the link to this record in admin.
    * @returns html with the link
    */

    function summary_link($opts=false) {
        ob_start();
        if($this->_title) {
            $recordTitle = $this->_title;
        } else {
            $titleField = $this->_titleField;
            $recordTitle = $this->$titleField;
        }
        ?>
        <a href="<?= $this->_controller ?>?table=<?= $this->_table ?>&action=summary&<?= $this->_idField ?>=<?= $this->id ?>"><?= $recordTitle ?>&nbsp;&gt;</a>
        <?
        return ob_get_clean();
    }

    /*
    * @author JB  8/27/08 1:56 PM
    * @returns The object associated with this table, with the record associated with the id given.
    */

    static function factory($table,$id) {
          // Only put the oddballs in this switch.  JB  10/5/08 7:43 PM
          switch($table) {
              case"":
                break;
              case"call_campaign_person":
                return CallCampaignPerson::newById($id);
                break;
              case"relation":
                return new ACRelation($id);
                break;
              case"call_log":
                return new CallLogEntry($id);
                break;
              case"report":
                return new ACReport($id);
                break;
              case"report_column":
                return new ReportColumn($id);
                break;
              case"user":
                return new ACUser($id);
                break;
              case"bug":
                return new Task($id);
                break;
              default:
                $guess = camelcaps($table);
                $guess = str_replace(" ","",$guess);
                if(class_exists($guess)) {
                  return new $guess($id);
                }
          }
    }


}  // end class DBOS

/**
* ACTree might end up extending all of this for tree/node type tables.
*/
include_once(dirname(__FILE__)."/ACTree.class.php");

//
//  Some shared functions
//

/**
* Obsolete
*/
function dbos_confirm_delete($table,$id,$db,$indexField=false) {
    // TODO: use a factory to figure out if a specific Data Object has been defined for this table.
    $dbo = new DBOS($table,$id,$db,$indexField);
    start_box("", array("style"=>"width:300;margin:auto;text-align:center;"));
    // TODO: use phpmyadmin "title" metadata to populate this.
    ?>
    Are you sure you want to delete record <?= $dbo->title ?> from table <b><?= $table ?></b>?<br /><br />
    <form action="<?= $PHP_SELF ?>" method="GET" name="form">
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="table" value="<?= $table ?>" />
    <input type="hidden" name="action" value="dbo_delete" />
    <input type="submit" value="Delete Record">
    </form>
    <?
    end_box();
}

function dbos_delete($table,$id,$db,$indexField=false) {
    $dbo = new DBOS($table,$id,$db,$indexField);
    $dbo->delete();
    show_success("Deleted record $id from table $table");
}
