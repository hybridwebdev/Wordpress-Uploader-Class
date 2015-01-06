<?php
// This class consists of a main upload handler that utilizes wordpresses built-in handler for processing uploads. We tack on a few of our own filters, such as
// pre-sanitizing the file-name. We use a white-list approach for mime-type and file extensions set using arrays and a function. In addition, the upload directory
// is built dynamically in one place, to ensure consistency.
//
// there are a few additional helper functions to move and delete files, which have their own checks in place to ensure files aren't overwritten.

class ngg_handle_file_upload {

	public function __construct() {
		
		  	
      $options = new NGG_pup_options(array('just_options'=>true));
		$this->options_array = $options->options_array;
		
			$this -> allowed_exts = $this->options_array['allowed_extensions'];

			$this->allowed_mimes = $this->options_array['allowed_mimes'];
					
		if(!$this -> allowed_exts) 
				$this -> allowed_exts = array('jpg');
		
		if(!$this -> allowed_mimes) 
			$this -> allowed_mimes = array('image');
		
		$this -> upload_path = $this->options_array['Upload_directory']; 
		$this -> upload_size_limit = $this->options_array['Upload_Size_Limit'];
		
		$this -> allowed_mime_list = $this -> filter_mime_types();
		
	}

	public function move_file() {
			
		if (!is_dir($this -> _file['destination'])) {
			
			if (!mkdir($this -> _file['destination'], 0777, true)) 
				return array('error' => true, 'message' => "Could not create directory.");

		}

		if (!file_exists($this -> _file['source'])) 
			return array('error' => true, 'message' => "Source file does not exist.");
		
		if (file_exists($this -> _file['destination'] . $this -> _file['name'])) 
			return array('error' => true, 'message' => "Target directory already has a file with that name.");
		
		if (rename($this -> _file['source'], $this -> _file['destination'] . $this -> _file['name'])) {
			
			return array('error' => false, 'message' => "File moved successfully.");
			
		} else {
			
			return array('error' => true, 'message' => "There was a problem moving the file.");
			
		}
	}

	public function delete_file() {
			
		if (!file_exists($this -> _file['file_path']))
			Return "Could not find file.";
		
		$status = unlink(file_exists($this -> _file['file_path']));
		
		return ($status) 
			? "File deleted successfully." 
			: "Could not delete file.";

	}

	public function filter_mime_types() {
			
		$allowed_types = array();
		$allowed_extensions = array();
		$allowed_mime_list = array();
		
		if (!function_exists('get_allowed_mime_types'))
			
			return "Function failed";
		
		$mime_list = get_allowed_mime_types();

		foreach ($mime_list as $ext => $mime) {

			$ext_pieces = explode("|", $ext);
			
			$mime_list_pieces = explode("/", $mime);

			foreach ($ext_pieces as $new_exts) {
					
				$list[$mime_list_pieces[0]][] = $new_exts;

				if (in_array($mime_list_pieces[0], $this -> allowed_mimes) && (!in_array($mime_list_pieces[0], $allowed_types))) 
					$allowed_types[] = $mime_list_pieces[0];
				

				if (in_array($new_exts, $this -> allowed_exts) && (!in_array($mime_list_pieces[0], $allowed_extensions))) 
					$allowed_extensions[] = $new_exts;
				

				if ((in_array($new_exts, $this -> allowed_exts)) && (in_array($mime_list_pieces[0], $this -> allowed_mimes))) 
					$allowed_mime_list[$new_exts] = $mime;
				
			}
		}

	return array($allowed_mime_list, $list, $allowed_types, $allowed_extensions);
		
		
	}

	public function handle_upload() {
		

		$target_dir = $this -> change_wp_upload_dir(wp_upload_dir());
		
		if ($target_dir['error']) return array('error'=>true,'message'=>$target_dir['message']);
		
		$this -> _file['name'] = preg_replace('/[^a-zA-Z0-9-_\.]/', '', strtolower(sanitize_file_name($this -> _file['name']))); // 1 line sanitization, cool. 

		$this -> _file['real_mime'] = $this -> get_file_mime($this -> _file['tmp_name']);

		if ($this -> _file['size'] < 1)
        	return ( array("message" => "Sorry, {$this->_file['name']} is too small, you can't upload empty files.", "error" => true));
				
		if (!in_array($this -> _file['real_mime'], $this -> allowed_mime_list[2]))
			return ( array("message" => "{$this->_file['name']} Sorry, this file type is not permitted for security reasons.", "error" => true));
            

		if (file_exists($target_dir['path'] . "/" . $this -> _file['name'])) 
			return ( array("message" => "Sorry, {$this->_file['name']} already exists", "error" => true));
		

		if ($this -> _file['size'] > $this -> upload_size_limit)
			return ( array("message" => "Sorry, {$this->_file['name']} exceeds the size limit.", "error" => true));

		add_filter('upload_dir', array(&$this, 'change_wp_upload_dir'));

			$upload_file = wp_handle_upload($this -> _file, array('test_form' => false, 'mimes' => $this -> allowed_mime_list[0]));

		remove_filter('upload_dir', array(&$this, 'change_wp_upload_dir'));

		if (!empty($upload_file['error'])) {
			
			return array("message" => "{$this->_file['name']} " . $upload_file['error'], "error" => true, 'file' => $upload_file);
			
		} else {
			
			$upload_file['name'] = $this -> _file['name'];
			$upload_file['path'] = $target_dir['path'] . "/";
			
			return array("message" => "{$this->_file['name']} uploaded sucessfully.", "error" => false, 'file' => $upload_file);
			
		}
	}

	private function get_file_mime($file) {
		// Tries 3 different ways to grab file mime info, starting with the functions that are available in newest
		// php installs, and works it way down to older versions. In MOST server install/config environments, ONE
		// of these should work. For the first 2, we strip out the full mime data, and return JUST the type: Application, Image etc.

		if (class_exists('finfo') && defined('FILEINFO_MIME') && (function_exists(file_get_contents))) {
			$file_info = new finfo(FILEINFO_MIME);

			$mime_type = $file_info -> buffer(file_get_contents($file));
			$mime_type = explode(" ", $mime_type);
			$mime_type = str_replace(";", "", $mime_type[0]);
			$mime_type = explode("/", $mime_type);

			if ($mime_type[0])
				return $mime_type[0];
		}

		if (class_exists('finfo') && defined('FILEINFO_MIME_TYPE')) {
			$mime_type = finfo_file(finfo_open(FILEINFO_MIME), $file);
			$mime_type = explode(" ", $mime_type);
			$mime_type = str_replace(";", "", $mime_type[0]);
			$mime_type = explode("/", $mime_type);

			if ($mime_type)
				return $mime_type[0];
		}

		if (function_exists(mime_content_type)) {
			
			$mime_type = mime_content_type($file);
			
			if ($mime_type)
				return $mime_type;
		}
		
        return $this -> allowed_mimes[0]; // if all else fails, we degrade and let WP handle the mime validation. It's not as secure, 
        //but it works. We also pass back the first instance of the allowed_mimes so that the function can continue without the extra security.  
	}

	function change_wp_upload_dir($upload) { // don't forget
		
		if($this->long_directory) {
			
		if ( !defined('ABSPATH') || !function_exists('Site_url') ) return array('error'=>true,'message'=>"Couldn't resolve relative paths. Try using default directories instead, or re-configure your settings. ");
			
			$this->long_directory = ltrim(rtrim($this->long_directory, '/'),'/');
			
			$upload['basedir'] = rtrim(ABSPATH, '/');			 
			
			$upload['subdir'] = $this->long_directory;
			
			$upload['path'] = $upload['basedir'] . "/" .  $upload['subdir'];
			$upload['url'] = Site_url() . "/". $upload['subdir'];
			
			$upload['path'] = str_replace("\\", "/", $upload['path']);
			$upload['baseurl'] = Site_url();

		} else {
			
			$upload['subdir'] = $this -> upload_path;
			$upload['path'] = $upload['basedir'] ."/". $upload['subdir'];
			$upload['url'] = $upload['baseurl'] ."/". $upload['subdir'];
			$upload['path'] = str_replace("\\", "/", $upload['path']);

		}
		
		
		
		return $upload;
		
		
	}

}
?>