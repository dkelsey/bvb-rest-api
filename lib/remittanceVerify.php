<?php

function remittanceVerify($filename) {
     $headerTemplate = array( 
        'Payee Name:     ',
        'Payee ID:       ',
        'Payee Site:     ',
        'Payment Number: ',
        'Payment Date:   '
     );
     
     $headerTemplate2 = array( 
        'Supplier: ',
        'Supplier Number: ',
        'Payment Number: '
     );
     
     $dataHeaderTemplate = array(
        'Invoice Number',
        'Order Type',
        'Order Number',
        'Store',
        'Bill of Lading',
        'Transaction Date',
        'Batch Date',
        'SKU',
        'Product',
        'UPC',
        'Size',
        'Supplier ID',
        'Supplier Name',
        'Reason',
        'Reference',
        'Quantity',
        'UOM',
        'Cost',
        'GST',
        'Container Deposit',
        'Freight Allowance',
        'Total'
     );
     
     $pattern = "/^,+$/";
     
     $fh = fopen($filename, 'r');
     if ($fh) {
          $header = array();
          $line = trim(fgets($fh));
          $line = str_replace("\"", "", $line);
          preg_match($pattern, $line, $m) ;
          while (strlen($line) && 0 == sizeof($m)) {
	       $tmp = str_getcsv($line);
               $header[] = $tmp[0];
               $line = trim(fgets($fh));
               $line = str_replace("\"", "", $line);
               preg_match($pattern, $line, $m) ;
          }
          # determine the header type - there are two different header types for remittance reports.
          if ( sizeof($headerTemplate) == sizeof($header) ) {
               $diff = array_diff($header, $headerTemplate) ;
               if (sizeof($diff)) 
                    return array( 'header_error' => array( 'filename' => basename($filename), 'unknown_colums' => $diff) );
          } else if ( sizeof($headerTemplate2) == sizeof($header) ) {
               $diff = array_diff($header, $headerTemplate2 ) ;
               if (sizeof($diff)) 
                    return array( 'header_error' => array( 'filename' => basename($filename), 'unknown_colums' => $diff) );
          } else {
               # need to do this better as it will be a return value from a REST call
               $result = array_diff($header, $headerTemplate ) ;
               //return $result ;
               return array ( 'header_error' => 'file hader has wrong number of headers.',
                              'filename' => $filename,
                              'header' => $header,
                              'unknown_column' => $result,
                      ) ;
          }
          $remittance_report = file($filename);
          if ( sizeof($headerTemplate) == sizeof($header) ) {
	       $data_header = str_getcsv(trim($remittance_report[6]));
               if ( sizeof($dataHeaderTemplate) < sizeof($data_header) ) {
                    $diff = array_diff($data_header, $dataHeaderTemplate ) ;
                    if (sizeof($diff)) 
                         return array( 'data_header_error' => array( 'filename' => basename($filename), 'unknown_colums' => $diff) );
               } else {
                    return array( 'format_valid' => 'header and data header are fine' ) ;
               }
          } else if ( sizeof($headerTemplate2) == sizeof($header) ) {
	       $data_header = str_getcsv(trim($remittance_report[4]));
               if ( sizeof($dataHeaderTemplate) < sizeof($data_header) ) {
                    $diff = array_diff($data_header, $dataHeaderTemplate ) ;
                    if (sizeof($diff)) 
                         return array( 'data_header_error' => array( 'filename' => basename($filename), 'unknown_colums' => $diff) );
               } else {
                    return array ( 'format_valid' => 'header and data header are fine' );
               }
          }
          fclose($fh);
     } else {
          return array ( 'fopen_error' => "there was an error opening $filename" ) ;
     }
}
?>
