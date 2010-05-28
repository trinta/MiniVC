<?php

/* Change this path if you run the sample from somewhere else than from /docs/example/ */
require_once("../../minivc.php");

/* The IndexController is our default controller name */
class IndexController extends Controller {
	
	var $template = "default";
	
	public function index() {
		
		// Load the last 10 posts
		$this->set("posts", 
			$this->loadAll("post", array("conditions" => array(), 
					"order" => array("field" => "posted", "order" => "desc"), "limit" => 10)));
		
	}
	
}

?>
