<?php

require_once('../../minivc.php');

class PostsController extends Controller {
	
	var $template = "default";
	
	public function post() {
		
		$post = array("message" => $this->input["message"]);
		
		$id = $this->getModel("post")->insert($post);
		$this->redirect("posts.php?mvcmethod=show&id=$id");
		
	}
	
	public function show() {
				
		$item = $this->load("post", array("id" => $this->input["id"]));
		if(!empty($item)) {
			$this->set("post", $item);
		} else {
			$this->redirect("index.php");
		}
		
	}
	
	public function delete() {
	
		$item = $this->load("post", array("id" => $this->input["id"]));
		if(!empty($item)) {
			$item->delete();
		}	
		
		$this->redirect("index.php");
	
	}
	
}

?>
