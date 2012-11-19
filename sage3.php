<?

/** sage3.php
 *
 * Sync specific tables from sage via ODBC to a mysql database.
 *
 * Author: Sean Boran on github. License GPL.
 *
 * Requirements: 
 * A Windows box with Sage, Sage ODBC and PHP with mysql libraries.
 * Sage DSN must be points to actual data (e.g. the demo company)
 * tested with php 5.3.18 and Sage 50 accounts 2013.
 
The output might be something like this:
$ /c/php/php.exe sage3.php
Table SALES_LEDGER with key ACCOUNT_REF
Completed sync of SALES_LEDGER
Table PURCHASE_LEDGER with key ACCOUNT_REF
Completed sync of PURCHASE_LEDGER
Table NOMINAL_LEDGER with key ACCOUNT_REF
Completed sync of NOMINAL_LEDGER
Table TAX_CODE with key TAX_CODE_ID
Completed sync of TAX_CODE
Table CURRENCY with key NUMBER
Completed sync of CURRENCY
Table AUDIT_SPLIT with key TRAN_NUMBER
Completed sync of AUDIT_SPLIT

 */
 
require_once('config.inc');
require_once('funcs.inc');    // common functions 

$debug=0;                     // set to 1 for verbose messages

// mysql destination
/*
$mysql['host'] = "localhost";
$mysql['user'] = "root";
$mysql['pass'] = "";
$mysql['dbname'] = "sagetest";*/

// ----------------------------------

// Open Mysql destination DB
$myconn = mysql_connect($mysql['host'], $mysql['user'], $mysql['pass']);
if (!$myconn)
  die("Error connecting to the MySQL database: " . mysql_error());
if (!mysql_select_db($mysql['dbname'], $myconn))
  die("Error selecting the database: " . mysql_error());


// Step 1: get list of tables
//$tables = array();
//sage_getTableNames($table, $debug, $tables);  // grab a list of all tables
// New: use specific tables that have tested, and keys defined
$tables = array(
    'SALES_LEDGER' => 'ACCOUNT_REF',
    'PURCHASE_LEDGER' => 'ACCOUNT_REF',
    'NOMINAL_LEDGER' => 'ACCOUNT_REF',
    'TAX_CODE' => 'TAX_CODE_ID',
    'CURRENCY' => 'NUMBER',
    'AUDIT_SPLIT' => 'TRAN_NUMBER',
 );
//print_r($tables);  


// Step 2: loop though each table
if (!empty($tables)) {
    foreach ($tables as $currentTable => $srcKeyname) {
      echo date("H:i:s") . " Table $currentTable ";
	  if ($debug>0) echo " with key $srcKeyname";
      $destKeyName=$srcKeyname;  
      $destTable=$currentTable;
      $fields=array(); $results=array();
                      
      // Step 2a: Connect to Sage and grab the source table data
      sage_getTable($currentTable, $debug, $fields, $results);
      //print_r($fields);   // to show the field structure  
      //print_r($results);    // to print out the data per record
      //print_r($results[0]);   // print first row
        

			// Step 2b: SQL to create the table, if it does not exist
        $sql = "CREATE TABLE IF NOT EXISTS " . $currentTable . " (\n";
        //$sql.= "  " . $mysql['idfield'] . " BIGINT(20) NOT NULL auto_increment,  ";
        foreach ($fields as $key => $value) {
            $sql.= "  " . mysql_real_escape_string($key) . " ";
            if ($value == "VARCHAR")
                $sql.= "VARCHAR(255)";
            elseif ($value == "INTEGER")
                $sql.= "BIGINT(20)";
            elseif ($value == "DATE")
                $sql.= "date";                
            else                       // TODO: other special type handling needed?
                $sql.=$value;
            $sql.= ", \n";
        }
        $sql.= "  PRIMARY KEY ($srcKeyname) \n"
          .") ENGINE=innoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;\n";
          // TODO: maybe some people want myisam or other encoding ?
        //print_r($sql);  
        $r = mysql_query($sql, $myconn);
        if (!$r)
            die("Error creating new table (" . $currentTable . ") on database (" . $mysql['dbname'] . ") : " . mysql_error());
        //echo "Created table if needed.\n";

      // Step 2c: Since this script is intented to migrate a database for the first time
      // as well as update existing data each time it is run, test each
      // row to see if it already exists before updating. 
      //
      // interate for each row.
        $update = FALSE;
        for ($i = 0; $i <= sizeof($results); $i++) {
            $srcKey=$results[$i][$srcKeyname];   // value of the key for this record
            if (strlen($srcKey)<1)
              continue; // ignore empty keys

            // Does the source row exist in the target?	    
          	$sql = "SELECT $destKeyName FROM $destTable WHERE $destKeyName = '" . $srcKey . "';";
            //print_r($sql);
            $r = mysql_query($sql, $myconn);
            if (!$r)
              echo "Error finding data on row " . $destKey . ": " . mysql_error() . " in $destTable\n";
            //echo "count=" . mysql_num_rows($r) . " $sql \n";
            if (mysql_num_rows($r) > 0) {
              $sqlb= "UPDATE $destTable SET ";
              $where= "WHERE $destKeyName='" . $srcKey . "' LIMIT 1;";
              $msg='updated';
            } 
            else {
              $sqlb= "INSERT INTO $destTable SET ";
              $where='';
              $msg='created';
            }
         
          // Step 2d: Run through all the field values
            foreach ($fields as $key => $value) {
              $data=mysql_real_escape_string($results[$i][$key]) ;
              //echo "key=$key data=$data  value=$value\n";
              if ( strlen($data)>0 )   // dont write empty fields
                //  field_name = value
                $sqlb.= mysql_real_escape_string($key) . " = '" . $data . "', ";
            }
            $sqlb = rtrim($sqlb, " \n\t\r,");
            $sqlb.= " " . $where;
            //print_r($sqlb);
            $r = mysql_query($sqlb, $myconn);
            if (!$r)
              echo "Error row $i: " . $results[$i][$srcKeyname] . ": ". mysql_error() . "\n";
            if (mysql_affected_rows($myconn)>0)
              echo "$msg $srcKey, ";
            //else 
            //  echo "no change $srcKey";   // record updated but no fields changed
            //echo "\n";
             
        }

        echo " ...completed sync of $currentTable at \n";
    }
	echo date("H:i:s"); 
}

?>
