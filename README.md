sage
====

PHP scripts for reading from the Sage 50 Accounting database via ODBC

The Sage line 50 is a (very) closed Accounting System, but it does have an ODBC interface where tables can be read (but not written).

The scripts are as follows:

  funcs.inc: Library of common functions.
  config.inc: Configuration file

  sage1.php: explore Sage tables via ODBC, print out the list of tables, 
             fields, and data.

  sage2.php: Dump all tables from sage ODBC and write them to a mysql database.
             This is a hacked/corrected found of an example found on the net.


  sage3.php: Sync specific tables from sage via ODBC to a mysql database.
             uses funcs.inc


Scripts for updating mysql Take5 accounts tables:

  sage_t5_cust.php: 
  Read the customer table from sage and update a Take5 customer table in mysql.

  sage_t5_supp.php.php: 
  Read the supplier table from sage and update a Take5 purmas table in mysql.
 
  sage_t5_nom.php.php: 
  Read the nominal table from sage and update a Take5 purmas table in mysql.
 

