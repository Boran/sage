<?
/** funcs.inc.php
 *
 * Common functions for sage interface
 *

 *
 * Author: Sean Boran on github. License GPL.
 *
 * Requirements: 
 * A Windows box with Sage, Sage ODBC and PHP with mysql libraries.
 * Sage DSN must be points to actual data (e.g. the demo company).
 * tested with php 5.3.18 and Sage 50 accounts 2013.
 */
 
require_once('config.inc');   // site specific settings like $odbc


/**
 * sage_getTableNames()
 * Input: $odbc (global), debug (0 or if >1 exho ebug messages)
 * Output: $felds has structure, $results has the data
 */
function sage_getTableNames($table, $debug, &$results)
{  
  global $odbc;
  // Connect to the source ODBC
  if ($debug>0) echo "Connect to " . $odbc['dsn'] . ' as ' . $odbc['user'] . "\n";
  $conn = odbc_connect($odbc['dsn'], $odbc['user'], $odbc['pass']);
  if (!$conn) {
    die("Error connecting to the ODBC database: " . odbc_errormsg());
  }
  
  $allTables = odbc_tables($conn);
  while (odbc_fetch_row($allTables)) {
    if (odbc_result($allTables, "TABLE_TYPE") == "TABLE") {
        $results[] = odbc_result($allTables, "TABLE_NAME");
    }
  }
}

  

/**
 * sage_getTable()
 * Input:  $odbc (global), table name, debug (0 or if >1 echo debug messages)
 * Output: $felds has structure, $results has the data
 */
function sage_getTable($table, $debug, &$fields, &$results)
{  
  global $odbc;
  // Connect to the source ODBC
  if ($debug>0) echo "Connect to " . $odbc['dsn'] . ' as ' . $odbc['user'] . "\n";
  $conn = odbc_connect($odbc['dsn'], $odbc['user'], $odbc['pass']);
  if (!$conn) {
    die("Error connecting to the ODBC database: " . odbc_errormsg());
  }
	
  // 1. Get the source structure and data
  if ($debug>0) echo ($table);
  // get first, or all entries in the table.
    $sql = "SELECT * FROM " . $table;   // grab all records
    //$sql = "SELECT TOP 1 * FROM " . $table;  // grab just first record
    $r = odbc_exec($conn, $sql);
    if (!$r) {
      die("Error: " . $table . "\n" . odbc_errormsg());
    }
        
    // how many fields and rows has the table?
    $maxfields=odbc_num_fields($r);
    if ($debug>0) echo ", fields=". $maxfields . ", rows=" . odbc_num_rows($r) . "\n"; 
    if (odbc_num_rows($r)==0) {   // if no rows, don't go looking for data _or_ fields
      if ($debug>0) echo "$table has no rows, skipping";
	  exit;   
	}

    // Get all the field names and store in an array $fields
    if ($maxfields>3) {
      $maxfields=$maxfields-3;   // TODO: why this is needed, php crashes otherise
    }  
    $ignores=array('DATE_ACCOUNT_OPENED',);  // TODO: if there are fields not to show
    for ($i = 1; $i <= $maxfields; $i++) {
      if ($debug>1) echo "\nfield " . $i . odbc_field_name($r, $i) . " type=" . odbc_field_type($r, $i);
      if (! in_array(odbc_field_name($r, $i), $ignores)) 
         $fields[odbc_field_name($r, $i)] = odbc_field_type($r, $i);
         //else 
         //  echo "Ignore: " . odbc_field_name($r, $i);
      }   
      //print_r($fields);   // to show the field structure  
        
     // for each row store data in array $results indexed by fieldname
     $x = 0;
     while ($row = odbc_fetch_row($r)) {
       for ($i = 1; $i <= odbc_num_fields($r); $i++)
         if (! in_array(odbc_field_name($r, $i), $ignores)) 
           $results[$x][odbc_field_name($r, $i)] = odbc_result($r, $i);
           $x++;
     }
     //print_r($results);    // to print out the data per record
     //print_r($results[0]);   // print first row
}     


?>