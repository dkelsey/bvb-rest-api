<?php

class WP_JSON_Uploads extends WP_JSON_Posts {
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
			'/uploads' => array(
				array( array( $this, 'get_uploads' ),        WP_JSON_Server::READABLE ),
				array( array( $this, 'upload_csv' ),         WP_JSON_Server::CREATABLE ),
			),
			'/uploads/test/(?P<filename>.+(?i:\.csv))' => array(
				array( array( $this, 'test_upload' ),       WP_JSON_Server::READABLE ),
			),
			'/uploads/process/(?P<filename>.+(?i:\.csv))' => array(
				array( array( $this, 'process_upload' ),       WP_JSON_Server::READABLE ),
			),
			'/uploads/(?P<filename>.+(?i:\.csv)?)' => array(
				array( array( $this, 'get_upload' ),       WP_JSON_Server::READABLE ),
				array( array( $this, 'delete_upload' ),    WP_JSON_Server::DELETABLE ),
			),
		);
		return array_merge( $routes, $user_routes );
	}

	/**
	 * Retrieve uploads.
	 *
	 * @param array $filter Extra query parameters for {@see WP_User_Query}
	 * @param string $context optional
	 * @param int $page Page number (1-indexed)
	 * @return array contains a collection of User entities.
	 */
	public function get_uploads( $filter = array(), $context = 'view', $page = 1 ) {

               $files = array_diff(scandir( UPLOADS_DIR ),array('..','.'));

               $fileData = array();
               foreach ($files as &$file) {
                 $fileSize = filesize(UPLOADS_DIR . $file);
                 $fileSize = $this->formatBytes($fileSize, 1);
                 $fileCTime = filectime(UPLOADS_DIR.$file);
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

		$current_user_id = get_current_user_id();

//*
    // why doesn't this work? - the nonce check :when there's no nonce the user is set to 0.  I've disabled this.  
    // further this is running from within a logged in session within the admin control panel of WordPress.  
                // Permissions check - Note: "upload_files" cap is returned for an attachment by $post_type->cap->create_posts
                if ( ! current_user_can( $post_type->cap->create_posts ) || ! current_user_can( $post_type->cap->edit_posts ) ) {
                        return new WP_Error( 'json_cannot_create', __( 'Sorry, you are not allowed to post on this site. create_posts?:'. $current_user_id . current_user_can($post_type->cap->edit_posts) ), array( 'status' => 400 ) );
                }

//*/
                // Get the file via $_FILES or raw data
                if ( ! move_uploaded_file($_files['file']['tmp_name'], UPLOADS_DIR . $_files['file']['name']) ) {
                        return new WP_Error( 'move_uploaded_file_fail', __( 'move_uploaded_file faild: '. $_files['file']['tmp_name']  ), array( 'status' => 400 ) );
		}

                return new WP_JSON_Response( array('message' => 'uploads/'), 201, $_headers );
        }

	/**
	 * Retrieve an uploaded file's info .
	 *
	 * @param int $id User ID
	 * @param string $context
	 * @return response
	 */
	public function get_upload( $filename, $context = 'view' ) {

	        return $this->getFileInfo($filename) ;

	}
	private function getFileInfo($file_name) {
	        $fileSize = filesize(UPLOADS_DIR . $file_name);
	        $fileSize = $this->formatBytes($fileSize, 1);
	        $fileCTime = filectime(UPLOADS_DIR . $file_name);
	        $fileCTime = date('M d, Y', $fileCTime);

                return array( $file_name => array ('filesize' => $fileSize, 'filectime' => $fileCTime,));
	}

	/**
	 * Retrieve an uploaded file's info .
	 *
	 * @param int $id User ID
	 * @param string $context
	 * @return response
	 */
	public function test_upload( $filename, $context = 'view' ) {

             if ( preg_match('/csv_.*\.csv/i', $filename) ) {
                  include 'batchVerify.php';
                  return batchVerify( UPLOADS_DIR . $filename);
             }
             else if ( preg_match('/BCLDB_.*\.csv/i', $filename) ) {
                  include 'remittanceVerify.php';
                  return remittanceVerify( UPLOADS_DIR . $filename) ;
             }
             else return array ( 'invalid_file' => sprintf('%s is unknows file type', $filename ) ) ;

	}

	/**
	 * Retrieve an uploaded file's info .
	 *
	 * @param int $id User ID
	 * @param string $context
	 * @return response
	 */
	public function process_upload( $filename, $context = 'view' ) {

             if ( preg_match('/csv_.*\.csv/i', $filename) ) {
                  include 'batchProcess.php';
                  return batchProcess( UPLOADS_DIR . $filename);
             }
             else if ( preg_match('/BCLDB_.*\.csv/i', $filename) ) {
                  include 'remittanceProcess.php';
                  return remittanceProcess( UPLOADS_DIR . $filename) ;
             }
             else return array ( 'invalid_file' => sprintf('%s is unknows file type', $filename ) ) ;

	}

	/**
	 * Delete an upload.
	 *
	 * @param string $filename
	 * @param bool force
	 * @return true on success
	 */
	public function delete_upload( $filename, $force = false, $reassign = null ) {

	     if ( unlink( UPLOADS_DIR . $filename) )
                  return array('message' => sprintf('%s has been deleted', $filename ) );
             else
                  return array('message' => sprintf('%s could not be deleted', $filename) );

	}
}
