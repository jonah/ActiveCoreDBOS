<?php
/**
* Also see DBOS which extends DBObjectAC and gives more html editing/ajax functions.
*
* ACTable
*  - ACTree
*    - DBObjectAC
*      - DBOS
* @version $Id: DBObjectAC.class.php 5580 2009-03-08 21:26:11Z jonah $
* @package ActiveCore
* @liscense LGPL
* @author Sam Withrow
* @author Jonah Baker, Aaron Negeurbaur, GPUSA, CCIE, World Forum Foundation
* @version 1.0  5/22/08 12:52 PM
*/

/**
* Include required parent classes.
*/

include_once(dirname(__FILE__)."/ACTable.class.php");

/**
* Also see DBOS which extends DBObjectAC and gives more html editing/ajax functions.
*
* @package ActivistCore
* @author Sam Withrow 2007
* @liscense GPL
* @version 1.0  5/22/08 12:52 PM
*/

abstract class DBObjectAC extends ACTable {
    // items pertaining to subclass
    protected $_db;
    protected $_table;
    protected $_idField;
    var $_timeStampField = 'modified';
    protected $_dbo; // zdb database connection.

    // individual items pertaining to this instance
    protected $_loaded = 0;
    protected $_dbValues = array();
    protected $_validationErrors = array();


    // holds table field info for all subclasses
    protected static $_tableInfo = array();
    protected static $_classes   = array();
    protected $_related = array();

    /**
    * Register by handing __contruct the record id only.
    */
    public function __construct($id = false) {
      $c = get_class($this);
      if (!isset(self::$_classes[$c])) {
        self::register($c,$this->_db,$this->_table,$this->_idField);
      }
      if ($idf = $this->_idField) {
        if ($this->isId($id)) {
          $this->$idf = $id;
          if ($this->exists($id)) $this->loadFields($id);
        }
      }
    }

    /**
    * register a class, to be used with DBObjectAC::factory()
    */
    public function register($className,$dbName,$tableName,$idField = '') {
      $ar = array(
        'db'    => $dbName,
        'table' => $tableName,
        'fullTable' => "$dbName.$tableName",
      );
      if ($idField != '') {
        $ar['idField'] = $idField;
      }
      self::$_classes[$className] = $ar;
      self::_loadTableInfo($className);
    }

    public function getById($id = false) {
      if (!isset($this)) {
        throw new Exception("Instatiating DBObjectAC When Class is Unknown!");
      }
      $c = get_class($this);
      return new $c($id);
    }

    public function find() {
      $c = 0;
      $str = '';
      foreach ($this->getTableFields as $tf) {
        $v = $this->getField($tf);
        if ($c++) $str .= ' AND ';
        $ev = mysql_real_escape_string($v);
        $str .= "`$k` = '$ev'";
      }
      $sql = "SELECT * FROM `{$this->_table}` WHERE {$str}";
      $res = mysql_db_query($this->_db,$sql) or mysql_die($sql);
      return $res;
    }

    /**
    * Load the values into the object.
    */

    public function loadFields($id = false) {
        if ($id === false) return false;
        $this->clearFields();
        if ($this->isId($id)) {
          $this->_load($id);
        } elseif ($id == 'new') {
          $this->setField($this->_idField,'new');
        }
    }

    /**
    * To be used when the $_table vars are set for the class when the class has been
    * manually defined.
    *
    * @author JB  8/12/08 12:16 PM
    */

    public function loadRecord($id) {
        $this->_load($id);
        if(is_array($this->_dbValues)) {
            foreach($this->_dbValues AS $f=>$v) {
              $this->$f = $v;
            }
        }
    }

    /**
    * Loads the record, but puts db fields into their own array, see loadRecord
    * for complete load.
    *
    * @author Sam Withrow 2007
    * @returns True/False
    */

    protected function _load ($id) {
        // select table fields...
        $retval = false;

        $mq = get_magic_quotes_runtime();
        set_magic_quotes_runtime(0);
        $this->id = $id;
        if(!$this->_titleField) {
          $this->_titleField = $this->_idField;
        }

        // If id is an array, set the variables.
        if(is_array($id)) {
            if(count($id)==2) {
                foreach($id AS $field=>$value) {
                  $this->$field = $value;
                }

            }
        }

        $sql = "SELECT * FROM {$this->_table} WHERE ";
        if(is_string($this->_idField)) {
          $sql .= " {$this->_idField} = '{$id}' ";
        } elseif(is_array($this->_idField)) {
          foreach($this->_idField AS $count=>$field) {
            // In theory this will always be numeric, but i'll allow for whatever
            // unless someone objects.  JB  8/10/08 3:54 PM
            $sql_pair[] = " {$field} = '{$this->$field}' ";

          }
          $sql .= implode(" AND ",$sql_pair);
        }

        $res = mysql_db_query($this->_db,$sql) or mysql_die($sql);
        // The following has run into memory limit problems, for some reason limited
        // to 20.  I had to put a 'memory_limit' line above to fix these.  Jonah  10/27/08 10:13 AM
        if ($row = mysql_fetch_assoc($res)) {
          foreach ($row as $k => $v) {
            $this->_dbValues[$k] = $v;
            // WHy not? JB  8/13/08 8:29 AM
            // $this->$k = $v;
            // See loadRecord JB  9/4/08 11:33 AM
          }
          $retval = true;
          $this->id = $id;
          $this->_loaded=true;
        } else {
          $retval = false;
          $this->_loaded=false;
        }
        set_magic_quotes_runtime($mq);
        return $retval;
    }

    public function clearFields() {
        $this->_loaded = 0;
        foreach ($this->getTableFields() as $tf) {
          unset($this->$tf);
        }
        $this->_dbValues = array();
        $this->_validationErrors = array();
    }

    public function validate() { return true; }

    public function getValidationErrors() {
      $out = array();
      foreach ($this->_validationErrors as $ve) {
        list($f,$str) = $ve;
        $out[$f] = $str;
      }
      return $out;
    }
    public function addValidationError($field,$str) {
      $this->_validationErrors[] = array($field,$str);
    }

    public function __set ( $name, $value ) {
      $this->setField($name,$value);
    }

    public function setField($field,$val) {
      $this->$field = $val;
    }

    public function setFields($fvals) {
      foreach ($fvals as $f => $v) {
        $this->$f = $v;
      }
    }

    public function getId () {
      return $this->getField($this->_idField);
    }
    public function __get ( $name ) {
      return $this->getField($name);
    }

    public function getField($name) {
        if(!$name) return false;
        if(is_array($name)) {
            foreach($name AS $field=>$value) {
                $out[] = $field."=".$value;
            }
            $str = implode("&",$out);
            return $str;
        }
        $methname = '_get_'.$name;
        if (method_exists ($this, $methname )) {
            return $this->$methname();
        } elseif(isset($this->$name)) {
            return $this->$name;
        } elseif(is_array($this->_dbValues)) {
            if(isset($this->_dbValues[$name])) {
              return $this->_dbValues[$name];
            }
        } else {
            return false;
        }
    }

    public function getPropertyInfo() {
    }

    public function getFields() {
      $fs = $this->getTableFields();
      $out = array();
      foreach ($fs as $fn) {
        $out[$fn] = $this->getField($fn);
      }
      return $out;
    }

    protected function _getFullTable($className = '') {
        if(is_object($this)) {
            if($this->_table) return $this->_table;
        }

        if ($className == '') {
          if (isset($this)) {
            $className = get_class($this);
          } else {
            throw new Exception("Tried to _getFullTable() without a class");
          }
        }
        if (isset(self::$_classes[$className]['fullTable'])) {
          return self::$_classes[$className]['fullTable'];
        }
    }

    public function loadTable($className=false) {
        return $this->_loadTableInfo($className=false);
    }

    /*
    * Load information about the table and fields from mysql.
    *
    * @author Sam Withrow 2007
    * @modified  4/17/08 6:44 PM by Jonah
    */

    public function _loadTableInfo($className=false) {
        if(!$className) $className = get_class($this);
        $tn = self::_getFullTable($className);

        list($db,$tbl) = explode('.',$tn);

        if(!$tbl) { // if table wasn't set by the method above, it failed.
            $tn = $this->_db.".".$this->_table;
            $db = $this->_db;
        }

        if (isset(self::$_tableInfo[$tn])) {
            return true;
        } else {
            $sql = "SHOW FULL COLUMNS FROM $tn";
            $res = mysql_db_query($db,$sql) or mysql_die($sql);
            if (!$res) return false;
            $ti = array();
            while ($row = mysql_fetch_assoc($res)) {
              $fn = strtolower($row['Field']);
              $ti[$fn] = array();
              foreach ($row as $k => $v) {
                $ti[$fn][strtolower($k)] = $v;
              }
            }
            self::$_tableInfo[$tn] = $ti;
            mysql_free_result($res);
            return true;
        }
    }

    /*
    * @function getTableFields
    * @returns Array of fields in the table.
    */
    public function getTableFields() {
        $o = array();
        $tn = $this->_db.'.'.$this->_table;
        if(!self::$_tableInfo[$tn]) {
            self::$_tableInfo[$tn] = $this->_loadTableInfo();
        }
        return array_keys(self::$_tableInfo[$tn]);
    }

    /**
    * Get all object fields, with HTML Entities Substituted
    */
    public function getFieldsHtmlEscaped() {
      // get all fields and html escape them
      $fvals = $this->getFields();
      $out = array();
      foreach ($fvals as $k => $v) {
        $out[$k] = htmlentities($v);
      }
      return $out;
    }

    /**
    * @author Sam Withrow
    * remove record from database
    * JB added multi-index row support.
    * @returns true/false
    */

    public function delete() {
        $idval = $this->getId();
        if (!$this->hasId()) return;

        $tn = $this->_getFullTable();

        $sql = "DELETE FROM {$this->_table} WHERE ";
        $sql .= $this->getWhere();
        $sql .= " LIMIT 1";
        if($debug) error_log($sql);
        $res = mysql_db_query($this->_db,$sql) or mysql_die($sql);
        if ($res) {
          return true;
        } else {
          return false;
        }
    }

    /*
    * @author Jonah  8/10/08 4:13 PM
    *  Return a string with the where sql pair.
    */

    public function getWhere() {
        if(is_string($this->_idField)) {
          $idf = $this->_idField;
          $sql_where = " $this->_idField = '{$this->$idf}' ";

        } elseif(is_array($this->_idField)) {
            foreach($this->_idField AS $field) {
              $sql_set[] = " $field = '{$this->$field}' " ;
            }
            $sql_where = implode(" AND ", $sql_set);
        }
        return $sql_where;
    }

    /*
    * @function save
    * @author Sam Withrow 2007
    * @returns true/false (JB would rather that it returns the record id or false  8/12/08 5:58 PM)
    * @version 1.6 JB  11/23/08 8:51 AM - Changed to return id instead of true.
    * @version 1.5 JB  10/25/08 4:20 PM
    * - fixed bug that might have done weird things to date fields.
    *
    * @version 1.4 JB  8/12/08 5:59 PM
    *  - added support for multi-index fields
    *  - return false if no fields have changed.
    *
    * @version 1.3 jonah  7/30/08 1:27 PM
    *  - fixed 'changed fields' handling
    *
    * @version 1.2 jonah  7/21/08 2:19 PM
    *   - Added 'on duplicate key' to inserts.
    */

    public function save() {
        $debug = false;
        $changed_fields = array();
        $table_fields = $this->getTableFields();
        if($this->debug) da($table_fields);
        foreach ($table_fields as $k) {
            if ($k == $this->_idField) continue;
            if ($k == $this->_timeStampField) continue;

            $v = $this->getField($k);

            // Be smart about dates.  JB  10/8/08 4:48 PM
            if($debug) da("hello $k");

            if(!self::$_tableInfo[$this->_table][$k]) {
                self::$_tableInfo[$this->_table][$k] = $this->loadTable();
                // da(self::$_tableInfo[$this->_table]);
            }

            $field_type = self::$_tableInfo[$this->_db.".".$this->_table][$k]['type'];

            if(($field_type=="datetime" || $field_type=="date") && $this->_dbValues[$k] != $v) {
                $tmp_date = $v;
                if(trim($v)) {
                    // Fix for some time situations that confuse strtotime
                    $tparts = explode(" ",$v);
                    if(is_array($tparts)) {
                      if(strtolower($tparts[2])=="pm") {
                          if(substr($tparts[1],0,2) > 12) $v = str_replace("pm","",$v);
                      }
                    }
                    $timestamp = strtotime($v);
                    $v = date("Y-m-d H:i:s", $timestamp);
                    $this->$k = $v;
                 }
                 if($debug) da("This is a date: $tmp_date : $v ");
            }
            if ($this->_dbValues[$k] != $v) {
              $changed_fields[$k] = $v;
            }
        }

        $is_update = 0;

        // deal with index fields
        if(is_string($this->_idField)) {
            $idf = $this->_idField;
            if ($id = $this->$idf) {
              if ($this->isId($id)) {
                if ($this->exists($id)) {
                  $is_update = 1;
                } else {
                  // add id to fields to insert
                  $had_id = 1;
                  $changed_fields[$idf] = $id;
                }
              }
              // if there's no id, we just hope for having an id from auto_increment
              $sql_where =  " `{$idf}` = '{$id}' ";
            }
        } elseif(is_array($this->_idField)) {
            unset($sql_set);
            foreach($this->_idField AS $field) {
              $sql_set[] = " $field = '{$this->$field}' " ;
            }
            $sql_where = implode(" AND ", $sql_set);
        }

        // Don't do the query if we don't seem to have anything new to save.
        if (empty($changed_fields) && !is_update) {
          // When creating a new record it is legitimate to not set any fields.
          if($this->debug) show_errors("There were no fields to change.");
          return false;
        } else {
          if($this->debug) da($changed_fields);
        }


        $ss = $this->_setstr($changed_fields);
        $set_sql = "{$ss}";
        if(!$set_sql) {
            // apparently, nothing has changed.
            return false;
        }

        if ($is_update) {
          $sql = "UPDATE {$this->_table} SET {$set_sql} WHERE {$sql_where} LIMIT 1";
        } else {
          $sql = "INSERT INTO {$this->_table} SET {$set_sql} ";
          // the following isn't implemented yet.  JB  5/28/08 8:21 AM
          $sql .= " ON DUPLICATE KEY UPDATE {$set_sql}";
        }
        $this->last_sql = $sql;
        if($this->debug) echo "SQL: $sql\n";

        $ok = mysql_db_query($this->_db, $sql);
        if($this->debug) da($sql);
        if (!$ok) {
          mysql_die($sql);
          return false;
        } else {
          if (!$is_update and !$had_id) {
            $id = mysql_insert_id();
          }
          $this->loadFields($id);
          return $id;
        }
    }

    /*
    *
    * Whether or not a record with the given ID exists in the DB
    *
    * @return bool
    */

    public function exists($id = '') {
      $idf = $this->_idField;
      if ($id === '') $id = $this->getId();
      // bail if a lookup would obviously fail
      if (!$this->hasId()) return false;
      $sql = "SELECT `{$this->_idField}` FROM `{$this->_table}` WHERE `{$this->_idField}` = '{$id}'";
      $foundit = false;
      if ($res = mysql_db_query($this->_db,$sql) or mysql_die($sql)) {
        if (mysql_num_rows($res) == 1) {
          $found_id = mysql_result($res,0,0);
          if ($found_id == $id) {
            $foundit = true;
          }
        }
        mysql_free_result($res);
      }
      return $foundit;
    }

    /*
    * @author Sam Withrow 2007
    * hasId() - say whether this has a valid id (may or may not exist in DB)
    *
    * default implementation just checks if it's an integer.
    *
    * @version 1.1 JB  8/10/08 4:19 PM
    *  - added mutli-index field support.
    * @return bool
    */

    public function hasId() {
        if(is_string($this->_idField)) {
            if ($this->isId($this->getId())) return true;
            else return false;
        } elseif(is_array($this->_idField)) {
            // multi-field array, make sure they are all set.  JB  8/10/08 4:18 PM
            foreach($this->_idField as $field) {
              if(!$this->$field) return false;
            }
            return true;
        } else {
            return false;
        }
    }

    /**
    * say whether the given value could be a valid ID
    */

    public function isId($val) {
      if ($val and is_numeric($val)) return true;
      return false;
    }

    /*
    * @var $ar - array of the variables to be set field=>value
    * @returns this = that, type string for a sql insert.
    * @version 1.1 added better 'human' formatted date handling. JB  5/22/08 1:06 PM
    */

    private function _setstr($ar) {
        $os = array();
        foreach ($ar as $k=>$v) {
            $ssv = stripslashes($v);
            $ev = mysql_real_escape_string($ssv);

            if ($this->_isDateField($k)) {
                $dv = trim(strtolower($ev));

                if ($dv == 'now' or $dv=='now()') {
                  $os[] = "`$k` = NOW()";
                } else {
                  // look for 'human' formatting
                  if(contains("/",$ev) || contains(",",$ev)) {
                      $timestamp = strtotime($ev);
                      $ev = date("Y-m-d H:i:s", $timestamp);
                  }
                  $os[] = "`$k` = '$ev'";
                }
            } else {
                $os[] = "`$k` = '$ev'";
            }
        }
        $ss = join(", ",$os);
        return $ss;
    }

    protected function _isDateField($f) {
      $tn = $this->_db.'.'.$this->_table;
      if ($ft = self::$_tableInfo[$tn][strtolower($f)]['type']) {
        if (preg_match('@^(?:date|time)@i',$ft)) {
          return true;
        }
      }
      return false;
    }

      // This is a general function for deleting records that will
      // ask the user if she is sure before doing anything.

      function deleteVerify($name=false) {
        global $sure, $id, $h_section, $delete;
        global $theme, $language, $type, $admin_dir;
        if($name) {
            $title = $name;
        } else {
            $title = $this->nameField;
        }
        if (empty($sure)) {
          start_box();
          ?>
          <br>
          <form method="get" action="<?php echo $PHP_SELF; ?>" name="surething">
          <p align="center">Are you sure you want to delete the record for <b><?= $title ?></b>?</p>
          <p align="center">
            <input type="radio" name="sure" value="1" checked>
            Yes
            <input type="radio" name="sure" value="0">
            No</p>
          <p align="center">
          <input type=hidden name='action' value='delete_record'>
          <input type="hidden" name="table" value="<?= $this->_table ?>">
           <input type=hidden name='id' value='<?= $this->id ?>'>
          <? if(isset($type)) {
            ?><input type="hidden" name="type" value="<?php echo $type; ?>"><?php
          } ?>
          <input type="hidden" name="<?= $name ?>" value="<?php echo $value; ?>">
          <input type="image" alt="Delete" name="Delete" value="Submit" src="/<?= $admin_dir ?>/images_oa/button_delete.gif">
        </p>
        </form>
        <?php
          end_box();
          }
      }

      /*
      * @function dumpRecords
      * @author jonah
      * @options master_id: The record id to force for the master record.
      *
      * Dump the sql records for a particular master record.
      */
      function dumpRecords($opts=false) {
          if(is_array($opts)) extract($opts);

          if($header) { ?><textarea cols="160" rows="200"><? }
          // dump the master record.
          foreach($this->tbl as $f) {

            $sets[] = " $f[field] = '".mes($this->$f[field])."'";
          }

          $out = " INSERT INTO $this->_table SET ".implode(",",$sets);

          echo $out;

          // dump dependent records
          echo "\r\r\r\r";
          $this->getRelated();

          $tables_used = array($this->_table);
          foreach($this->_related as $rel) {
            // get relations

            if($rel['table'] != $this->_table) {
              $sql = "SELECT * FROM ".$rel['table']." WHERE $this->indexField = $this->id ";
              $result = acquery($sql,$rel['db']);
              while($row=mysql_fetch_array($result)) {
                extract($row);
                $link = new DBOS($rel['table'],$row[0],$rel['db']);
                $link->dumpRecords();
                $tables_used[] = $rel['table'];
              }

            }

          }
          if($header) { ?></textarea><? }
      }

      /*
      * @function getRelated
      * @version experimental
      * @created Jonah
      *
      */
      function getRelated($opts=false) {
          global $zdb_schema;
          $sql = "SELECT r.* FROM pma_relation r

              WHERE foreign_db = '$this->_db' AND foreign_table = '$this->_table'
              AND foreign_field = '$this->indexField' ";
          // da($sql);
          $res = $zdb_schema->getAll($sql);
          if(count($res)) {
              foreach($res AS $row) {
                  extract($row);
                  $link['db'] = $row['master_db'];
                  $link['table'] = $row['master_table'];
                  $link['field'] = $row['master_field'];
                  $this->_related[] = $link;
              }
              return $this->_related;
          }
      }
}