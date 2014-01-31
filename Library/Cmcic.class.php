<?php
include_once(realpath(dirname(__FILE__)) . "/../../../classes/PluginsPaiements.class.php");


class Cmcic extends PluginsPaiements{
	
	var $defalqcmd = 1;
	
	function __construct(){
		parent::__construct("cmcic");
	}
	
	function init(){
		$this->ajout_desc("CB", "CB", "", 1);			
	}

	function paiement($commande){

		header("Location: " . "client/plugins/cmcic/paiement.php"); exit;			
	}
}


?>