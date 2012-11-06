<?

/** sage2.php
 *
 * Dump all tables from sage ODBC
 * and write them to a mysql database.
 * For a simpler example, see sage1.php
 *
 * Author: Sean Boran on github. License GPL.
 * Some code copied from SAGEtoMYSQL by adbe, d62.net/journal 20/01/2011
 * which is turn was based on http://rickvause.com/2009/07/10sagetomysql/
 *
 * Requirements: 
 * A Windows box with Sage, Sage ODBC and PHP with mysql libraries.
 * Sage DSN must be points to actual data (e.g. the demo company)
 * tested with php 5.3.18 and Sage 50 accounts 2013.
 */
 
/* the output might be something like this:

$ /c/php/php.exe sage2.php
Connect to SageLine50v19 as manager
Table SALES_LEDGERSALES_LEDGER, fields=143, rows=30
Table OK
Completed Import of table SALES_LEDGER
Table PURCHASE_LEDGERPURCHASE_LEDGER, fields=140, rows=15
Table OK
Completed Import of table PURCHASE_LEDGER
Table NOMINAL_LEDGERNOMINAL_LEDGER, fields=123, rows=152
Table OK
Completed Import of table NOMINAL_LEDGER
Table TAX_CODETAX_CODE, fields=10, rows=100
Table OK
Completed Import of table TAX_CODE
Table CURRENCYCURRENCY, fields=12, rows=100
Table OK
Completed Import of table CURRENCY
Table AUDIT_SPLITAUDIT_SPLIT, fields=64, rows=1234
Table OK
Completed Import of table AUDIT_SPLIT

*/ 


// this script will only move one table at a time. 'idfield' will
// be used as the primary key on the new table to identify each moved entry
// so that we know whether to update or insert later on.


$odbc['dsn'] = "SageLine50v19";
$odbc['user'] = "manager";
$odbc['pass'] = "";

$mysql['host'] = "localhost";
$mysql['user'] = "root";
$mysql['pass'] = "";
$mysql['dbname'] = "sagetest";
$mysql['idfield'] = "id";

$debug=true;

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
  die("Error selecting the database: " . mysql_error());


// Step 1.5: loop through each table with steps 2-7
$allTables = odbc_tables($conn);
$tablesArray = array();
while (odbc_fetch_row($allTables)) {
    if (odbc_result($allTables, "TABLE_TYPE") == "TABLE") {
        $tablesArray[] = odbc_result($allTables, "TABLE_NAME");
    }
}
//if ($debug) print_r($tablesArray);

// The above gives us all tables, which could be done one after the
// other. Usuall though one just needs 3-4 specific tables that
// have ben well tested.
// e.g.
$tablesArray = array('SALES_LEDGER', 'PURCHASE_LEDGER','NOMINAL_LEDGER', 'TAX_CODE',
  'CURRENCY','AUDIT_SPLIT');
//$tablesArray = array('COMPANY',);   // this table has isues
$tablesArray = array('SALES_LEDGER',);

if (!empty($tablesArray)) {
    foreach ($tablesArray as $currentTable) {
      echo "Table " . $currentTable;
	
      // get first, or all entries in the table.
        $sql = "SELECT * FROM " . $currentTable;   // TODO: grab all records
        //$sql = "SELECT TOP 1 * FROM " . $currentTable;  // grab just first record
        $r = odbc_exec($conn, $sql);
        if (!$r) {
            die("Error: " . $currentTable . "\n" . odbc_errormsg());
        }

        // how many fields and rows has the table?
        $maxfields=odbc_num_fields($r);
        echo ", fields=". $maxfields . ", rows=" . odbc_num_rows($r) ; 
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
        

// Step 5-6: Build the Mysql query to make create the table and structure on the
// database. This will include an IF NOT EXISTS statement and will be run
// either way since it is only 1 query.
// TODO: Alternativly one could drop tabels and recreate each time

// Indexing
// In the structure of the table we are including a new column at the
// beginning of the table called id which will be the auto incrementing
// key. This column on the table is the how this script will decide to
// deal with updating/inserting data.
// Now this is extending the sage tables, so its no longer a one to one copy
// this may not be ideal for all situations. Alternatively, one could hard
// code the key per sage table, e.g. ACCOUNT for SALES_LEDGER

				// SQL to create the table
        $sql = "CREATE TABLE IF NOT EXISTS " . $currentTable . " (\n";
        $sql.= "  " . $mysql['idfield'] . " BIGINT(20) NOT NULL auto_increment,  ";
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
        $sql = rtrim($sql, " \n\t\r");
        reset($fields);
        $sql.= "\nPRIMARY KEY (" . $mysql['idfield'] . 
          ") )\nENGINE = innoDB\nDEFAULT CHARSET=latin1\nCOLLATE=latin1_general_ci;\n";
          // TODO: maybe some people want myisam or other encodeing ?
        $r = mysql_query($sql, $myconn);
        if (!$r)
            die("Error creating new table (" . $currentTable . ") on database (" . $mysql['dbname'] . ") : " . mysql_error());
        echo ". OK\n";

        //print_r($sql);    exit;


// Step 7: Now that we have translated the structure across we have the
// basic layout ready for our data to be move in.
// Since this script is intented to migrate a database for the first time
// as well as update existing data each time it is run we will test each
// row to see if it already exists before updating. Then we will execute
// each query one by one.
// This is probably the most complicated part of the script so commenting
// will talk us through it.

// Step 7a: Set initial values and start the loop. $update is a flag used
// for deciding to add a WHERE clause on the end of each query.
// TODO: it depends in an ID field
        $update = FALSE;
        for ($i = 1; $i <= sizeof($results); $i++) {
            $sqlb = "";

            // Step 7b: Get the row from the database, if it already existed ie, more than
            // zero rows are returned then we will do an update query. If the row does not
            // already exist on the table then we will to an insert query instead.

            $sql = "SELECT " . $mysql['idfield'] . " FROM " . $currentTable 
               . " WHERE " . $mysql['idfield'] . " = " . $i . ";";
            $r = mysql_query($sql, $myconn);
            if (!$r)
                echo "Error finding data on row " . $id . ": " . mysql_error() . " in $currentTable\n";
            //echo "count=" . mysql_num_rows($r) . " $sql \n";
            
            if (mysql_num_rows($r) > 0) {
                //echo "Row " . $i . ": Updating\n";
                $update = TRUE;
                $sqlb.= "UPDATE " . $currentTable . " SET ";
            } else {
                //echo "Row " . $i . ": Inserting\n";
                $update = FALSE;
                //$sqlb.= "INSERT INTO " . $currentTable . " SET ";
                $sqlb.= "INSERT INTO " . $currentTable . " SET " 
                  . $mysql['idfield'] . "=" . $i . ",";
            }

            // Step 7c: Run through all the field values and thus create the query to be run
            // onto the table. Then we remove an commas and spaced at the end of the query
            // before adding the where clause if it is an update query and then ending the
            // query with a ;
            // Then we finally execute the query.

            foreach ($fields as $key => $value) {
              $data=mysql_real_escape_string($results[$i - 1][$key]) ;
              //echo "key=$key data=$data  value=$value\n";
              if ( strlen($data)>0 )   // dont write empty fields
                $sqlb.= mysql_real_escape_string($key) . " = '" . $data . "', ";
            }
            $sqlb = rtrim($sqlb, ", \n\r\t");
            if ($update) {
                $sqlb.= " WHERE " . $mysql['idfield'] . " = " . $i;            
            } 
            else {
            	  //echo $sqlb . "\n";
            }   
            $sqlb.= ";\n";
            //print_r($sqlb);
            $r = mysql_query($sqlb, $myconn);
            if (!$r)
                echo "Error inserting/updating data on row: " . $i . ": " . mysql_error() . "\n";
     
        }

        echo "Completed Import of " . $currentTable;
    }
}

?>
