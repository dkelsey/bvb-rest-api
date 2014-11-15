<?php

function batchVerify($filename) {

     $headerTemplate = array(
          'Store_Number',
          'Transaction_Type',
          'Transaction_date',
          'Invoice_Reference_Number',
          'Original_Invoice_Number',
          'Customer_Number',
          'Customer_Type',
          'Customer_Name',
          'Customer_Phone_Number',
          'Customer_Address',
          'Customer_City',
          'Customer_Province',
          'Customer_Postal_Code',
          'Payment_Method',
          'SKU',
          'Quantity',
          'Display_Price',
          'Container_Deposit',
          'Total_Doc_Amount',
          'Return_Reason_Code',
          'Pipeline Sales'
     );
          
     $fh = fopen($filename, 'r');
     if ($fh) {
          $line = trim(fgets($fh));
          $header = str_getcsv($line);
          $result = array_diff($header, $headerTemplate);
          if (sizeof($result)) {
               return array( 'format_error' => array( 'filename' => basename($filename), 'differences' => $result) );
          } else {
               return array( 'format_valid' => sprintf("Header for %s is valid", basename($filename)) );
          }
          fclose($fh);
     } else {
          return array( 'fopen_error' => "There was an error opening $filename" );
     }
}
?>
