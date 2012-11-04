<?
/** sage_t5_cust.php
 *
 * Read the customer table from sage and update a Take5 customer
 * table in mysql
 *
 * Author: Sean Boran on github. License GPL.
 *
 * Requirements: 
 * A Windows box with Sage, Sage ODBC and PHP with mysql libraries.
 * Sage DSN must be points to actual data (e.g. the demo company).
 * A mysql table called asalmas, with he Take5 Acounting structure.
 * tested with php 5.3.18 and Sage 50 accounts 2013.
 */
 
$odbc['dsn'] = "SageLine50v19";
$odbc['user'] = "manager";
$odbc['pass'] = "";

$mysql['host'] = "localhost";
$mysql['user'] = "root";
$mysql['pass'] = "";
$mysql['dbname'] = "boranpla";

// Step 1: Connect to the source ODBC and target mysql database
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
  
  
$currentTable='SALES_LEDGER';
$destTable='asalmas';
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
     
        
  for ($i = 1; $i <= sizeof($results); $i++) {
  	if ( $results[$i - 1]['RECORD_DELETED'] > 0 )
  	  continue;
    //  take5                        sage
                  $data=mysql_real_escape_string($results[$i - 1][$key]) ;

    $sqlb= "UPDATE " . $destTable . " SET "
      ."NAME='"     .mysql_real_escape_string($results[$i - 1]['NAME'])      ."', "
      ."ADDRESS1='" .mysql_real_escape_string($results[$i - 1]['ADDRESS_1']) ."', "
      ."ADDRESS2='" .mysql_real_escape_string($results[$i - 1]['ADDRESS_2']) ."', "
      ."ADDRESS3='" .mysql_real_escape_string($results[$i - 1]['ADDRESS_3']) ."', "
      ."ADDRESS4='" .mysql_real_escape_string($results[$i - 1]['ADDRESS_4']) ."', "
      ."ADDRESS5='" .mysql_real_escape_string($results[$i - 1]['ADDRESS_5']) ."', "
      ."EMAIL='"    .mysql_real_escape_string($results[$i - 1]['E_MAIL']) ."', "
      ."PHONE='"    .mysql_real_escape_string($results[$i - 1]['TELEPHONE']) ."', "
      ."FAX='"      .mysql_real_escape_string($results[$i - 1]['FAX']) ."', "                  
      ."CONTACT1='" .mysql_real_escape_string($results[$i - 1]['CONTACT_NAME']) ."', "
      ."CONTACT2='" .mysql_real_escape_string($results[$i - 1]['TRADE_CONTACT']) ."', "
      ."EMAIL1='"   .mysql_real_escape_string($results[$i - 1]['E_MAIL2']) ."', "
      ."EMAIL2='"   .mysql_real_escape_string($results[$i - 1]['E_MAIL3']) ."', "
      ."VATREG='"   .mysql_real_escape_string($results[$i - 1]['VAT_REG_NUMBER']) ."', "
      ."WEB='"      .mysql_real_escape_string($results[$i - 1]['WEB_ADDRESS']) ."', "
      ."LASTINVO='" .$results[$i - 1]['LAST_INV_DATE'] ."' "
                  
      /* REGION REP CURRENCY num > 3 char ONHOLD
*/
      ." WHERE ACCOUNT='" . $results[$i - 1]['ACCOUNT_REF']      ."' LIMIT 1";
     //echo "$sqlb \n";
     $r = mysql_query($sqlb, $myconn);
     if (!$r)
       echo "Error updating data on row: " . $i . ": " 
        . $results[$i - 1]['ACCOUNT_REF'] . "- ". mysql_error() . "\n";
     echo $results[$i - 1]['ACCOUNT_REF'];
     if (mysql_affected_rows($myconn)>0)
       echo " updated";
     else 
       echo " not found ";   // TODO: so insert a new record!
     echo "\n";
     
   }
   

?>