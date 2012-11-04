<?

/** sage1.php
 *
 * Script to explore Sage tables via ODBC, print out the list of tables, fields, 
 * and data.
 * Look at the sources, and enable the various print statement to see more and
 * more detail.
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

$odbc['dsn'] = "SageLine50v19";
$odbc['user'] = "manager";
$odbc['pass'] = "";


// Step 1: Connect to the source ODBC database
if ($debug) echo "Connect to " . $odbc['dsn'] . ' as ' . $odbc['user'] . "\n";
$conn = odbc_connect($odbc['dsn'], $odbc['user'], $odbc['pass']);
if (!$conn) {
    die("Error connecting to the ODBC database: " . odbc_errormsg());
}

// loop through each table 
$allTables = odbc_tables($conn);
$tablesArray = array();
while (odbc_fetch_row($allTables)) {
    if (odbc_result($allTables, "TABLE_TYPE") == "TABLE") {
        $tablesArray[] = odbc_result($allTables, "TABLE_NAME");
    }
}
//print_r($tablesArray);      // to list all tables


if (!empty($tablesArray)) {  // loop though each table
    foreach ($tablesArray as $currentTable) {
      echo "Table " . $currentTable;
    	
      // get first, or all entries in the table.
        if ($debug) echo ($currentTable);
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
        //print_r($results[1]);   // print first row

    }
}
echo ".\n";
?>