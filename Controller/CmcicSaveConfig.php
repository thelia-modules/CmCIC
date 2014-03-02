<?php

namespace CmCIC\Controller;

use CmCIC\CmCIC;
use Thelia\Controller\Admin\BaseAdminController;
use CmCIC\Model\Config;
use CmCIC\Form\ConfigureCmCIC;
use Thelia\Core\Translation\Translator;

class CmcicSaveConfig extends BaseAdminController
{
    const CIC_SERVER = "https://ssl.paiement.cic-banques.fr/";
    const CM_SERVER = "https://paiement.creditmutuel.fr/";
    const OBC_SERVER = "https://ssl.paiement.banque-obc.fr/";
    const CMCIC_VERSION = "3.0";
    const CMCIC_URLOK = "/order/placed/";
    const CMCIC_URLKO = "/module/cmcic/payfail/";
    const CMCIC_URLRECEIVE = "/module/cmcic/receive/";

    public function save()
    {
        $error_message="";
        $conf = new Config();
        $form = new ConfigureCmCIC($this->getRequest());
        try {
            $vform = $this->validateForm($form);
            // After post checks (PREG_MATCH) & create json file
            if(preg_match("#^\d{7}$#",$vform->get('TPE')->getData()) &&
                preg_match("#^[a-z\d]{40}$#i", $vform->get('com_key')->getData()) &&
                preg_match("#^[a-z\d]+$#i", $vform->get('com_soc')->getData()) &&
                preg_match("#^cic|cm|obc$#", $vform->get('server')->getData())
            ) {
                $serv = $vform->get('server')->getData();
                $serv = ($serv === "cic" ?self::CIC_SERVER:($serv === "cm"?self::CM_SERVER:($serv === "obc" ?self::OBC_SERVER:""))).($vform->get('debug')->getData() === true ?"test/":"");

                $conf->setCMCICKEY($vform->get('com_key')->getData())
                    ->setCMCICVERSION(self::CMCIC_VERSION)
                    ->setCMCICCODESOCIETE($vform->get('com_soc')->getData())
                    ->setCMCICPAGE($vform->get('page')->getData())
                    ->setCMCICTPE($vform->get('TPE')->getData())
                    ->setCMCICVERSION(self::CMCIC_VERSION)
                    ->setCMCICURLOK(self::CMCIC_URLOK)
                    ->setCMCICURLKO(self::CMCIC_URLKO)
                    ->setCMCICSERVER($serv)
                    ->setCMCICURLRECEIVE(self::CMCIC_URLRECEIVE)
                    ->write(CmCIC::JSON_CONFIG_PATH)
                ;
            } else {
                throw new \Exception(Translator::getInstance()->trans("Error in form syntax, please check that your values are correct."));
            }
        } catch (\Exception $e) {
            $error_message = $e->getMessage();
        }
        $this->setupFormErrorContext(
            'erreur sauvegarde configuration',
            $error_message,
            $form
        );
        $this->redirectToRoute("admin.module.configure",array(),
            array ( 'module_code'=>"CmCIC",
                '_controller' => 'Thelia\\Controller\\Admin\\ModuleController::configureAction'));
    }
}
