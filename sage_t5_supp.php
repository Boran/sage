<?
/** sage_t5_supp.php
 *
 * Read the Supplier table from sage and update a Take5 table in mysql
 *
 * Records are keyed by $srcKeyname, if a record does not exist in the target
 * it is created. The destination table must exist already.
 * Only *certain columns* are updated, and the column names are
 * different so they need to be mapped.
 * No records are deleted.
 *
 * Author: Sean Boran on github. License GPL.
 *
 * Requirements: 
 * A Windows box with Sage, Sage ODBC and PHP with mysql libraries.
 * Sage DSN must be points to actual data (e.g. the demo company).
 * A mysql table called asalmas, with he Take5 Acounting structure.
 * tested with php 5.3.18 and Sage 50 accounts 2013.
 */
 
require_once('config.inc');
require_once('funcs.inc');    // common functions 

// configuration 
$srcTable='PURCHASE_LEDGER';
$srcKeyname='ACCOUNT_REF';
$destTable='apurmas';
$destKeyName='ACCOUNT';


// Connect to Sage and grab the source table data
$fields=array(); $results=array();
$debug=0;
sage_getTable($srcTable, $debug, $fields, $results);
//print_r($fields);   // to show the field structure  
//print_r($results);    // to print out the data per record
//print_r($results[0]);   // print first row

// 1. Connect to he destination     
$myconn = mysql_connect($mysql['host'], $mysql['user'], $mysql['pass']);
if (!$myconn)
  die("Error connecting to the MySQL database: " . $mysql_error());
if (!mysql_select_db($mysql['dbname'], $myconn))
  die("Error selecting the database: " . $mysql_error());

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
      ."WEB='"      .substr(mysql_real_escape_string($results[$i]['WEB_ADDRESS']),0,120) ."' "
      //."LASTINVO='" .$results[$i]['LAST_INV_DATE'] ."' "
      . $where;         
                  
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