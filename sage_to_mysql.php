<?

// Welcome to SAGEtoMYSQL
// Below we start of with the basic configuration vairables. These are quite
// self explanatory so I will not go into too much detail here. Things to
// note here is that you must input a source table and destination table
// since this script will only move one table at a time. 'idfield' will
// be used as the primary key on the new table to identify each moved entry
// so that we know whether to update or insert later on.

/*
 * @updated adbe
 * @url d62.net/journal
 * @date 20/01/2011
 * @description
 * modified original script from http://rickvause.com/2009/07/10sagetomysql/
 * to pull all tables from the data source instead of specifying just one.
 */

$odbc['dsn'] = "SageLine50v19";
$odbc['user'] = "manager";
$odbc['pass'] = "";

$mysql['host'] = "localhost";
$mysql['user'] = "root";
$mysql['pass'] = "";
$mysql['dbname'] = "sagetest";
$mysql['idfield'] = "id";

$debug=true;

// Step 1: Connect to the source ODBC database
if ($debug) echo "Connect to " . $odbc['dsn'] . ' as ' . $odbc['user'] . "\n";
$conn = odbc_connect($odbc['dsn'], $odbc['user'], $odbc['pass']);
if (!$conn) {
    die("Error connecting to the ODBC database: " . odbc_errormsg());
}

// Step 1b: Connect to the destination MySQL Database.
/*$myconn = odbc_connect('sagemysql', '', '');
if (!$myconn) {
    die("Error connecting to mysql: " . odbc_errormsg());
}*/

/*try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=sagetest', 'root', '');
} catch (PDOException $e) {
    echo 'Connexion échouée : ' . $e->getMessage();
}*/
$myconn = mysql_connect($mysql['host'], $mysql['user'], $mysql['pass']);
if (!$myconn)
  die("Error connecting to the MySQL database: " . $mysql_error());
if (!mysql_select_db($mysql['dbname'], $myconn))
  die("Error selecting the database: " . $mysql_error());


// Step 1.5: loop through each table with steps 2-7
$allTables = odbc_tables($conn);
$tablesArray = array();
while (odbc_fetch_row($allTables)) {
    if (odbc_result($allTables, "TABLE_TYPE") == "TABLE") {
        $tablesArray[] = odbc_result($allTables, "TABLE_NAME");
    }
}

// TODO:
// COMPANY    AUDIT_SPLIT TAX_CODE CURRENCY 'PURCHASE_LEDGER'
// 'SALES_LEDGER' 
$tablesArray = array('PURCHASE_LEDGER' ,'NOMINAL_LEDGER');
//if ($debug) print_r($tablesArray);


if (!empty($tablesArray)) {
    foreach ($tablesArray as $currentTable) {
    	
// Step 2: Construct and execute a query to get all the entries in the
// chosen table.
        if ($debug) echo ($currentTable);
        $sql = "SELECT * FROM " . $currentTable;
        //$sql = "SELECT TOP 1 * FROM " . $currentTable;
        $r = odbc_exec($conn, $sql);
        if (!$r) {
            die("Error getting data from the table to be moved: " . $odbc['table'] . "\n" . odbc_errormsg());
        }

// Step 3: Get all the field names returned by the result and store them
// in an array $fields
        $maxfields=odbc_num_fields($r);
        echo ", numfields (take 3 less)=". $maxfields;        
        if ($maxfields>100) {
          //$maxfields=140;
          $maxfields=$maxfields-3;
        }  
        $ignores=array('DATE_ACCOUNT_OPENED','LETTERS_VIA_EMAIL');  // TODO
        for ($i = 1; $i <= $maxfields; $i++) {
            //echo "\nfield " . $i . odbc_field_name($r, $i) . " type=" . odbc_field_type($r, $i);
            if (! in_array(odbc_field_name($r, $i), $ignores)) 
              $fields[odbc_field_name($r, $i)] = odbc_field_type($r, $i);
            //else 
            //  echo "Ignore: " . odbc_field_name($r, $i);
        }   
        //if ($debug) print_r($fields);
        
// Step 4: Loop through each row in the returned results and store in an
// array $results
        $x = 0;
        while ($row = odbc_fetch_row($r)) {
            for ($i = 1; $i <= odbc_num_fields($r); $i++)
                if (! in_array(odbc_field_name($r, $i), $ignores)) 
                  $results[$x][odbc_field_name($r, $i)] = odbc_result($r, $i);
            $x++;
        }
        //print_r($results);

// Step 5-6: Build the Mysql query to make create the table and structure on the
// database. This will include an IF NOT EXISTS statement and will be run
// either way since it is only 1 query.
// In the structure of the table we are including a new column at the
// beginning of the table called id which will be the auto incrementing
// key. This column on the table is the how this script will decide to
// deal with updating/inserting data.

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
            else 
                $sql.=$value;
            $sql.= ", \n";
        }
        $sql = rtrim($sql, " \n\t\r");
        reset($fields);
        $sql.= "\nPRIMARY KEY (" . $mysql['idfield'] . ") )\nENGINE = innoDB\nDEFAULT CHARSET=latin1\nCOLLATE=latin1_general_ci;\n";
        $r = mysql_query($sql, $myconn);
        if (!$r)
            die("Error creating new table (" . $currentTable . ") on database (" . $mysql['dbname'] . ") : " . mysql_error());
        echo "Table OK\n";

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
                echo "Row " . $i . ": Updating\n";
                $update = TRUE;
                $sqlb.= "UPDATE " . $currentTable . " SET ";
            } else {
                echo "Row " . $i . ": Inserting\n";
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
              if ( strlen($data)>0 )   // dont write empty fields
                $sqlb.= mysql_real_escape_string($key) . " = '" . $data . "', ";
            }
            $sqlb = rtrim($sqlb, ", \n\r\t");
            if ($update) {
                $sqlb.= " WHERE " . $mysql['idfield'] . " = " . $i;            } 
            else {
            	  //echo $sqlb . "\n";
            }   
            $sqlb.= ";\n";
            //print_r($sqlb);
            $r = mysql_query($sqlb, $myconn);
            if (!$r)
                echo "Error inserting/updating data on row: " . $i . ": " . mysql_error() . "\n";
     
        }

        echo "Completed Import of table " . $currentTable . "\n";
    }
}
// Complete: And that is it, complete database translation in 7 steps!
?>