<?
/***************************************************************************************
* Warning !! CMCIC_Config contains the key, you have to protect this file with all     *   
* the mechanism available in your development environment.                             *
* You may for instance put this file in another directory and/or change its name       *
***************************************************************************************/
//code client
//define ("CMCIC_CLE", "votre cle fournit par la banque");
define ("CMCIC_CLE", "12345678901234567890123456789012345678P0");

//TPE
define ("CMCIC_TPE", "0000001");


//code société
define ("CMCIC_CODESOCIETE", "codesociete");


//ne pas toucher
define ("CMCIC_VERSION", "3.0");

//serveur de paiement
//serveur de test, supprimer une fois vos tests effectués
define ("CMCIC_SERVEUR", "https://ssl.paiement.cic-banques.fr/test/");
//serveur de production, décommenter lorsque votre statut est en production en supprimant les deux // au début de la ligne suivante
//define ("CMCIC_SERVEUR", "https://ssl.paiement.cic-banques.fr/");


//url de retour ok
define ("CMCIC_URLOK", "http://urlthelia/merci.php");


//url de retour ko
define ("CMCIC_URLKO", "http://urlthelia/regret.php");


?>
