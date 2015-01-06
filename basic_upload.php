<?php class uploader extends upload_class {
	
	function __construct() {
		  	
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
}
