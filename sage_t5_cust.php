<?
/** sage_t5_cust.php
 *
 * Read the customer table from sage and update a Take5 customer
 * table in mysql
 *
 * Records are keys by $srcKeyname, if a record does not exist in the target
 * it is created. The destinaton table must exist already.
 * Only certain columns are updated, and the column names are
 * different in each table.
 *
 * Author: Sean Boran on github. License GPL.
 *
 * Requirements: 
 * A Windows box with Sage, Sage ODBC and PHP with mysql libraries.
 * Sage DSN must be points to actual data (e.g. the demo company).
 * A mysql table called asalmas, with he Take5 Acounting structure.
 * tested with php 5.3.18 and Sage 50 accounts 2013.
 */
 
// configuration 
$currentTable='SALES_LEDGER';
$srcKeyname='ACCOUNT_REF';
$odbc['dsn'] = "SageLine50v19";
$odbc['user'] = "manager";
$odbc['pass'] = "";

$destTable='asalmas';
$destKeyName='ACCOUNT';
$mysql['host'] = "localhost";
$mysql['user'] = "root";
$mysql['pass'] = "";
$mysql['dbname'] = "boranpla";


// Connect to the source ODBC and target mysql database
if ($debug) echo "Connect to " . $odbc['dsn'] . ' as ' . $odbc['user'] . "\n";
$conn = odbc_connect($odbc['dsn'], $odbc['user'], $odbc['pass']);
if (!$conn) {
    die("Error connecting to the ODBC database: " . odbc_errormsg());
}

$myconn = mysql_connect($mysql['host'], $mysql['user'], $mysql['pass']);
if (!$myconn)
  die("Error connecting to the MySQL database: " . $mysql_error());
if (!mysql_select_db($mysql['dbname'], $myconn))
  die("Error selecting the database: " . $mysql_error());
  
// 1. Get the source structure and data
  echo ($currentTable);
  // get first, or all entries in the table.
    $sql = "SELECT * FROM " . $currentTable;   // TODO: grab all records
    //$sql = "SELECT TOP 1 * FROM " . $currentTable;  // grab just first record
    $r = odbc_exec($conn, $sql);
    if (!$r) {
      die("Error: " . $currentTable . "\n" . odbc_errormsg());
    }
        
    // how many fields and rows has the table?
    $maxfields=odbc_num_fields($r);
    echo ", fields=". $maxfields . ", rows=" . odbc_num_rows($r) . "\n"; 
    if (odbc_num_rows($r)==0)   // if no rows, dont go looking for data _or_ fields
      continue;   

    // Get all the field names and store in an array $fields
    if ($maxfields>3) {
      $maxfields=$maxfields-3;   // TODO: why this is needed, php crashes otherise
    }  
    $ignores=array('DATE_ACCOUNT_OPENED',);  // TODO: if there are fields not to show
    for ($i = 1; $i <= $maxfields; $i++) {
      //echo "\nfield " . $i . odbc_field_name($r, $i) . " type=" . odbc_field_type($r, $i);
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
     

// 2. Loop through the data and update the target        
  for ($i = 0; $i <= sizeof($results) -1; $i++) {
  	if ( $results[$i]['RECORD_DELETED'] > 0 )  // ignore these
  	  continue;  	  
    $srcKey=$results[$i][$srcKeyname];
 	
    // Does the source row exist n the arget?	    
  	$sql = "SELECT $destKeyName FROM $destTable WHERE $destKeyName = '" . $srcKey . "';";
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
      $sqlb= "INSERT INTO $destTable SET $destKeyName='"  . $srcKey . "', ";
      $where='';
      $msg='created';
    }
  	
  	//  Field mapping. 
  	//  TODO:  REGION REP CURRENCY num > 3 char ONHOLD
  	//  chop destination size if too big
    //  <Take5 field name>                                       <sage>
    $sqlb.= 
      "NAME='"      .substr(mysql_real_escape_string($results[$i]['NAME']),0,30)      ."', "
      ."ADDRESS1='" .substr(mysql_real_escape_string($results[$i]['ADDRESS_1']),0,30) ."', "
      ."ADDRESS2='" .substr(mysql_real_escape_string($results[$i]['ADDRESS_2']),0,30) ."', "
      ."ADDRESS3='" .substr(mysql_real_escape_string($results[$i]['ADDRESS_3']),0,30) ."', "
      ."ADDRESS4='" .substr(mysql_real_escape_string($results[$i]['ADDRESS_4']),0,30) ."', "
      ."ADDRESS5='" .substr(mysql_real_escape_string($results[$i]['ADDRESS_5']),0,30) ."', "
      ."EMAIL='"    .substr(mysql_real_escape_string($results[$i]['E_MAIL']),0,60) ."', "
      ."PHONE='"    .substr(mysql_real_escape_string($results[$i]['TELEPHONE']),0,20) ."', "
      ."FAX='"      .substr(mysql_real_escape_string($results[$i]['FAX']),0,20) ."', "                  
      ."CONTACT1='" .substr(mysql_real_escape_string($results[$i]['CONTACT_NAME']),0,20) ."', "
      ."CONTACT2='" .substr(mysql_real_escape_string($results[$i]['TRADE_CONTACT']),0,20) ."', "
      ."EMAIL1='"   .substr(mysql_real_escape_string($results[$i]['E_MAIL2']),0,60) ."', "
      ."EMAIL2='"   .substr(mysql_real_escape_string($results[$i]['E_MAIL3']),0,60) ."', "
      ."VATREG='"   .substr(mysql_real_escape_string($results[$i]['VAT_REG_NUMBER']),0,18) ."', "
      ."WEB='"      .substr(mysql_real_escape_string($results[$i]['WEB_ADDRESS']),0,120) ."', "
      ."LASTINVO='" .$results[$i]['LAST_INV_DATE'] ."' ";
                  
                  
     //echo "$sqlb \n";
     $r = mysql_query($sqlb, $myconn);
     if (!$r)
       echo "Error row $i: " . $results[$i][$srcKeyname] . ": ". mysql_error() . "\n";
       
     if (mysql_affected_rows($myconn)>0)
       echo "$msg $srcKey";
     else 
       echo "ignored $srcKey";   // TODO: so insert a new record!
     echo "\n";
     
   }
   

?>