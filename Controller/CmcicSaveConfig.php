<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace CmCIC\Controller;

use CmCIC\CmCIC;
use CmCIC\Form\ConfigureCmCIC;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Thelia\Tools\URL;

class CmcicSaveConfig extends BaseAdminController
{
    const CIC_SERVER = "https://ssl.paiement.cic-banques.fr/";
    const CM_SERVER = "https://paiement.creditmutuel.fr/";
    const OBC_SERVER = "https://ssl.paiement.banque-obc.fr/";
    const MONETICO_SERVER = "https://p.monetico-services.com/";

    const CMCIC_VERSION = "3.0";
    const CMCIC_URLOK = "/order/placed/";
    const CMCIC_URLKO = "/module/cmcic/payfail/";
    const CMCIC_URLRECEIVE = "/module/cmcic/receive/";

    public function downloadLog()
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CmCIC', AccessManager::UPDATE)) {
            return $response;
        }

        $data = @file_get_contents(THELIA_LOG_DIR . "log-cmcic.txt");

        if (empty($data)) {
            $data = Translator::getInstance()->trans("The CmCIC server log is currently empty.", [], CmCIC::DOMAIN_NAME);
        }

        $header = [
            'Content-Type' => "text/plain",
            'Content-Disposition' => sprintf(
                sprintf('Attachment;filename=log-cmcic.txt')
            ),
        ];
        return new Response($data, 200, $header);
    }

    public function save(Request $request)
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CmCIC', AccessManager::UPDATE)) {
            return $response;
        }

        $error_message="";
        $form = $this->createForm(ConfigureCmCIC::getName());

        try {
            $vform = $this->validateForm($form);

            // After post checks (PREG_MATCH)
            if (preg_match("#^\d{7}$#", $vform->get('CMCIC_TPE')->getData()) &&
                preg_match("#^[a-z\d]{40}$#i", $vform->get('CMCIC_KEY')->getData()) &&
                preg_match("#^[a-z\-\d]+$#i", $vform->get('CMCIC_CODESOCIETE')->getData()) &&
                preg_match("#^cic|cm|obc|mon$#", $vform->get('CMCIC_SERVER')->getData())
            ) {
                $serv = $vform->get('CMCIC_SERVER')->getData();

                switch($serv) {
                    case 'mon':
                        $serv = self::MONETICO_SERVER;
                        break;

                    case 'cic':
                        $serv = self::CIC_SERVER;
                        break;

                    case 'cm':
                        $serv = self::CM_SERVER;
                        break;

                    case 'obc':
                        $serv = self::OBC_SERVER;
                        break;

                    default:
                        throw new \InvalidArgumentException("Unknown server type '$serv'");
                }

                if ($vform->get('CMCIC_DEBUG')->getData() === true) {
                    $serv .= 'test/';
                }

                $data = $vform->getData();

                $data['CMCIC_SERVER'] = $serv;

                foreach ($data as $name => $value) {
                    if (!preg_match('/^CMCIC/', $name)) {
                        continue;
                    }
                    CmCIC::setConfigValue($name, $value);
                }
            } else {
                throw new \Exception($this->getTranslator()->trans("Error in form syntax, please check that your values are correct."));
            }
        } catch (\Exception $e) {
            $error_message = $e->getMessage();

            $this->setupFormErrorContext(
                'erreur sauvegarde configuration',
                $error_message,
                $form
            );
        }

        return $this->generateRedirect(URL::getInstance()->absoluteUrl("/admin/module/CmCIC"));
    }
}
