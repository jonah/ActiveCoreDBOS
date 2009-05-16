<?php
/**
 * Auto-generated tables for DBOS classes.
 * Currently table related function are sprinked around, mostly in DBOS, should be collected here.
 *
 * @license GPL
 * @version $Id: $
 * @package ActivistCore
 * @subpackage DBOS
 *
 */

/*
 * Auto-generated tables for DBOS classes.
 * Currently table related function are sprinked around, mostly in DBOS, should be collected here.
 *
 * @author JB  9/4/08 2:47 PM
 * @version 0.1 JB  10/7/08 11:23 AM
 * - Now supports ajax grid editing for most field types.
 */

class ACTable {

    /*
    * @creator JB  8/27/08 1:56 PM
    * @returns The object associated with this table, with the record associated with the id given.
    * @abstract
    * Shows an error if the class can't be found.
    */

    static function factory($table,$opts=false) {
          switch($table) {
              case"":
                break;
              case"report":
                return new ACReportTable();
                break;
              case"report_column":
                return new ReportColumnTable();
                break;
              default:
                $guess = camelcaps($table);
                $guess = str_replace(" ","",$guess);
                $guess = $guess."Table";
                if(class_exists($guess)) {
                    return new $guess();
                }
          }
          echo "Could not find class for $guess";
    }

    /**
    * @author Jonah  2/5/09 11:18 AM
    */
    public function addFilter($pairs) {
        if(!is_array($pairs)) return false;
        foreach($pairs AS $field=>$value) {
            $this->_where .= " AND $field = '$value' ";
        }
    }

    /*
    * @function ACTable->showTable
    * @version 0.1 Alpha JB  8/4/08 6:22 PM
    * - opts
    *   - where - raw sql appended to select statement, expects " AND field = '$value' "
    *   - join - raw sql to be appended to the join section. EX.
        $opts = array(
            "where"=>" AND rtc.report_id = $this->id ",
            "join"=>" JOIN report_to_column AS rtc USING(column_id) "
        );
    * In use on person profile table.
    * @todo move this function to the right class.
    */

    public function showTable($opts=false) {
        global $zdb_action;
        if(is_array($opts)) extract($opts);
        if(!$view) $view = $_REQUEST['view'];

        if($this->_gridEdit==true) $ajax = true;

        if(isset($_REQUEST['row'])) {
          $row = $_REQUEST['row'];
          $date_range = $this->rowToDateRange($row,$view);
          $filters['dates'] = " from ".$date_range['date_starting']." to ".$date_range['date_ending'];
        }

        $limit_chapter = $this->limitChapterSql();
        $sql = " SELECT {$this->_table}.* ";
        $sql .= $limit_chapter['select'];
        $sql .= " FROM $this->_table ";
        $sql .= $limit_chapter['join'];
        $sql .= $join;
        $sql .= " WHERE 1";
        $sql .= $limit_chapter['where'];
        $sql .= $date_range['where'];
        $sql .= $this->_where;

        if($where) $sql .= $where;

        if($this->_dateField) {
            $sql .= " ORDER BY $this->_dateField DESC ";
        }

        $this->_tableSql = $sql;
        if($sql_only) return $sql;
        $browse = new browse($sql,array("db"=>$this->_db,"rows"=>100));

        foreach($_GET AS $f=>$v) {
            $browse->add_var("&{$f}={$v}");
        }

        if(is_object($this->_dbo)) {
            $rows=$this->_dbo->fetchAll($browse->sql);
        } else {
            $rows=$zdb_action->fetchAll($browse->sql);
        }

        // Backwards compatibility for GreenpeaceUSA.  JB  10/7/08 4:30 PM
        if(is_array($this->hidefields)) {
            $hidefields=$this->hidefields;
        } elseif(is_array($this->_hiddenFields)) {
            $hidefields = $this->_hiddenFields;
        } else {
            $hidefields = array();
        }
        // Activate some graphing variables.
        $graphf = array(); $data = array();
        $i=0;
        if(!$this->_tableTitle) {
            $this->_tableTitle = camelcap($this->_table);
        }

        if(is_array($limit_chapter)) {
            $chapter_id = $limit_chapter[chapter_id];
            $chapter_name =  Chapter::get_name($chapter_id);
            $filters[chapter] = " in chapter $chapter_name ";
        }

        if($num=count($rows)) {
            ?>
            <div>Showing <b><?= $num ?></b> <?= $this->_tableTitle ?>s
            <? if(is_array($limit_chapter)) {
                ?>for chapter <b><?= $chapter_name ?></b>
            <? } ?>
            .</div>
            <?= $browse->show_nav_lite(); ?>
            <table class="list"><?
            foreach($rows AS $row) {
                $i++; $i % 2 ? $row_class = "odd" : $row_class = "even";
                extract($row);

                if($i==1) { // do header row ?>
                      <tr class="reverse">
                        <td>Action</td>
                        <?
                        foreach($row as $f=>$v) {
                          if(!in_array($f,$hidefields)) { ?>
                            <td><?= camelcap($f); ?></td>
                       <? }
                        }
                        ?>
                      </tr>
             <? }
                if($ajax) {
                    if(is_array($this->_idField)) {
                        $ajax = false;
                    } else {
                        $this->loadRecord($row[$this->_idField]);
                    }
                }
                ?>
                <tr class="<?= $row_class ?>">
                  <td nowrap>
                  <? $this->adminActionCell($row); ?>
                  </td>
                  <? foreach($row AS $f=>$v) {
                         if(!in_array($f,$hidefields)) {
                            // Deal with fields that have relationships defined to other tables via phpmyadmin.
                            if($related=$this->tbl[$f][rel]) {
                                $obj = DBOS::factory($this->tbl[$f][rel][foreign_table],$v);
                                if(is_object($obj)) {
                                    $v = $obj->summary_link();
                                }
                            }
                            if($f==$graphf[0]) $data[$i-1][label] = $v;
                            if($f==$graphf[1]) $data[$i-1][point] = $v;
                            ?>
                            <td>
                              <? if(!$ajax || $related || $this->tbl[$f][type]=="timestamp") { ?>
                                <?= $v ?>
                              <? } else {

                                $ajaxable = $this->ajaxField($f,array("raw"=>true,"gridedit"=>true));
                                if($ajaxable===false) echo $v;
                               } ?>
                              </td>
                       <? } ?>
                  <? } ?>
                </tr>
                <?php
            }
            ?></table>

            <center><? $browse->show_nav(); ?></center>
            <?php
            if(count($data)) {
              $g = new ACGraph($data);
              $g->render();
            }
        } else {
            ?><p id="noRecordsFound"><?php
            if($this->_noRecordsMessage) {
                echo $this->_noRecordsMessage;
            } else {
                ?>No records found in <?= $this->_tableTitle ?> table <?php
            }
            ?></p><?php
        }
        if(is_array($filters)) {
           ?><br /> for filters:<br /><?
           echo implode(",<br />",$filters);
        }
        ?><div class="table_debug_info" style="display:none;"><? da($sql); ?></div><?
    }

    /**
    * Huh? - Jonah  2/5/09 11:27 AM
    */
    function clear_inuse_records() {
      $sql = " update call_campaign_person set available = 1 where status = '' and call_campaign_id = 309 ";
    }

    /*
    * Returns the name of the record, the title, or the person's name, or whatever.
    * This won't work for all objects, since there isn't always a good name to be returned.
    *
    * Under construction JB  10/20/08 9:54 AM
    * @function getRecordName
    * @creator Jonah  8/30/08 7:13 PM
    */

    public function getRecordName($id) {
       if(!$this->_titleField) return $id;

       $sql = " SELECT $this->_titleField FROM $this->_table WHERE $this->_idField = '".mes($id)."' ";
       return $this->_dbo->fetchOne($sql);
    }

    static function arrayToUL($array) {
        ?><ul class="items"><?
        foreach($result AS $row) {
          extract($row); ?>
          <li class="item">
              <div class="name"><?= $id ?></div>
              <span class="informal"><?= $title ?></span>

          </li>
        <? } ?>
        </ul><?

    }

    function showAdminTable($opts=false) {
      $this->adminPageTop();
      $this->showTable($opts);
    }

    function getRes($sql) {
        return acquery($sql,$this->_db);
    }


    /**
    * @creator Jonah  8/19/08 12:45 PM
    * This is a table related function.  This is for tables that 99% of the time will be
    * filtered to a particular field.  Filters survey_response to a survey id,
    * to person_to_attribute to a particular attribute.
    */

    public function defaultFilter($id) {
        return false;
    }

    public function trendGraph($opts=false) {
        return $this->graph($opts);
    }



    /*
    * Tinker with the sql to find the records belonging to a particular chapter.
    */
    function limitChapterSql($chapter_id=false) {

        // Some alpha chapter limiting stuff
        if($chapter_id=$_REQUEST['chapter_id']) {
            $out[chapter_id] = $chapter_id;
            // we want to avoid limiting to chapter id twice.  I'm not sure
            // what the best way to do that is.  JB  10/7/08 4:28 PM
            if(!contains("chapter_id =",$sql)) {
                $where .= " AND ".$this->_table.".chapter_id = $chapter_id ";
            }
        }
        if($where) $out[where] = $where;
        return $out;
    }

    /*
    * @function ACTable->rowToDateRange
    * @returns array with sql and other date info
    * @abstract
    * $row is an integer of time past.  Row 2 of a weekly chart will give
    * the date range from two weeks ago.
    * @version 0.2 beta - supports month - JB  11/21/08 9:31 AM
    * @version 0.1 alpha experimental JB  8/25/08 1:13 PM
    */

    function rowToDateRange($row,$view="weekly",$opts=false) {
        $debug = false;
        if(!$view) $view = $this->_default_view;
        if(!$view) $view = "weekly";
        // @todo - Add daily, yearly, quarterly, hourly.  JB  12/3/08 1:04 PM
        switch($view) {
            case"week":
            case"weekly":
                $week_now = date('W');
                $current_week = $week_now - $row;
                $day_starting = strtotime(date('Y')."W".$current_week."0");
                $day_ending = strtotime(date('Y')."W".$current_week."7");
                break;
            default:
            case"month":
            case"monthly":
                $month_now = date('m');

                $current_month = $month_now - $row;
                if($debug) echo $current_month;
                $day_starting = strtotime(date('Y')."-".$current_month."-1");
                $day_ending = strtotime(date('Y')."-".$current_month."-31");

                break;


       }
       $sdate=date('M-d-Y',$day_starting);
       $edate=date('M-d-Y',$day_ending);

       $out['date_starting'] = $sdate;
       $out['date_ending'] = $edate;

       $out[sql_starting] = date ("Y-m-d H:i:s", $day_starting);
       $out[sql_ending] = date ("Y-m-d H:i:s", $day_ending);

       $out['where'] = " AND $this->_dateField > '{$out[sql_starting]}' AND $this->_dateField <= '{$out[sql_ending]}' ";
       return $out;
    }

    /*
    * @version experimental JB  11/21/08 9:32 AM
    * For ajax autocomplete
    */
    function searchUL($search,$opts=false) {
        if(!$search) echo("No search given to searchUL");
        $sql = $this->showTable(array("sql_only"=>true,"where"=>" AND $this->_titleField LIKE '%".mes($search)."%' "));
        $sql .= " LIMIT 100 ";
        $result=acquery($sql,$this->_db);
        ?><ul><?
        while($row=mysql_fetch_assoc($result)) {
            extract($row);
            ?><li><?= $row[$this->_idField] ?><span class="informal"> - <?= $row["title"] ?></span></li><?
        }
        ?></ul><?
    }

    /*
    * @ACTable->getTrendSql
    * @creator Jonah  8/18/08 7:40 PM
    * @abstract
    * Find single 'cell' with a date, will either count the records in a table
    * in that date range or sum a field.
    * - opts array
    *   - view (weekly, monthly, etc.)
    */

    function getTrendSql($opts=false) {

        if(is_array($opts)) extract($opts);

        if($chapter_id=$_REQUEST['chapter_id']) { $this->_chapter_id = $chapter_id; }
        $g = group_time($view,$this->_dateField,array("table"=>$this->_table));

        // Make sure that we haven't manually limited to chapter_id already
        if(!contains("chapter_id",$where)) $chapter = $this->limitChapterSql();

        if($chapter['where']) $where .= $chapter['where'];
        if($chapter['join']) $join .= $chapter['join'];

        // Begin building the sql string.

        $sql = " SELECT ";
        if($field) {
          $sql .= " SUM($field) ";
        } else {
          $sql .= " count(*) ";
        }
        $sql .= " as count, {$this->_table}.$this->_dateField FROM $this->_table ";
        if($join) $sql .= $join;

        $sql .= " WHERE 1 ";

        if($where) $sql .= $where;

        $sql .= $g['sql'];
        // Having needs to be inserted between ORDER BY and other stuff.
        // if($having) $sql .= " ".$having;

        return $sql;
    }

    /*
    * @function ACTable->getTrendCell
    * @creator Jonah  8/19/08 12:55 PM
    * @returns A numeric value of a count or sum of things that happened within a time interval.
    * @abstract
    * If a field is given it's assumed that you want it to be summed. Otherwise it simply
    * be the rate at which records are added to the table. JB  8/15/08 10:19 PM
    */

    public function getTrendCell($date,$field=false,$view="weekly",$opts=false) {
        $debug = false;
        if(is_array($opts)) extract($opts);

        if(is_numeric($date)) {
            // The date is an interval, 0 is now, 1 is yesterday or last week, etc.
            $interval = $date;
            /*
            * Currently the system will not work across years.  We should be converting the
            * 'weeks since today' format, into the week of the year, then into a date range
            * with a start date and an end date. JB  9/5/08 2:31 PM
            */
            $date_range = $this->rowToDateRange($interval,$view);
            if($debug) { echo "<br> Date range is: "; da($date_range); }
            if($debug) echo "<br> The view is: $view <br>";
            switch($view) {
                case"weekly":
                    $where .= " AND WEEK({$this->_table}.{$this->_dateField}) = (WEEK(NOW()) - $date) AND YEAR({$this->_table}.{$this->_dateField}) = (YEAR(NOW()) - 0) ";
                    break;
                default:
                case"monthly":
                    $where .= " AND {$this->_table}.{$this->_dateField} > '".mes($date_range['sql_starting'])."' AND {$this->_table}.{$this->_dateField} <= '{$date_range[sql_ending]}' ";
                    break;
            }
        } else {
            // I'm not sure why this would be, if it's not numeric, we might as well error and die.  JB  11/20/08 6:17 PM
            // $where .= " AND WEEK({$this->_table}.{$this->_dateField}) = WEEK('".mes($date)."') ";
        }


        $sqlopts = array("where"=>$this->_where.$where,"field"=>$field,"join"=>$join,"view"=>$view,"having"=>$having);
        $this->sql = $this->getTrendSql($sqlopts);

        if($_REQUEST['debug_dbos']) da($this->sql);
        $res = $this->getRes($this->sql);
        if($num=mysql_num_rows($res)) {
            $row=mysql_fetch_assoc($res);
            return $row['count'];
        } else {
            return 0;
        }

    }

    /*
    * @ACTable->graph
    * @author Jonah  8/18/08 7:41 PM
    * Show a flash trend graph.  This was built before the new getTrendSql features,
    * so it needs to be redone with those to be starter.
    * opts:
    * - title
    */

    public function graph($opts=false) {
        $debug = false;
        if(is_array($opts)) extract($opts);

        if(!$view) $view="weekly";

        if(!$this->tbl)   {
          $this->tbl = $this->_loadTableInfo();
          // $this->tbl = self::$_tableInfo[$this->_table];
        }

        if(!$title) {
            $title = $this->_table;
        }

        if($debug) da($this->tbl);

        if(!$this->_dateField) {
            // @todo, infer datefield from field info. JB  8/8/08 2:59 PM
            $errors[] = "We don't know the date field for $this->_table.";
        }

        if($errors) show_errors($errors);
        $g = group_time($view,$this->_dateField,array("table"=>$this->_table));
        $sql = $this->getTrendSql($opts);

        $trend = acqueryz($sql,$this->_db);
        $trend = $trend->fetchAll();

        $hidefields = array();
        $graphf = array($this->_dateField,'count'); $data = array();
        $i=0;
        if(count($trend)) {
            ?>
            <div id="tableWrapper" style="display:none">
            <div>Showing <b><?= $num ?></b> records</div>
            <table class="list"><?
            foreach($trend AS $row) {
                $i++; $i % 2 ? $row_class = "odd" : $row_class = "even";
                extract($row);
                if($i==1) { // do header row ?>
                      <tr class="reverse">
                        <td>Action</td>
                        <?
                        foreach($row as $f=>$v) {
                          if(!in_array($f,$hidefields)) { ?>
                            <td><?= camelcap($f); ?></td>
                       <? }
                        }
                        ?>
                      </tr>
                <? } ?>
                <tr class="<?= $row_class ?>">
                  <td> <a href="<?= $this->_controller ?>?action=edit_<?= $this->_table ?>&<?= $this->_idField ?>=<?= $$this->_idField ?>">Edit</a>
                  </td>
                  <? foreach($row AS $f=>$v) {
                         if(!in_array($f,$hidefields)) {
                            if($f==$graphf[0]) {
                              $v = date($g[dformat],strtotime($v));
                              $data[$i-1][label] = $v;
                            }

                            if($f==$graphf[1]) $data[$i-1][point] = $v;
                            ?>
                            <td> <?= $v ?> </td>
                       <? } ?>
                  <? } ?>
                </tr>
                <?php
            }
            ?></table>
            </div>
            <?php
            if(count($data)) {
              $g = new ACGraph($data,$title." ".$view);
              $g->render();
            }
        } else {
            ?><p>No <?= $title ?> records found</p><?
        }
    } // end function graph

    /*
    * @function ACTable->adminPage
    * @creator JB  8/26/08 5:20 PM
    * @abstract
    * The central switch statement for the admin pages associated with DBOS tables/classes.
    */

    public function adminPage($action=false) {

        if(!$action) $action = $_REQUEST['action'];
        if(!$table) $table = $_REQUEST['table'];
        // Only supports simple index fields.
        if(!is_array($this->_idField)) {
            if(!$id) $id = $_REQUEST[$this->_idField];
        } else {
            foreach($this->_idField AS $field) {
              $id[$field] = $_REQUEST[$field];
            }
        }

        $this->adminPageTop();
        switch($action) {
            case"delete":
                $obj = DBOS::factory($this->_table,$id);
                $obj->delete();
                show_success("The record was delete from ".$this->_table);
                $this->showTable();
                break;
            case"summary":
            case $this->_table."_summary":
                $obj = DBOS::factory($this->_table,$id);
                // Should be toAdminHTML.
                if(is_numeric($obj->id)) {
                  ?>| <a class="editMe" href="<?= $_SERVER[PHP_SELF] ?>?action=edit&table=<?= $obj->_table ?>&<?= $this->_idField ?>=<?= $obj->id ?>">Edit this <?= $obj->_table ?></a><?
                }
                $obj->toHTML();
                break;
            case"edit_".$this->_table:
            case"edit":
                $obj = DBOS::factory($this->_table,$id);
                $obj->showForm();
                break;
            case"db_edit_".$this->_table:
                $obj = DBOS::factory($this->_table,$id);
                $obj->saveForm();
                $this->showTable();
                break;
            case"list":
            case"":
                $this->showTable();
                break;
            default:
              ?>The action <?= $action ?> is not defined for this the <?= $this->_table ?> class.  Use Object->adminPage to set.<?
        }
    }



    /**
    * @author jonah 2008
    */
    public function adminPageTop() {
        ?><a class="addMe" href="<?= $_SERVER[PHP_SELF] ?>?action=edit&table=<?= $this->_table ?>&<?= $this->_idField ?>=new">Create New <?= $this->_table ?></a>
        | <a href="<?= $_SERVER[PHP_SELF] ?>?table=<?= $this->_table ?>">List All <?= $this->_table ?>s</a>
        <?
        if(is_numeric($this->id)) {
          ?><a class="addMe" href="<?= $_SERVER[PHP_SELF] ?>?action=edit&table=<?= $this->_table ?>&<?= $this->_idField ?>=<?= $this->id ?>">Edit <?= $this->_table ?></a><?
        }
    }

}  // end class ACTable
