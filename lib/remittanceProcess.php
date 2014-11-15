<?php

function remittanceProcess($filename) {
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

     $headerTitle2DBColumnName = array(
        'Payee Name:     ' => 'payeename',
        'Payee ID:       ' => 'payeeid',
        'Payee Site:     ' => 'payeesite',
        'Payment Number: ' => 'paymentnumber',
        'Payment Date:   ' => 'paymentdate'
      );
      $dataHeader2DBColumnName = array(
        'Invoice Number'   => 'invoiceNumber',
        'Order Type'       => 'orderType',
        'Order Number'     => 'orderNumber',
        'Store'            => 'store',
        'Store Number'     => 'store',
        'Bill of Lading'   => 'billOfLading',
        'Transaction Date' => 'transactionDate',
        'Batch Date'       => 'batchDate',
        'SKU'              => 'SKU',
        'Product'          => 'product',
        'Product Description' => 'product',
        'UPC'              => 'UPC',
        'Size'             => 'size',
        'Pack'             => 'size',
        'Supplier ID'      => 'supplierID',
        'Supplier Name'    => 'supplierName',
        'Reason'           => 'reason',
        'Reference'        => 'reference',
        'Quantity'         => 'quantity',
        'UOM'              => 'UOM',
        'Cost'             => 'cost',
        'Item Cost'        => 'cost',
        'GST'              => 'GST',
        'Container Deposit'=> 'containerDeposit',
        'Freight Allowance'=> 'freightAllowance',
        'Total'            => 'total',
      );
      $header2Title2DBColumnName = array(
        'Supplier: '       => 'payeename',
        'Supplier Number: '=> 'payeeid',
        'Payment Number: ' => 'paymentnumber'
      );
     
// DB Stuff
include_once( dirname( __FILE__ ) . '/bvbdb.php' );
$myDB = new wpdb(BVB_DB_USER, BVB_DB_PASSWORD, BVB_DB_NAME, BVB_DB_HOST);

     $pattern = "/^,+$/";

     $fh = fopen($filename, 'r');
     if ($fh) {
          $header = array();
          $headerData = array();
          $line = trim(fgets($fh));
          $line = str_replace("\"", "", $line);
          preg_match($pattern, $line, $m) ;
          while (strlen($line) && 0 == sizeof($m)) {
	       $tmp = str_getcsv($line);
               $header[] = $tmp[0];
               $headerData[] = $tmp[1];
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
                    // process the file
                    // insert the header data
		    $header_data = array( 'paymentremittanceid' => NULL,
		                          'filename' => basename($filename)
					);

		    // load $header_data with values
		    for ($i=0; $i < sizeof($header); $i++) {
		       $header_data[ $headerTitle2DBColumnName[ $header[$i] ] ] = trim( $headerData[$i] );
		    }
                    // fixup Transaction_dates:
                    $header_data['paymentdate'] = date('Y-m-d', strtotime($header_data['paymentdate'] )) ;
		   
		    $res = $myDB->insert(
		         'paymentremittance',
			 $header_data
		    );

		    if (!$res) {
		         return array( 'message' => sprintf("failure. failed to add remittancereport record for goo %s", basename($filename)) );
	            }
		    $paymentremittanceid = $myDB->insert_id ;

		    // load data now
		    $data_data = array('paymentremittanceid' => $paymentremittanceid,
				       'reason'              => 'N/A',
				       'reference'           => 'N/A'
				      );


		    for ($i=7; $i < sizeof($remittance_report); $i++) {
		         // load data now
		         $data_data = array('paymentremittanceid' => $paymentremittanceid,
				            'reason'              => 'N/A',
				            'reference'           => 'N/A'
				           );

		         $line = $remittance_report[$i];
			 $line = trim($line);
			 $line = str_replace('"','',$line);
			 if ( 80 < strlen($line) ) {

			    $line_data = str_getcsv($line);
			    for ($n=0; $n<sizeof($data_header); $n++) {
			         $data_data[ $dataHeader2DBColumnName[ $data_header[$n] ] ] =  trim( $line_data[$n] ) ;
			    }
			    // fixup bad date columns

			    // perform inserts
		            $res = $myDB->insert(
        		         'paymentremittancedata',
        			 $data_data
        		    );

        		    if (!$res) {
        		         return array( 'message' => sprintf("failure. failed to add remittancereport data record for %s", basename($filename)) );
        	            }
			 }
		    }

                    // move the file
                    if( rename( $filename, ARCHIVES_DIR . basename($filename)) )
                         // return that the file was processed successfully
                         return array( 'message' => sprintf("Success. %s was processed", basename($filename)) );
                    else
                         return array( 'message' => sprintf("move of %s to archive dir failed", basename($filename)) );
                    //return array( 'format_valid' => sprintf("Header for %s is valid", basename($filename)) );
               }
          } else if ( sizeof($headerTemplate2) == sizeof($header) ) {
     
	       $data_header = str_getcsv( trim($remittance_report[4]) );
               if ( sizeof($dataHeaderTemplate) < sizeof($data_header) ) {
                    $diff = array_diff($data_header, $dataHeaderTemplate ) ;
                    if (sizeof($diff)) 
                         return array( 'data_header_error' => array( 'filename' => basename($filename), 'unknown_colums' => $diff) );
               } else {
                    //return array ( 'format_valid' => 'header and data header are fine' );
                    // process the file
                    // insert the header data
		    $header_data = array( 'paymentremittanceid' => NULL,
                                           'filename'           => basename($filename),
					   'payeesite'          => '',
					   'paymentdate'        => date('Y-m-d', strtotime('2001-01-01'))
					);

		    // load $header_data with values
		    for ($i=0; $i < sizeof($header); $i++) {
		       $header_data[ $header2Title2DBColumnName[ $header[$i] ] ] = trim( $headerData[$i] );
		    }
                    // fixup Transaction_dates:
                    //$header_data['paymentdate'] = date('Y-m-d', strtotime($header_data['paymentdate'] )) ;
		   
		    $res = $myDB->insert(
		         'paymentremittance',
			 $header_data
		    );

		    if (!$res) {
		         return array( 'message' => sprintf("failure. boo failed to add remittancereport record for %s", basename($filename)) );
	            }
		    $paymentremittanceid = $myDB->insert_id ;

		    // load data now
		    $data_data = array('paymentremittanceid' => $paymentremittanceid,
		                        'supplierID'         => 0,
					'supplierName'       => 'N/A',
					'reason'             => 'N/A',
					'reference'          => 'N/A',
					'freightAllowance'   => 'N/A'
				      );


		    for ($i=5; $i < sizeof($remittance_report); $i++) {
		         // load data now
		         $data_data = array('paymentremittanceid' => $paymentremittanceid,
		                            'supplierID'         => 0,
			          	    'supplierName'       => 'N/A',
				            'reason'             => 'N/A',
					    'reference'          => 'N/A',
					    'freightAllowance'   => 'N/A',
					    'batchDate'          => NULL
				           );

		         $line = $remittance_report[$i];
			 $line = trim($line);
			 $line = str_replace('"','',$line);
			 if ( 80 < strlen($line) ) {

			    $line_data = str_getcsv($line);
			    for ($n=0; $n<sizeof($data_header); $n++) {
			         $data_data[ $dataHeader2DBColumnName[ $data_header[$n] ] ] =  trim( $line_data[$n] ) ;
			    }
			    // fixup bad date columns

			    // perform inserts
		            $res = $myDB->insert(
        		         'paymentremittancedata',
        			 $data_data
        		    );

        		    if (!$res) {
        		         return array( 'message' => sprintf("failure. failed to add remittancereport record for %s", basename($filename)) );
        	            }
			 }
		    }

                    // move the file
                    if( rename( $filename, ARCHIVES_DIR . basename($filename)) )
                         // return that the file was processed successfully
                         return array( 'message' => sprintf("Success. %s was processed", basename($filename)) );
                    else
                         return array( 'message' => sprintf("move of %s to archive dir failed", basename($filename)) );
                    //return array( 'format_valid' => sprintf("Header for %s is valid", basename($filename)) );
               }
          }
          fclose($fh);
     } else {
          return array ( 'message' => "there was an error opening $filename" ) ;
     }
// */
}
?>
