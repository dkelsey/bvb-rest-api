<?php

function batchProcess($filename) {

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
     $headerTitle2DBColumnName = array(
          'batchid'                 => 'batchid',
	  'Store_Number'            => 'store_number',
	  'Transaction_Type'        => 'transaction_type',
	  'Transaction_date'        => 'transaction_date',
          'Invoice_Reference_Number'=> 'invoice_reference_number',
	  'Original_Invoice_Number' => 'original_invoice_number',
	  'Customer_Number'         => 'customer_number',
	  'Customer_Type'           => 'customer_type',
	  'Customer_Name'           => 'customer_name',
	  'Customer_Phone_Number'   => 'customer_phone_Number',
	  'Customer_Address'        => 'customer_address',
	  'Customer_City'           => 'customer_city',
	  'Customer_Province'       => 'customer_province',
	  'Customer_Postal_Code'    => 'customer_postal_code',
	  'Payment_Method'          => 'payment_method',
	  'SKU'                     => 'SKU',
	  'Quantity'                => 'quantity',
	  'Display_Price'           => 'display_price',
	  'Container_Deposit'       => 'container_deposit',
	  'Total_Doc_Amount'        => 'total_doc_amount',
	  'Return_Reason_Code'      => 'return_reason_code',
	  'Pipeline Sales'          => 'pipeline_sales'
     );

// DB Stuff

include_once( dirname( __FILE__ ) . '/bvbdb.php' );
//$myDB = new wpdb('root','','barker_remittance','localhost');
$myDB = new wpdb(BVB_DB_USER, BVB_DB_PASSWORD, BVB_DB_NAME, BVB_DB_HOST);
          

     $fh = fopen($filename, 'r');
     if ( ! $fh) {
          return array( 'fopen_error' => "There was an error opening $filename" );
     } else {
          $line = trim(fgets($fh));
          $header = explode(",", $line);
          $result = array_diff($header, $headerTemplate);
          if (sizeof($result)) {
               return array( 'format_error' => array( 'filename' => basename($filename), 'differences' => $result) );
          } else {
               // process the file
               // insert into lbd_batches a new header record.
               $res = $myDB->insert(
	            'ldb_batches',
		    array(
		         'batchid' => NULL,
		         'file_name' => basename($filename),
			 'process_date' => current_time('mysql',1)
		    )
               );

	       if (!$res) {
                    return array( 'message' => sprintf("failue. failed to add ldb_batches record for %s", $filename) );
	       }
	       $batchid = $myDB->insert_id ;

               // insert data, row by row into ldb_batchesdata.
	       $val_array =  array(
	  	     'batchid'  => $batchid
	       );

	       while (($line = fgets($fh)) !== false) {
	            $line = trim($line);
                    $data = explode(",", $line);

		    for( $i=0; $i < sizeof($header); $i++) {
                         $val_array[ $headerTitle2DBColumnName[ $header[$i] ] ] = trim( $data[$i] );
		    }

                    // fixup Transaction_dates:
		    $val_array['transaction_date'] = date('Y-m-d', strtotime($val_array['transaction_date'] )) ; 
		    $val_array['pipeline_sales'] = is_null( $val_array['pipeline_sales'] ) ? '' : $val_array['pipeline_sales'] ; // ensure has some value
//		    return array('message' => $val_array['transaction_date'] );
//	       return array('message' => 'huh: ' . $headerTitle2DBColumnName[ $header[0] ] . ' => ' . $val_array[ $headerTitle2DBColumnName[ $header[0] ] ] . ' = ' . $data[0] );

                    $res = $myDB->insert(
	                 'ldb_batchesdata',
			 $val_array
                    );
	            if (!$res) {
                        return array( 'message' => sprintf("failue. failed to add ldb_batchesdata record for %s", basename($filename)) );
	            }

	       }

               // move the file
               if( rename( $filename, ARCHIVES_DIR . basename($filename)) )
                    // return that the file was processed successfully
                    return array( 'message' => "Success." );
               else
                    return array( 'message' => "move to archive dir failed" );
               //return array( 'format_valid' => sprintf("Header for %s is valid", basename($filename)) );

          }
          fclose($fh);
     }
}
?>
