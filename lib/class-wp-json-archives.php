<?php

class WP_JSON_Archives extends WP_JSON_Posts {
	/**
	 * Server object
	 *
	 * @var WP_JSON_ResponseHandler
	 */
	protected $server;

	/**
	 * Constructor
	 *
	 * @param WP_JSON_ResponseHandler $server Server object
	 */
	public function __construct( WP_JSON_ResponseHandler $server ) {
		$this->server = $server;
	}
	
	private function formatBytes($size, $precision = 2) {
	        $base = log($size) / log(1024);
		$suffixes = array('', 'k', 'M', 'G', 'T');

		return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
	}
	/**
	 * Register the user-related routes
	 *
	 * @param array $routes Existing routes
	 * @return array Modified routes
	 */
	public function register_routes( $routes ) {
		$user_routes = array(
			// User endpoints
			'/archives' => array(
				array( array( $this, 'get_archives' ),       WP_JSON_Server::READABLE ),
			),
			'/archives/zip/(?P<filename>.+(?i:\.csv(?i:\.gz)?))' => array(
				array( array( $this, 'zip_archive' ),       WP_JSON_Server::EDITABLE ),
			),
			'/archives/unarchive/(?P<filename>.+(?i:\.csv))' => array(
				array( array( $this, 'unarchive_archive' ),       WP_JSON_Server::EDITABLE ),
			),
			'/archives/(?P<filename>.+(?i:\.csv(?:(?:\.)gz))?)' => array(
				array( array( $this, 'get_archive' ),       WP_JSON_Server::READABLE ),
				array( array( $this, 'delete_archive' ),      WP_JSON_Server::DELETABLE ),
			),
		);
		return array_merge( $routes, $user_routes );
	}

	/**
	 * Retrieve archives.
	 *
	 * @param array $filter Extra query parameters for {@see WP_User_Query}
	 * @param string $context optional
	 * @param int $page Page number (1-indexed)
	 * @return array contains a collection of User entities.
	 */
	public function get_archives( $filter = array(), $context = 'view', $page = 1 ) {

               $files = array_diff(scandir( ARCHIVES_DIR ),array('..','.'));

               $fileData = array();
               foreach ($files as &$file) {
                 $fileSize = filesize(ARCHIVES_DIR . $file);
                 $fileSize = $this->formatBytes($fileSize, 1);
                 $fileCTime = filectime(ARCHIVES_DIR.$file);
                 $fileCTime = date('M d, Y', $fileCTime);
                 $fileData[] = array( $file => array('filesize' => $fileSize, 'filectime' => $fileCTime,));
               }

               return $fileData;
	}

        /**
         * Upload a new attachment
         *
         * Creating a new attachment is done in two steps: uploading the data, then
         * setting the post. This is achieved by first creating an attachment, then
         * editing the post data for it.
         *
         * @param array $_files Data from $_FILES
         * @param array $_headers HTTP headers from the request
         * @return array|WP_Error Attachment data or error
         */
        //public function upload_attachment( $_files, $_headers ) {
        public function upload_csv( $_files, $_headers ) {
                $post_type = get_post_type_object( 'attachment' );

                if ( ! $post_type ) {
                        return new WP_Error( 'json_invalid_post_type', __( 'Invalid post type' ), array( 'status' => 400 ) );
                }

                // Permissions check - Note: "upload_files" cap is returned for an attachment by $post_type->cap->create_posts
                if ( ! current_user_can( $post_type->cap->create_posts ) || ! current_user_can( $post_type->cap->edit_posts ) ) {
                        return new WP_Error( 'json_cannot_create', __( 'Sorry, you are not allowed to post on this site.'. $post_type->cap->edit_posts ), array( 'status' => 400 ) );
                }

                if ( ! move_uploaded_file($_files['file']['tmp_name'], ARCHIVES_DIR . $_files['file']['name']) ) {
                        return new WP_Error( 'move_uploaded_file_fail', __( 'move_uploaded_file faild: '. $_files['file']['tmp_name']  ), array( 'status' => 400 ) );
		}

                return new WP_JSON_Response( $_files, 201, $_headers );
        }

        /**
         * Handle an upload via multipart/form-data ($_FILES)
         *
         * @param array $_files Data from $_FILES
         * @param array $_headers HTTP headers from the request
         * @return array|WP_Error Data from {@see wp_handle_upload()}
         */
        protected function upload_from_file( $_files, $_headers ) {
                if ( empty( $_files['file'] ) )
                        return new WP_Error( 'json_upload_no_data', __( 'No data supplied' ), array( 'status' => 400 ) );

                // Verify hash, if given
                if ( ! empty( $_headers['CONTENT_MD5'] ) ) {
                        $expected = trim( $_headers['CONTENT_MD5'] );
                        $actual = md5_file( $_files['file']['tmp_name'] );
                        if ( $expected !== $actual ) {
                                return new WP_Error( 'json_upload_hash_mismatch', __( 'Content hash did not match expected' ), array( 'status' => 412 ) );
                        }
                }

                // Pass off to WP to handle the actual upload
                $overrides = array(
                        'test_form' => false,
                );

                $file = wp_handle_upload( $_files['file'], $overrides );

                if ( isset( $file['error'] ) ) {
                        return new WP_Error( 'json_upload_unknown_error', $file['error'], array( 'status' => 500 ) );
                }

                return $file;
        }


	/**
	 * Retrieve the current user
	 *
	 * @param string $context
	 * @return mixed See
	 */
	public function get_current_user( $context = 'view' ) {
		$current_user_id = get_current_user_id();

		if ( empty( $current_user_id ) ) {
			return new WP_Error( 'json_not_logged_in', __( 'You are not currently logged in.' ), array( 'status' => 401 ) );
		}

		$response = $this->get_user( $current_user_id, $context );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! ( $response instanceof WP_JSON_ResponseInterface ) ) {
			$response = new WP_JSON_Response( $response );
		}

		$data = $response->get_data();

		$response->header( 'Location', $data['meta']['links']['self'] );
		$response->set_status( 302 );

		return $response;
	}

	/**
	 * Retrieve an uploaded file's info .
	 *
	 * @param int $id User ID
	 * @param string $context
	 * @return response
	 */
	public function get_archive( $filename, $context = 'view' ) {
	        return $this->getFileInfo($filename) ;
	}

	private function getFileInfo($file_name) {
	        $fileSize = filesize(ARCHIVES_DIR . $file_name);
	        $fileSize = $this->formatBytes($fileSize, 1);
	        $fileCTime = filectime(ARCHIVES_DIR . $file_name);
	        $fileCTime = date('M d, Y', $fileCTime);

                return array( $file_name => array ('filesize' => $fileSize, 'filectime' => $fileCTime,));
	}

	/**
	 * Zip or UnZip an archived file.
	 *
	 * @param int $id User ID
	 * @param string $context
	 * @return response
	 */
	public function zip_archive( $filename, $context = 'view' ) {

             if ( preg_match('/.*gz$/i', $filename) ){
	          //unzip file
		  $buffer_size = 4096;
		  $out_file_name = str_replace('.gz', '', $filename);

		  $gz_file =  gzopen(ARCHIVES_DIR . $filename, 'rb');

		  if (! $gz_file )
		    return array('message' => 'Error: could not open gz file');

		  $out_file = fopen(ARCHIVES_DIR . $out_file_name, 'wb');

		  if (! $out_file)
		       return array('message' => 'Error: could not open output file');

		  while(!gzeof($gz_file)) {
		       fwrite($out_file, gzread($gz_file, $buffer_size));
		  }

		  fclose($out_file);
		  gzclose($gz_file);

		  unlink(ARCHIVES_DIR . $filename);

		  return array( 'message' => 'unzip succeeded');

	     } else {
	          // zip the file
	          $gzfile = $filename . ".gz";
	          $fp = gzopen (ARCHIVES_DIR . $gzfile, 'w9');
	          gzwrite ($fp, file_get_contents( ARCHIVES_DIR . $filename));
	          gzclose($fp);
	          unlink(ARCHIVES_DIR . $filename) ;

                  return array('message' => 'File has been zipped') ;
	     }
	}

	/**
	 * move archived file to uploads folder. it must be uncompressed.
	 *
	 * @param int $id User ID
	 * @param string $context
	 * @return response
	 */
	public function unarchive_archive( $filename, $context = 'view' ) {
               // move the file
               if( rename( ARCHIVES_DIR . $filename, UPLOADS_DIR . basename($filename)) ) 
                    // return that the file was processed successfully
                    return array( 'message' => "File has been moved to Uploads Dir" );
               else 
                    return array( 'message' => "restore to uploads dir failed" );
	}

	/**
	 * Delete an archive.
	 *
	 * @param string $filename
	 * @param bool force
	 * @return true on success
	 */
	public function delete_archive( $filename, $force = false, $reassign = null ) {
	     
	     if ( unlink( ARCHIVES_DIR . $filename) )
                  return array('message' => sprintf('%s has been deleted', $filename ) );
             else
                  return array('message' => sprintf('%s could not be deleted', $filename) );

	}
}
