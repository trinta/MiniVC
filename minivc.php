<?php

/*

MiniVC - A minimal MVC-framework
 
Copyright (c) 2010, Tuomas Rinta
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:
    * Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * Neither the name of the software nor the
      names of its contributors may be used to endorse or promote products
      derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

*/

/**
 * Configuration. Edit this if necessary
 */
class MiniVC {

	public static $CONF = array(
		/* The name of the HTTP parameter where the MiniVC system will look for the method name */
		"PARAMETER_NAME" => "mvcmethod",
		/* The folder where the views will be searched for */
		"VIEW_PATH" => "./views/",
		/* The folder where the templates will be searched for */
		"TEMPLATE_PATH" => "./templates/",
		/* The folder where model classes will be searched for */
		"MODEL_PATH" => "./models/",
		/* Datasource configuration */
		"MYSQL" => array("hostname" => "localhost", "username" => "username", "password" => "password", "database" => "minivc")
	);

}

/*===============================================*
 * NO EDITING BEYOND THIS. HERE BE DRAGONS.
 *===============================================*/

/**
 * The Controller class
 * 
 * Controllers need to extend this class.
 */
class Controller {

	/* Should the current controller render it's view */
	public $render_view = true;

	/* The template in which the output should be encapsulated in. 
	 * If null, it will be <controller>.phtml
	 */
	public $template = null;

	/* The variables of the controller method
	 */
	public $variables = array();

	/* MySQL link
	 */
	private $link = null;

	/**
	 * Set a variable for the view
	 */
	public function set($key, $value) {
		$this->variables[$key] = $value;
	}

	/**
	 * Send a redirect to a certain address.
	 * 
	 * This automatically disables view rendering.
	 */
	public function redirect($target) {
		header("Location: $target");
		$this->render_view = false;
	}

	/**
	 * Load a single item from database
	 */
	public function load($table, $conditions = array()) {
		if(!is_array($conditions["conditions"])) {
			$conditions = array("conditions" => $conditions);
		}
		$conditions["limit"] = 1;

		try {
			$data = $this->loadAll($table, $conditions);
		} catch(Exception $e) {
			throw $e;
		}

		return $data[0];
	}
	
	public function getModel($table) {
		/* See if we have a model class */
		$model = "MiniVCModel";
		if(!class_exists(ucfirst($table) . "Model")) {
			// See if we have a class
			if(file_exists(MiniVC::$CONF["MODEL_PATH"] . strtolower($table) . "_model.php")) {
				require_once(MiniVC::$CONF["MODEL_PATH"] . strtolower($table) . "_model.php");
				$model = ucfirst($table) . "Model";
			}
		} 
		$modelImpl = new $model();
		$modelImpl->model_name = strtolower($table);
		$modelImpl->setController($this);
		return $modelImpl;
	}

	public function loadAll($table, $conditions = array()) {

		/* See if we have a simple parameter-set, such as array("id" => 1) */
		if(!is_array($conditions["conditions"])) {
			$conditions = array("conditions" => $conditions);
		}

		$query = "select * from `%s` %s";

		$parameterSet = array();

		/* Query conditions */
		if(!empty($conditions["conditions"])) {
			$whereSet = array();
			foreach($conditions["conditions"] as $key => $value) {
				$whereSet[] = "`$key`='" . mysql_escape_string($value) . "'";
			}
			$parameterSet[] = "where " . join($whereSet, " AND ");
		}

		/* Ordering */
		if(!empty($conditions["order"])) {
			$parameterSet[] = "order by " . $conditions["order"]["field"] . " " . 
				(empty($conditions["order"]["direction"]) ? "desc" : $conditions["order"]["direction"]);
		}

		/* Limit */
		if(!empty($conditions["limit"])) {
			$parameterSet[] = "limit " . $conditions["limit"];
		}

		$actual_query = sprintf($query, $table, join($parameterSet, " "));

		try {
			$result = $this->executeSQL($actual_query);
		} catch(Exception $e) {
			throw $e;
		}

		/* See if we have a model class */
		$model = "MiniVCModel";
		if(!class_exists(ucfirst($table) . "Model")) {
			// See if we have a class
			if(file_exists(MiniVC::$CONF["MODEL_PATH"] . strtolower($table) . "_model.php")) {
				require_once(MiniVC::$CONF["MODEL_PATH"] . strtolower($table) . "_model.php");
				$model = ucfirst($table) . "Model";
			}
		} 

		$model_results = array();

		while(($row = mysql_fetch_array($result, MYSQL_ASSOC)) !== false) {

			$modelItem = new $model();
			$modelItem->model_name = strtolower($table);
			$modelItem->setController($this);

			foreach($row as $key => $value) {
				$modelItem->$key = $value;
				$modelItem->field_names[] = $key;
			}

			$model_results[] = $modelItem;
		}

		return $model_results;

	}

	/**
	 * Execute an SQL statement
	 */
	public function executeSQL($sql) {

		/* If we're not connected to the database, do it at this point */
		if(empty($this->link)) {
			$this->link = 
				mysql_connect(
					MiniVC::$CONF["MYSQL"]["hostname"],
					MiniVC::$CONF["MYSQL"]["username"],
					MiniVC::$CONF["MYSQL"]["password"]
				);
			mysql_select_db(MiniVC::$CONF["MYSQL"]["database"], $this->link);
		}

		/* Perform the query. If it fails, throw an exception */
		$result = mysql_query($sql);
		if($result === false) {
			throw new Exception(mysql_error());
		}

		/* If we performed an insert, return the create ID (if any) */
		if(preg_match("#^insert #i", $sql)) {
			return @mysql_insert_id();
		}

		/* Return the queried rows */
		return $result;

	}

	/**
	 * Close any MySQL connections when shuttin down
	 */
	public function __destruct() {
		if(!empty($this->link)) {
			@mysql_close($this->link);
		}
	}


}

/**
 * The Model clas
 */
 
class MiniVCModel {
	
	/* The primary key name. Subclasses should define this to use update() delete() */
	var $primary_key = null;

	/* The field names which have been loaded */
	var $field_names = array();	
	
	/* Parent controller */
	var $controller = null;
	
	public function setController($c) {
		$this->controller = $c;		
	} 

	/** Delete the currently loaded item */
	public function delete() {
		if(empty($this->primary_key)) {
			trigger_error("Cannot delete an item of model " . $this->model_name . " as no primarey key defined", E_ERROR);
		}
		$query = "delete from `%s` where `%s`='%s'";
		/* Execute the query */
		$this->controller->executeSQL(
			sprintf(
				$query,
				$this->model_name,
				$this->primary_key,
				$this->{$this->primary_key}
			)
		);
	}
	
	/** Save data */
	public function insert($data) {
		
		$query = "insert into `%s` (%s) values (%s)";
		
		$keys = array();
		$values = array();
		foreach($data as $key => $value) {
			$keys[] = mysql_escape_string($key);
			$values[] = "'" . mysql_escape_string($value) . "'";
		}
		
		return $this->controller->executeSQL(
			sprintf(
				$query,
				$this->model_name,
				join($keys, ","),
				join($values, ",")
			)
		);
		
	}
	
	/** Update the currently loaded item */
	public function update() {
		if(empty($this->primary_key)) {
			trigger_error("Cannot update an item of model " . $this->model_name . " as no primarey key defined", E_ERROR);
		}
		$query = "update `%s` set `%s` where `%s`='%s'";
		$updates = array();
		foreach($this->field_names as $fld) {
			$updates[] = "`$fld`=`" . myqsl_escape_string($this->$fld) . "`";
		}
		/* Do the update */
		$this->controller->executeSQL(
			sprintf(
				$query,
				$this->model_name,
				join($updates, ", "),
				$this->primary_key,
				$this->{$this->primary_key}
			)
		);
	}
	
}


/*=====================================================*
 * MVC controller and method handling
 *=====================================================*/
 
__mvc();

/* 
 * The MVC functionality is enclosed in a function so variable names won't
 * affect files including this.
 */
function __mvc() {

	/* Re-require the calling file to process it */
	require($_SERVER['SCRIPT_FILENAME']);	

	/* 
	 * Create an instance of the controller.
	 * If the file is index.php, the controller should be named IndexController
	 */
	$classname = (ucfirst(basename($_SERVER['PHP_SELF'], '.php')) . "Controller");
	$controller = new $classname();
	
	if($_SERVER['REQUEST_METHOD'] == 'POST') {
		$controller->input = $_POST;
	} else {
		$controller->input = $_GET;
	}

	/*
	 * See if we have a condition to invoke the controller
	 */
	if(method_exists($controller, "beforeInvocation")) {
		/* If the beforeInvocation method returns false, stop execution */
		if($controller->beforeInvocation() === false) {
			exit();
		}
	}

	/*
	 * Invoke the method
	 */
	$controller->
		{
			(
				empty($_GET[MiniVC::$CONF["PARAMETER_NAME"]]) ?
				"index" : strtolower($_GET[MiniVC::$CONF["PARAMETER_NAME"]])
			)
		}();

	/*
	 * Then see what's next, render a view if necessary
	 */
	if($controller->render_view) {

		/* Render thew view */

		$view_file = MiniVC::$CONF["VIEW_PATH"] . 
			strtolower(basename($_SERVER['PHP_SELF'], '.php')) . "/" . 
			(empty($_REQUEST[MiniVC::$CONF["PARAMETER_NAME"]]) ?
				"index" : strtolower($_REQUEST[MiniVC::$CONF["PARAMETER_NAME"]])) . ".php";
				
		if(!file_exists($view_file)) {
			?>
			<div style="border: 1px solid #FF0000; background-color: #990000; color: #FFFFFF; padding: 10px; text-align: center">
			 Cannot load view for <?=strtolower(basename($_SERVER['PHP_SELF'], '.php'))?>::<?=
			 (empty($_REQUEST[MiniVC::$CONF["PARAMETER_NAME"]]) ?
				"index" : strtolower($_REQUEST[MiniVC::$CONF["PARAMETER_NAME"]]))?>, file not found:
				<?=$view_file?>
			</div>
			<?
			exit();			
		}

		extract($controller->variables);
		ob_start();

		require_once($view_file);

		$content_for_template = ob_get_contents();
		ob_end_clean();

		/* And smack it in a template */
		$template_file = MiniVC::$CONF["TEMPLATE_PATH"] .
			(empty($controller->template) ? strtolower(basename($_SERVER['PHP_SELF'], ".php")) : 
				$controller->template) . ".phtml";
				
		if(!file_exists($template_file)) {
			?>
<div style="border: 1px solid #FF0000; background-color: #990000; color: #FFFFFF; padding: 10px; text-align: center">
 Cannot load template <?=$template_file?>, file not found
</div>						
			<?
			exit();
		}

		require_once($template_file);

	}

	exit();

}

?>